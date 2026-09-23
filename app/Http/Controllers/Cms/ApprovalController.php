<?php

namespace App\Http\Controllers\Cms;

use App\Contracts\Workflowable;
use App\Enums\Channel;
use App\Enums\ContentStatus;
use App\Http\Controllers\Controller;
use App\Models\Alert;
use App\Models\Announcement;
use App\Models\Document;
use App\Models\Instruction;
use App\Models\News;
use App\Models\Page;
use App\Models\PendingChange;
use App\Models\Project;
use App\Models\User;
use App\Models\WorkflowTransition;
use App\Services\PendingChangeService;
use App\Services\WorkflowService;
use App\Support\ContentTitle;
use App\Support\ContentTypes;
use App\Support\EditorialContent;
use App\Support\PublicSite;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ApprovalController extends Controller
{
    public function __construct(
        private readonly WorkflowService $workflow,
        private readonly PendingChangeService $pendingChanges,
        private readonly EditorialContent $editorialContent,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user && $this->canApproveAny($user), 403);

        $queue = $this->buildQueue($user);

        // Changes to published materials («изменения на согласовании»).
        $requestedChange = $request->integer('change');
        $changeSelected = $requestedChange > 0
            || ($request->string('type')->toString() === '' && ($queue[0]['change_id'] ?? null) !== null);

        if ($changeSelected) {
            $changeId = $requestedChange > 0 ? $requestedChange : (int) $queue[0]['change_id'];
            abort_unless(collect($queue)->contains(fn (array $item): bool => $item['change_id'] === $changeId), 404);

            $change = PendingChange::query()->with(['changeable', 'author'])->findOrFail($changeId);

            return Inertia::render('approvals', [
                'queue' => $queue,
                'detail' => $this->changeDetail($change, $user),
            ]);
        }

        $selectedType = $request->string('type')->toString() ?: (string) ($queue[0]['type'] ?? '');
        $selectedId = (int) ($request->integer('id') ?: ($queue[0]['id'] ?? 0));

        $detail = null;
        if ($selectedType !== '' && $selectedId > 0) {
            abort_unless(collect($queue)->contains(
                fn (array $item): bool => $item['change_id'] === null && $item['type'] === $selectedType && $item['id'] === $selectedId,
            ), 404);

            $model = ContentTypes::resolve($selectedType, $selectedId);
            if ($model !== null) {
                $this->authorize('approve', $model);
                $detail = $this->detail($model, $selectedType, $user);
            }
        }

        return Inertia::render('approvals', [
            'queue' => $queue,
            'detail' => $detail,
        ]);
    }

    public function approve(Request $request): RedirectResponse
    {
        $model = $this->resolveOrFail($request);
        $this->authorize('approve', $model);

        $this->workflow->transition($model, ContentStatus::Published, $request->user());

        return redirect('/approvals')->with('success', 'Материал согласован и передан на публикацию.');
    }

    public function returnToAuthor(Request $request): RedirectResponse
    {
        $model = $this->resolveOrFail($request);
        $this->authorize('approve', $model);

        $validated = $request->validate([
            'comment' => ['required', 'string', 'min:3'],
        ], [
            'comment.required' => 'Укажите, что нужно доработать.',
        ]);

        $this->workflow->transition($model, ContentStatus::Returned, $request->user(), $validated['comment']);

        return redirect('/approvals')->with('success', 'Материал возвращён на доработку.');
    }

    public function applyChange(Request $request, PendingChange $pendingChange): RedirectResponse
    {
        $subject = $this->changeSubject($pendingChange);
        $this->authorize('approve', $subject);
        abort_unless($pendingChange->isPending(), 409, 'Это предложение уже рассмотрено.');

        $this->pendingChanges->apply($pendingChange, $request->user());

        return redirect('/approvals')->with('success', 'Изменения применены — они уже на сайте.');
    }

    public function rejectChange(Request $request, PendingChange $pendingChange): RedirectResponse
    {
        $subject = $this->changeSubject($pendingChange);
        $this->authorize('approve', $subject);
        abort_unless($pendingChange->isPending(), 409, 'Это предложение уже рассмотрено.');

        $validated = $request->validate([
            'comment' => ['required', 'string', 'min:3'],
        ], [
            'comment.required' => 'Напишите автору, почему изменения не приняты.',
        ]);

        $this->pendingChanges->reject($pendingChange, $request->user(), $validated['comment']);

        return redirect('/approvals')->with('success', 'Изменения отклонены, автор получил комментарий.');
    }

    // ---------------------------------------------------------------- helpers

    private function changeSubject(PendingChange $change): Model&Workflowable
    {
        $subject = $change->changeable;
        abort_unless($subject instanceof Model && $subject instanceof Workflowable, 404);

        return $subject;
    }

    /**
     * @return array<string, mixed>
     */
    private function changeDetail(PendingChange $change, User $user): array
    {
        $subject = $this->changeSubject($change);
        $this->authorize('approve', $subject);
        $type = (string) ContentTypes::slugFor($subject);
        $liveVersion = $subject->getAttribute('updated_at');

        return [
            'change_id' => $change->id,
            'type' => $type,
            'id' => $subject->getKey(),
            'title' => $this->title($subject),
            'kind' => 'Изменения: '.mb_strtolower(ContentTypes::label($type)),
            'subkind' => $this->subkind($subject),
            'severity' => $subject instanceof Alert ? $subject->severity->value : null,
            'status' => $subject->getWorkflowStatus()->value,
            'body' => '',
            'meta' => [
                ['label' => 'Предложил', 'value' => $change->author->name ?? '—'],
                ['label' => 'Отправлено', 'value' => $change->created_at?->isoFormat('D MMMM, HH:mm') ?? '—'],
            ],
            'languages' => null,
            'timeline' => [],
            'comment' => $change->base_updated_at !== null && $liveVersion !== null && ! $liveVersion->equalTo($change->base_updated_at)
                ? 'Опубликованную версию изменили после отправки предложения: применяются только поля из сравнения ниже, остальное останется как на сайте сейчас.'
                : null,
            'diff' => $this->pendingChanges->diff($change),
            'route' => (ContentTypes::META[$type]['route'] ?? '/').'/'.$subject->getKey().'/edit',
            'public_url' => PublicSite::urlFor($subject),
            'preview_url' => null,
            'can_approve' => $user->can('approve', $subject),
        ];
    }

    private function resolveOrFail(Request $request): Model&Workflowable
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(array_keys(ContentTypes::MAP))],
            'id' => ['required', 'integer'],
        ]);

        $model = ContentTypes::resolve($validated['type'], (int) $validated['id']);
        abort_unless($model !== null, 404);

        return $model;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildQueue(?User $user): array
    {
        if (! $user) {
            return [];
        }

        $items = [];

        foreach (ContentTypes::MAP as $type => $modelClass) {
            if (! $user->can(ContentTypes::module($type).'.approve')) {
                continue;
            }

            foreach ($modelClass::query()
                ->whereIn('status', [ContentStatus::Review->value, ContentStatus::TranslationCheck->value])
                ->with('author')
                ->get() as $model) {
                if ($user->can('approve', $model)) {
                    $items[] = $this->queueItem($model, $type, urgent: $type === 'alert');
                }
            }
        }

        foreach (PendingChange::query()->pending()->with(['changeable', 'author'])->get() as $change) {
            $subject = $change->changeable;

            if (! $subject instanceof Model || ! $subject instanceof Workflowable || ! $user->can('approve', $subject)) {
                continue;
            }

            $type = (string) ContentTypes::slugFor($subject);
            $items[] = [
                'change_id' => $change->id,
                'type' => $type,
                'id' => $subject->getKey(),
                'title' => $this->title($subject),
                'kind' => 'Изменения: '.mb_strtolower(ContentTypes::label($type)),
                'subkind' => $this->subkind($subject),
                'severity' => $subject instanceof Alert ? $subject->severity->value : null,
                'author' => $change->author->name ?? '—',
                'submitted' => $change->created_at?->isoFormat('D MMMM, HH:mm') ?? '',
                'submitted_ts' => $change->created_at->timestamp ?? 0,
                'urgent' => $type === 'alert',
            ];
        }

        usort($items, fn (array $a, array $b): int => [$b['urgent'], $b['submitted_ts']] <=> [$a['urgent'], $a['submitted_ts']]);

        return $items;
    }

    private function canApproveAny(User $user): bool
    {
        foreach (array_keys(ContentTypes::MAP) as $type) {
            if ($user->can(ContentTypes::module($type).'.approve')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    private function queueItem(Model&Workflowable $model, string $type, bool $urgent = false): array
    {
        return [
            'change_id' => null,
            'type' => $type,
            'id' => $model->getKey(),
            'title' => $this->title($model),
            'kind' => ContentTypes::label($type),
            'subkind' => $this->subkind($model),
            'severity' => $model instanceof Alert ? $model->severity->value : null,
            'author' => $this->authorName($model),
            'submitted' => $model->getAttribute('updated_at')->isoFormat('D MMMM, HH:mm'),
            'submitted_ts' => $model->getAttribute('updated_at')->timestamp,
            'urgent' => $urgent,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detail(Model&Workflowable $model, string $type, ?User $user): array
    {
        return [
            'type' => $type,
            'id' => $model->getKey(),
            'title' => $this->title($model),
            'kind' => ContentTypes::label($type),
            'subkind' => $this->subkind($model),
            'severity' => $model instanceof Alert ? $model->severity->value : null,
            'status' => $model->getWorkflowStatus()->value,
            'body' => $this->body($model),
            'meta' => $this->meta($model),
            'languages' => method_exists($model, 'languageCompleteness') ? $model->languageCompleteness() : null,
            'timeline' => $this->timeline($model),
            'comment' => $this->latestComment($model),
            'route' => (ContentTypes::META[$type]['route'] ?? '/').'/'.$model->getKey().'/edit',
            'change_id' => null,
            'diff' => [],
            'public_url' => null,
            // The approver reads the whole material as it will look on the
            // site, not only its title and lead.
            'preview_url' => $this->editorialContent->supports($model) ? $this->editorialContent->previewUrl($model) : null,
            'can_approve' => $user !== null && $user->can('approve', $model),
        ];
    }

    private function title(Model $model): string
    {
        $title = ContentTitle::of($model);

        if ($model instanceof Alert) {
            return $title ?: $model->internal_title;
        }

        return $title ?: '—';
    }

    private function subkind(Model $model): string
    {
        return match (true) {
            $model instanceof Alert => $model->hazard_type->label(),
            $model instanceof Document => $model->doc_type->label(),
            default => '',
        };
    }

    private function authorName(Model $model): string
    {
        return is_string($n = data_get($model, 'author.name')) ? $n : '—';
    }

    private function body(Model $model): string
    {
        return match (true) {
            $model instanceof Alert => $model->getTranslation('body', 'ru', false) ?: $model->getTranslation('summary', 'ru', false),
            $model instanceof News, $model instanceof Instruction, $model instanceof Project => $model->getTranslation('summary', 'ru', false),
            $model instanceof Announcement, $model instanceof Page => $model->getTranslation('body', 'ru', false),
            default => '',
        };
    }

    /**
     * @return array<int, array{label: string, value: string}>
     */
    private function meta(Model $model): array
    {
        $meta = [];

        if ($model instanceof Alert) {
            $model->loadMissing('regions');
            $meta[] = ['label' => 'Действие', 'value' => ($model->starts_at?->isoFormat('D.MM') ?? '—').' — '.($model->ends_at?->isoFormat('D.MM, HH:mm') ?? '—')];
            $meta[] = ['label' => 'Регионы', 'value' => $model->regions->map(fn ($r) => $r->getTranslation('name', 'ru'))->implode(', ') ?: 'Вся страна'];
            $meta[] = ['label' => 'Каналы', 'value' => collect($model->channels ?? [])->map(fn (string $c) => Channel::from($c)->label())->implode(', ')];
        }

        if ($model instanceof Document) {
            $meta[] = ['label' => 'Тип', 'value' => $model->doc_type->label().($model->number ? ' '.$model->number : '')];
            $meta[] = ['label' => 'Дата', 'value' => $model->doc_date?->isoFormat('D MMMM YYYY') ?? '—'];
        }

        return $meta;
    }

    /**
     * @return array<int, array{title: string, meta: string, state: string}>
     */
    private function timeline(Model&Workflowable $model): array
    {
        $steps = [];

        foreach ($model->transitions()->with('user')->get()->reverse() as $transition) {
            /** @var WorkflowTransition $transition */
            $who = is_string($n = data_get($transition, 'user.name')) ? $n : 'система';
            $steps[] = [
                'title' => ContentStatus::from($transition->to_status)->label(),
                'meta' => $who.' · '.($transition->created_at?->isoFormat('D.MM, HH:mm') ?? ''),
                'state' => 'done',
            ];
        }

        if (in_array($model->getWorkflowStatus(), [ContentStatus::Review, ContentStatus::TranslationCheck], true)) {
            $steps[] = ['title' => 'Согласование руководителем', 'meta' => 'ожидает вашего действия', 'state' => 'active'];
            $steps[] = ['title' => 'Публикация', 'meta' => 'автоматически после согласования', 'state' => 'todo'];
        }

        return $steps;
    }

    private function latestComment(Model&Workflowable $model): ?string
    {
        $transition = $model->transitions()->whereNotNull('comment')->with('user')->first();

        if (! $transition) {
            return null;
        }

        $who = is_string($n = data_get($transition, 'user.name')) ? $n : 'система';

        return 'Комментарий '.$who.' ('.($transition->created_at?->isoFormat('D.MM, HH:mm') ?? '').'): '.$transition->comment;
    }
}
