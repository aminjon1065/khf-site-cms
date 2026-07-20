<?php

namespace App\Http\Controllers\Cms;

use App\Enums\ContentStatus;
use App\Enums\ProjectStatus;
use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Project\ProjectRequest;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use App\Models\User;
use App\Services\WorkflowService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    /**
     * @var list<string>
     */
    private const LOCALES = ['ru', 'tg', 'en'];

    public function __construct(private readonly WorkflowService $workflow) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Project::class);

        $view = $request->string('view', 'all')->toString();
        $perPage = (int) $request->integer('per_page', 25);

        $query = Project::query()->with(['author', 'media']);
        $this->applyView($query, $view, $request->user());
        $this->applyFilters($query, $request);
        $this->applySort($query, $request);

        $projects = $query->paginate($perPage)->withQueryString();

        return Inertia::render('projects/index', [
            'projects' => ProjectResource::collection($projects->items())->resolve(),
            'meta' => [
                'from' => $projects->firstItem(),
                'to' => $projects->lastItem(),
                'total' => $projects->total(),
                'per_page' => $projects->perPage(),
                'current_page' => $projects->currentPage(),
                'last_page' => $projects->lastPage(),
                'prev' => $projects->previousPageUrl(),
                'next' => $projects->nextPageUrl(),
            ],
            'filters' => [
                'view' => $view,
                'search' => $request->string('search')->toString(),
                'status' => $request->string('status')->toString(),
                'lifecycle' => $request->string('lifecycle')->toString(),
                'sort' => $request->string('sort')->toString(),
                'dir' => $request->string('dir', 'desc')->toString(),
            ],
            'savedViews' => $this->savedViewCounts($request),
            'options' => [
                'statuses' => ContentStatus::options(),
                'lifecycles' => ProjectStatus::options(),
            ],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Project::class);

        return Inertia::render('projects/form', [
            'project' => null,
            'reference' => $this->reference(),
        ]);
    }

    public function edit(Project $project): Response
    {
        $this->authorize('update', $project);

        $project->load(['author', 'media']);

        return Inertia::render('projects/form', [
            'project' => $this->formPayload($project),
            'reference' => $this->reference(),
        ]);
    }

    public function store(ProjectRequest $request): RedirectResponse
    {
        $this->authorize('create', Project::class);

        $project = new Project;
        $this->fill($project, $request);
        $project->author_id = $request->user()?->id;
        $project->status = ContentStatus::Draft;
        $project->save();

        $this->syncMedia($project, $request);
        $this->runPublishAction($project, $request);

        return redirect('/projects')->with('success', $this->savedMessage($request));
    }

    public function update(ProjectRequest $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $this->fill($project, $request);
        $project->save();

        $this->syncMedia($project, $request);
        $this->runPublishAction($project, $request);

        return redirect('/projects')->with('success', $this->savedMessage($request));
    }

    public function destroy(Project $project): RedirectResponse
    {
        $this->authorize('delete', $project);
        $project->delete();

        return back()->with('success', 'Проект удалён.');
    }

    public function duplicate(Project $project): RedirectResponse
    {
        $this->authorize('create', Project::class);

        $copy = $project->replicate(['slug', 'published_at']);
        $title = $project->getTranslations('title');
        $title['ru'] = ($title['ru'] ?? '').' (копия)';
        $copy->setTranslations('title', $title);
        $copy->slug = null;
        $copy->status = ContentStatus::Draft;
        $copy->author_id = request()->user()?->id;
        $copy->save();

        return redirect('/projects/'.$copy->id.'/edit')->with('success', 'Создана копия проекта.');
    }

    public function publish(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('publish', $project);
        $this->workflow->transition($project, ContentStatus::Published, $request->user(), force: true);

        return back()->with('success', 'Проект опубликован.');
    }

    public function unpublish(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('publish', $project);
        $validated = $request->validate(['comment' => ['required', 'string', 'min:3']], [
            'comment.required' => 'Укажите причину снятия с публикации.',
        ]);
        $this->workflow->transition($project, ContentStatus::Archived, $request->user(), $validated['comment'], force: true);

        return back()->with('success', 'Проект снят с публикации.');
    }

    // ---------------------------------------------------------------- helpers

    /**
     * @param  Builder<Project>  $query
     */
    private function applyView(Builder $query, string $view, ?User $user): void
    {
        match ($view) {
            'published' => $query->public(),
            'review' => $query->whereIn('status', [ContentStatus::Review->value, ContentStatus::TranslationCheck->value, ContentStatus::Approved->value]),
            'drafts' => $query->whereIn('status', [ContentStatus::Draft->value, ContentStatus::Returned->value]),
            'mine' => $query->where('author_id', $user?->id),
            default => null,
        };
    }

    /**
     * @param  Builder<Project>  $query
     */
    private function applyFilters(Builder $query, Request $request): void
    {
        if ($search = $request->string('search')->toString()) {
            $query->where(function (Builder $q) use ($search): void {
                $q->where('title->ru', 'like', "%{$search}%")
                    ->orWhere('title->tg', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }
        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }
        if ($lifecycle = $request->string('lifecycle')->toString()) {
            $query->where('lifecycle_status', $lifecycle);
        }
    }

    /**
     * @param  Builder<Project>  $query
     */
    private function applySort(Builder $query, Request $request): void
    {
        $dir = $request->string('dir', 'desc')->toString() === 'asc' ? 'asc' : 'desc';

        match ($request->string('sort')->toString()) {
            'status' => $query->orderBy('status', $dir),
            'published' => $query->orderBy('published_at', $dir),
            'order' => $query->orderBy('sort'),
            default => $query->orderByDesc('updated_at'),
        };
    }

    /**
     * @return array<int, array{key: string, label: string, count: int}>
     */
    private function savedViewCounts(Request $request): array
    {
        $views = [
            ['key' => 'all', 'label' => 'Все проекты'],
            ['key' => 'published', 'label' => 'Опубликованные'],
            ['key' => 'review', 'label' => 'На согласовании'],
            ['key' => 'drafts', 'label' => 'Черновики'],
            ['key' => 'mine', 'label' => 'Мои материалы'],
        ];

        return array_map(function (array $v) use ($request): array {
            $q = Project::query();
            $this->applyView($q, $v['key'], $request->user());
            $v['count'] = $q->count();

            return $v;
        }, $views);
    }

    /**
     * @return array<string, mixed>
     */
    private function reference(): array
    {
        return [
            'lifecycles' => ProjectStatus::options(),
            'authors' => User::query()->role([
                RoleName::Editor->value,
                RoleName::ChiefEditor->value,
                RoleName::Admin->value,
            ])->get()->map(fn (User $u): array => ['value' => $u->id, 'label' => $u->name])->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formPayload(Project $project): array
    {
        return [
            'id' => $project->id,
            'title' => $project->getTranslations('title'),
            'summary' => $project->getTranslations('summary'),
            'body' => $project->getTranslations('body'),
            'slug' => $project->slug,
            'status' => $project->status->value,
            'lifecycle_status' => $project->lifecycle_status->value,
            'code' => $project->code,
            'years' => $project->years,
            'customer' => $project->customer,
            'partner' => $project->partner,
            'budget' => $project->budget,
            'goals' => $this->normalizeGoals($project->goals),
            'timeline' => $this->normalizeTimeline($project->timeline),
            'direction' => $this->normalizeDirection($project->direction),
            'cover_url' => $project->getFirstMediaUrl('cover') ?: null,
            'sort' => (int) $project->sort,
            'published_at' => $project->published_at?->toIso8601String(),
            'languages' => $project->languageCompleteness(),
        ];
    }

    private function fill(Project $project, ProjectRequest $request): void
    {
        $project->fill([
            'lifecycle_status' => $request->input('lifecycle_status'),
            'code' => $request->input('code'),
            'years' => $request->input('years'),
            'customer' => $request->input('customer'),
            'partner' => $request->input('partner'),
            'budget' => $request->input('budget'),
            'sort' => (int) $request->integer('sort'),
            'goals' => $this->cleanGoals($request->input('goals')),
            'timeline' => $this->cleanTimeline($request->input('timeline')),
            'direction' => $this->normalizeDirection($request->input('direction')),
        ]);

        if ($request->filled('slug')) {
            $project->slug = $request->string('slug')->toString();
        }

        foreach (['title', 'summary', 'body'] as $field) {
            /** @var array<string, string|null> $values */
            $values = $request->input($field, []);
            $project->setTranslations($field, array_filter(
                $values,
                fn (?string $v): bool => $v !== null && trim($v) !== '',
            ));
        }
    }

    /**
     * @return array<string, list<string>>
     */
    private function cleanGoals(mixed $input): array
    {
        $input = is_array($input) ? $input : [];
        $result = [];

        foreach (self::LOCALES as $locale) {
            $items = $input[$locale] ?? [];

            if (! is_array($items)) {
                continue;
            }

            $clean = array_values(array_filter(
                array_map(fn ($s): string => is_string($s) ? trim($s) : '', $items),
                fn (string $s): bool => $s !== '',
            ));

            if ($clean !== []) {
                $result[$locale] = $clean;
            }
        }

        return $result;
    }

    /**
     * @return list<array{date: string, text: string, tone: string}>
     */
    private function cleanTimeline(mixed $input): array
    {
        $input = is_array($input) ? $input : [];
        $result = [];

        foreach ($input as $row) {
            if (! is_array($row)) {
                continue;
            }

            $text = is_string($row['text'] ?? null) ? trim($row['text']) : '';

            if ($text === '') {
                continue;
            }

            $tone = is_string($row['tone'] ?? null) ? $row['tone'] : 'info';

            $result[] = [
                'date' => is_string($row['date'] ?? null) ? trim($row['date']) : '',
                'text' => $text,
                'tone' => in_array($tone, ['success', 'info', 'warning', 'danger', 'neutral'], true) ? $tone : 'info',
            ];
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>|null  $goals
     * @return array<string, list<string>>
     */
    private function normalizeGoals(?array $goals): array
    {
        $goals ??= [];
        $result = [];

        foreach (self::LOCALES as $locale) {
            $items = $goals[$locale] ?? [];
            $result[$locale] = is_array($items) ? array_values($items) : [];
        }

        return $result;
    }

    /**
     * @param  array<int, mixed>|null  $timeline
     * @return list<array{date: string, text: string, tone: string}>
     */
    private function normalizeTimeline(?array $timeline): array
    {
        return $this->cleanTimeline($timeline);
    }

    /**
     * @param  array<string, mixed>|null  $direction
     * @return array{address: string, phone: string, email: string}
     */
    private function normalizeDirection(mixed $direction): array
    {
        $direction = is_array($direction) ? $direction : [];

        return [
            'address' => is_string($direction['address'] ?? null) ? trim($direction['address']) : '',
            'phone' => is_string($direction['phone'] ?? null) ? trim($direction['phone']) : '',
            'email' => is_string($direction['email'] ?? null) ? trim($direction['email']) : '',
        ];
    }

    private function syncMedia(Project $project, ProjectRequest $request): void
    {
        if ($request->boolean('cover_remove')) {
            $project->clearMediaCollection('cover');
        }

        if ($request->hasFile('cover')) {
            $project->clearMediaCollection('cover');
            $project->addMediaFromRequest('cover')->toMediaCollection('cover');
        }
    }

    private function runPublishAction(Project $project, ProjectRequest $request): void
    {
        if ($request->input('action') !== 'submit') {
            return;
        }

        $user = $request->user();

        match ($request->input('publish_mode', 'review')) {
            'now' => $this->authorizeAndPublish($project, $user),
            default => $this->workflow->transition($project, ContentStatus::Review, $user, force: true),
        };
    }

    private function authorizeAndPublish(Project $project, ?User $user): void
    {
        if ($user && $user->can('publish', $project)) {
            $this->workflow->transition($project, ContentStatus::Published, $user, force: true);
        } else {
            $this->workflow->transition($project, ContentStatus::Review, $user, force: true);
        }
    }

    private function savedMessage(ProjectRequest $request): string
    {
        if ($request->input('action') !== 'submit') {
            return 'Черновик сохранён.';
        }

        return $request->input('publish_mode') === 'now'
            ? 'Проект опубликован.'
            : 'Проект отправлен на согласование.';
    }
}
