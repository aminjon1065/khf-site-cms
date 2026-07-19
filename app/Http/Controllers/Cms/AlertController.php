<?php

namespace App\Http\Controllers\Cms;

use App\Enums\Channel;
use App\Enums\ContentStatus;
use App\Enums\HazardType;
use App\Enums\RoleName;
use App\Enums\Severity;
use App\Http\Controllers\Controller;
use App\Http\Requests\Alert\AlertRequest;
use App\Http\Resources\AlertResource;
use App\Models\Alert;
use App\Models\Instruction;
use App\Models\Region;
use App\Models\User;
use App\Services\WorkflowService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AlertController extends Controller
{
    public function __construct(private readonly WorkflowService $workflow) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Alert::class);

        $user = $request->user();
        $view = $request->string('view', 'active')->toString();
        $perPage = (int) $request->integer('per_page', 25);

        $base = fn (): Builder => $this->scopedQuery($request);

        $query = $base()->with(['regions', 'author', 'approver', 'districts']);
        $this->applyView($query, $view, $user);
        $this->applyFilters($query, $request);
        $this->applySort($query, $request);

        $alerts = $query->paginate($perPage)->withQueryString();

        return Inertia::render('alerts/index', [
            'alerts' => AlertResource::collection($alerts->items())->resolve(),
            'meta' => [
                'from' => $alerts->firstItem(),
                'to' => $alerts->lastItem(),
                'total' => $alerts->total(),
                'per_page' => $alerts->perPage(),
                'current_page' => $alerts->currentPage(),
                'last_page' => $alerts->lastPage(),
                'prev' => $alerts->previousPageUrl(),
                'next' => $alerts->nextPageUrl(),
            ],
            'filters' => [
                'view' => $view,
                'search' => $request->string('search')->toString(),
                'severity' => $request->string('severity')->toString(),
                'status' => $request->string('status')->toString(),
                'region' => $request->string('region')->toString(),
                'hazard' => $request->string('hazard')->toString(),
                'sort' => $request->string('sort')->toString(),
                'dir' => $request->string('dir', 'desc')->toString(),
            ],
            'savedViews' => $this->savedViewCounts($request),
            'options' => $this->filterOptions(),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Alert::class);

        return Inertia::render('alerts/wizard', [
            'alert' => null,
            'reference' => $this->reference(),
        ]);
    }

    public function edit(Request $request, Alert $alert): Response
    {
        $this->authorize('update', $alert);

        $alert->load(['regions', 'districts', 'relatedInstructions', 'author', 'approver']);

        return Inertia::render('alerts/wizard', [
            'alert' => $this->wizardPayload($alert),
            'reference' => $this->reference(),
        ]);
    }

    public function store(AlertRequest $request): RedirectResponse
    {
        $this->authorize('create', Alert::class);

        $alert = new Alert;
        $this->fill($alert, $request);
        $alert->author_id = $request->user()?->id;
        $alert->status = ContentStatus::Draft;
        $alert->save();

        $this->syncRelations($alert, $request);
        $this->runPublishAction($alert, $request);

        return redirect('/alerts')->with('success', $this->savedMessage($request));
    }

    public function update(AlertRequest $request, Alert $alert): RedirectResponse
    {
        $this->authorize('update', $alert);

        $this->fill($alert, $request);
        $alert->save();
        $this->syncRelations($alert, $request);
        $this->runPublishAction($alert, $request);

        return redirect('/alerts')->with('success', $this->savedMessage($request));
    }

    public function destroy(Alert $alert): RedirectResponse
    {
        $this->authorize('delete', $alert);
        $alert->delete();

        return back()->with('success', 'Предупреждение удалено.');
    }

    public function duplicate(Alert $alert): RedirectResponse
    {
        $this->authorize('create', Alert::class);

        $copy = $alert->replicate(['published_at', 'scheduled_at']);
        $copy->internal_title = $alert->internal_title.' (копия)';
        $copy->status = ContentStatus::Draft;
        $copy->author_id = request()->user()?->id;
        $copy->save();
        $copy->regions()->sync($alert->regions->pluck('id'));
        $copy->districts()->sync($alert->districts->pluck('id'));

        return redirect('/alerts/'.$copy->id.'/edit')->with('success', 'Создана копия предупреждения.');
    }

    public function publish(Request $request, Alert $alert): RedirectResponse
    {
        $this->authorize('publish', $alert);
        $this->workflow->transition($alert, ContentStatus::Published, $request->user(), force: true);

        return back()->with('success', 'Предупреждение опубликовано.');
    }

    public function unpublish(Request $request, Alert $alert): RedirectResponse
    {
        $this->authorize('publish', $alert);
        $validated = $request->validate(['comment' => ['required', 'string', 'min:3']], [
            'comment.required' => 'Укажите причину снятия с публикации.',
        ]);
        $this->workflow->transition($alert, ContentStatus::Cancelled, $request->user(), $validated['comment']);

        return back()->with('success', 'Предупреждение снято с публикации.');
    }

    // ---------------------------------------------------------------- helpers

    /**
     * @return Builder<Alert>
     */
    private function scopedQuery(Request $request): Builder
    {
        $query = Alert::query();
        $user = $request->user();

        if ($user && $user->hasRole(RoleName::RegionalEditor->value) && $user->region_id) {
            $query->whereHas('regions', fn (Builder $q) => $q->whereKey($user->region_id));
        }

        return $query;
    }

    /**
     * @param  Builder<Alert>  $query
     */
    private function applyView(Builder $query, string $view, ?User $user): void
    {
        match ($view) {
            'review' => $query->whereIn('status', ['review', 'translation_check']),
            'scheduled' => $query->where('status', 'scheduled'),
            'expiring' => $query->active()->whereDate('ends_at', today()),
            'mine' => $query->where('author_id', $user?->id),
            'all' => null,
            default => $query->active(),
        };
    }

    /**
     * @param  Builder<Alert>  $query
     */
    private function applyFilters(Builder $query, Request $request): void
    {
        if ($search = $request->string('search')->toString()) {
            $query->where(function (Builder $q) use ($search): void {
                $q->where('internal_title', 'like', "%{$search}%")
                    ->orWhere('title->ru', 'like', "%{$search}%")
                    ->orWhere('title->tg', 'like', "%{$search}%");
            });
        }
        if ($sev = $request->string('severity')->toString()) {
            $query->where('severity', $sev);
        }
        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }
        if ($hazard = $request->string('hazard')->toString()) {
            $query->where('hazard_type', $hazard);
        }
        if ($region = $request->string('region')->toString()) {
            $query->whereHas('regions', fn (Builder $q) => $q->where('code', $region));
        }
    }

    /**
     * @param  Builder<Alert>  $query
     */
    private function applySort(Builder $query, Request $request): void
    {
        $sort = $request->string('sort')->toString();
        $dir = $request->string('dir', 'desc')->toString() === 'asc' ? 'asc' : 'desc';

        match ($sort) {
            'severity' => $query->orderBy('severity', $dir),
            'status' => $query->orderBy('status', $dir),
            'published' => $query->orderBy('published_at', $dir),
            'ends' => $query->orderBy('ends_at', $dir),
            default => $query->orderByDesc('updated_at'),
        };
    }

    /**
     * @return array<int, array{key: string, label: string, count: int}>
     */
    private function savedViewCounts(Request $request): array
    {
        $views = [
            ['key' => 'active', 'label' => 'Активные'],
            ['key' => 'review', 'label' => 'Требуют согласования'],
            ['key' => 'scheduled', 'label' => 'Запланированные'],
            ['key' => 'expiring', 'label' => 'Истекают сегодня'],
            ['key' => 'mine', 'label' => 'Мои материалы'],
            ['key' => 'all', 'label' => 'Все предупреждения'],
        ];

        return array_map(function (array $v) use ($request): array {
            $q = $this->scopedQuery($request);
            $this->applyView($q, $v['key'], $request->user());
            $v['count'] = $q->count();

            return $v;
        }, $views);
    }

    /**
     * @return array<string, mixed>
     */
    private function filterOptions(): array
    {
        return [
            'severities' => Severity::options(),
            'statuses' => ContentStatus::options(),
            'hazards' => HazardType::options(),
            'regions' => Region::query()->orderBy('sort')->get()->map(fn (Region $r): array => [
                'value' => $r->code,
                'label' => $r->getTranslation('name', 'ru'),
            ])->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function reference(): array
    {
        return [
            'severities' => Severity::options(),
            'hazards' => HazardType::options(),
            'channels' => Channel::options(),
            'regions' => Region::query()->with('districts')->orderBy('sort')->get()->map(fn (Region $r): array => [
                'id' => $r->id,
                'code' => $r->code,
                'name' => $r->getTranslation('name', 'ru'),
                'districts_count' => $r->districts_count,
                'districts' => $r->districts->map(fn ($d): array => ['id' => $d->id, 'name' => $d->getTranslation('name', 'ru')])->all(),
            ])->all(),
            'sources' => ['Агентство по гидрометеорологии', 'Оперативная служба КЧС', 'Региональное управление'],
            'riskCategories' => [
                ['value' => 'hydro', 'label' => 'Гидрологический'],
                ['value' => 'geo', 'label' => 'Геологический'],
                ['value' => 'meteo', 'label' => 'Метеорологический'],
            ],
            'approvers' => User::query()->role([RoleName::ChiefEditor->value, RoleName::Approver->value, RoleName::Admin->value])
                ->get()->map(fn (User $u): array => ['id' => $u->id, 'name' => $u->name.' — '.($u->position ?? '')])->all(),
            'instructions' => Instruction::query()->get()->map(fn (Instruction $i): array => [
                'id' => $i->id, 'name' => $i->getTranslation('name', 'ru'),
            ])->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function wizardPayload(Alert $alert): array
    {
        return [
            'id' => $alert->id,
            'internal_title' => $alert->internal_title,
            'hazard_type' => $alert->hazard_type->value,
            'severity' => $alert->severity->value,
            'status' => $alert->status->value,
            'source' => $alert->source,
            'risk_category' => $alert->risk_category,
            'territory_type' => $alert->territory_type,
            'territory_note' => $alert->territory_note,
            'starts_at' => $alert->starts_at?->format('Y-m-d\TH:i'),
            'ends_at' => $alert->ends_at?->format('Y-m-d\TH:i'),
            'scheduled_at' => $alert->scheduled_at?->format('Y-m-d\TH:i'),
            'channels' => $alert->channels ?? [],
            'approver_id' => $alert->approver_id,
            'title' => $alert->getTranslations('title'),
            'summary' => $alert->getTranslations('summary'),
            'body' => $alert->getTranslations('body'),
            'instructions' => $alert->getTranslations('instructions'),
            'contacts' => $alert->getTranslations('contacts'),
            'regions' => $alert->regions->pluck('id')->all(),
            'districts' => $alert->districts->pluck('id')->all(),
            'related_instructions' => $alert->relatedInstructions->pluck('id')->all(),
            'languages' => $alert->languageCompleteness(),
        ];
    }

    private function fill(Alert $alert, AlertRequest $request): void
    {
        $alert->fill([
            'internal_title' => $request->input('internal_title'),
            'hazard_type' => $request->input('hazard_type'),
            'severity' => $request->input('severity'),
            'source' => $request->input('source'),
            'risk_category' => $request->input('risk_category'),
            'territory_type' => $request->input('territory_type'),
            'territory_note' => $request->input('territory_note'),
            'starts_at' => $request->input('starts_at'),
            'ends_at' => $request->input('ends_at'),
            'scheduled_at' => $request->input('scheduled_at'),
            'channels' => $request->input('channels', []),
            'approver_id' => $request->input('approver_id'),
        ]);

        foreach (['title', 'summary', 'body', 'instructions', 'contacts'] as $field) {
            /** @var array<string, string> $values */
            $values = $request->input($field, []);
            $alert->setTranslations($field, array_filter($values, fn (string $v): bool => trim($v) !== ''));
        }
    }

    private function syncRelations(Alert $alert, AlertRequest $request): void
    {
        $alert->regions()->sync($request->input('regions', []));
        $alert->districts()->sync($request->input('districts', []));
        $alert->relatedInstructions()->sync($request->input('related_instructions', []));
    }

    private function runPublishAction(Alert $alert, AlertRequest $request): void
    {
        if ($request->input('action') !== 'submit') {
            return;
        }

        $mode = $request->input('publish_mode', 'review');
        $user = $request->user();

        match ($mode) {
            'now' => $this->authorizeAndPublish($alert, $user),
            'schedule' => $this->workflow->transition($alert, ContentStatus::Scheduled, $user, force: true),
            default => $this->workflow->transition($alert, ContentStatus::Review, $user, force: true),
        };
    }

    private function authorizeAndPublish(Alert $alert, ?User $user): void
    {
        if ($user && $user->can('publish', $alert)) {
            $this->workflow->transition($alert, ContentStatus::Published, $user, force: true);
        } else {
            $this->workflow->transition($alert, ContentStatus::Review, $user, force: true);
        }
    }

    private function savedMessage(AlertRequest $request): string
    {
        if ($request->input('action') !== 'submit') {
            return 'Черновик сохранён.';
        }

        return match ($request->input('publish_mode')) {
            'now' => 'Предупреждение опубликовано.',
            'schedule' => 'Предупреждение запланировано к публикации.',
            default => 'Предупреждение отправлено на согласование.',
        };
    }
}
