<?php

namespace App\Services;

use App\Contracts\Workflowable;
use App\Enums\ContentStatus;
use App\Enums\Severity;
use App\Jobs\RevalidateFrontend;
use App\Models\Activity;
use App\Models\Alert;
use App\Models\User;
use App\Models\WorkflowTransition;
use App\Notifications\WorkflowNotification;
use App\Support\ContentTitle;
use App\Support\ContentTypes;
use App\Support\FrontendRevalidation;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Drives editorial workflow transitions for content models. Every transition
 * is validated, recorded in `workflow_transitions`, written to the activity
 * log (flagging critical actions), and notifies the responsible person.
 */
class WorkflowService
{
    public function __construct(private readonly PublicationChecklist $publicationChecklist) {}

    /**
     * Allowed transitions by source status.
     *
     * @var array<string, list<string>>
     */
    private const ALLOWED = [
        'draft' => ['review', 'scheduled', 'published'],
        'review' => ['translation_check', 'approved', 'published', 'returned', 'draft'],
        'translation_check' => ['approved', 'review', 'returned'],
        'approved' => ['published', 'scheduled', 'returned'],
        'scheduled' => ['published', 'cancelled', 'draft'],
        // «Снять с публикации» returns a material to drafts (as in WordPress),
        // so it can be fixed and published again under the same address.
        'published' => ['updated', 'completed', 'cancelled', 'archived', 'draft'],
        'updated' => ['completed', 'cancelled', 'archived', 'draft'],
        'returned' => ['draft', 'review'],
        'completed' => ['updated'],
        'cancelled' => ['draft'],
        // Legacy: materials unpublished before drafts took over can be revived.
        'archived' => ['draft'],
    ];

    /**
     * Statuses that require a mandatory comment to enter.
     *
     * @var list<string>
     */
    private const REQUIRE_COMMENT = ['returned', 'cancelled'];

    /**
     * @throws ValidationException
     */
    public function transition(
        Model&Workflowable $subject,
        ContentStatus $to,
        ?User $actor = null,
        ?string $comment = null,
        bool $force = false,
    ): WorkflowTransition {
        $from = $subject->getWorkflowStatus();

        if (in_array($to->value, self::REQUIRE_COMMENT, true) && blank($comment)) {
            throw ValidationException::withMessages([
                'comment' => 'Комментарий обязателен при возврате на доработку или отмене.',
            ]);
        }

        if (! $force && ! $this->canTransition($from, $to)) {
            throw ValidationException::withMessages([
                'status' => "Недопустимый переход: «{$from->label()}» → «{$to->label()}».",
            ]);
        }

        if ($to === ContentStatus::Published && ! $force) {
            $this->publicationChecklist->ensurePublishable($subject);
        }

        // Scheduling a critical alert is publishing it later: the scheduler
        // runs without a person, so the role check happens when it's planned.
        if (in_array($to, [ContentStatus::Published, ContentStatus::Scheduled], true)
            && $subject instanceof Alert && $actor !== null) {
            $this->guardCriticalPublish($subject, $actor);
        }

        $transition = DB::transaction(function () use ($subject, $from, $to, $actor, $comment): WorkflowTransition {
            $this->applyStatus($subject, $to);
            $subject->save();

            if (method_exists($subject, 'syncContentMediaVisibility')) {
                $subject->syncContentMediaVisibility();
            }

            $transition = $subject->transitions()->create([
                'from_status' => $from->value,
                'to_status' => $to->value,
                'user_id' => $actor?->id,
                'comment' => $comment,
            ]);

            $this->logActivity($subject, $from, $to, $actor, $comment);
            $this->notify($subject, $to, $actor, $comment);

            return $transition;
        });

        $changesSite = $from->isPublic() || in_array($to, [
            ContentStatus::Published,
            ContentStatus::Updated,
            ContentStatus::Completed,
            ContentStatus::Cancelled,
            ContentStatus::Archived,
        ], true);

        if ($changesSite && config('services.frontend.revalidation_url') && config('services.frontend.revalidation_secret')) {
            $payload = FrontendRevalidation::forContent($subject, $to->value);

            if ($payload !== null) {
                RevalidateFrontend::dispatch(
                    type: $payload['type'],
                    id: $payload['id'],
                    slug: $payload['slug'],
                    locales: $payload['locales'],
                    event: $payload['event'],
                )->afterCommit();
            }
        }

        return $transition;
    }

    public function canTransition(ContentStatus $from, ContentStatus $to): bool
    {
        return in_array($to->value, self::ALLOWED[$from->value], true);
    }

    /**
     * @return list<ContentStatus>
     */
    public function allowedNext(ContentStatus $from): array
    {
        return array_map(
            fn (string $value): ContentStatus => ContentStatus::from($value),
            self::ALLOWED[$from->value],
        );
    }

    /**
     * Critical (severity=critical) alerts may only be published by an
     * alert_operator or higher.
     *
     * @throws ValidationException
     */
    private function guardCriticalPublish(Alert $alert, User $actor): void
    {
        if ($alert->severity !== Severity::Critical) {
            return;
        }

        if (! $actor->hasAnyRole(['alert_operator', 'admin', 'superadmin', 'chief_editor'])) {
            throw ValidationException::withMessages([
                'severity' => 'Критическое предупреждение может публиковать только оператор предупреждений или выше.',
            ]);
        }
    }

    private function applyStatus(Model&Workflowable $subject, ContentStatus $to): void
    {
        $subject->setWorkflowStatus($to);

        if ($to === ContentStatus::Published && $subject->getAttribute('published_at') === null) {
            $subject->setAttribute('published_at', now());
        }
    }

    private function logActivity(
        Model&Workflowable $subject,
        ContentStatus $from,
        ContentStatus $to,
        ?User $actor,
        ?string $comment,
    ): void {
        activity($subject->getTable())
            ->performedOn($subject)
            ->causedBy($actor)
            ->event('workflow')
            ->withProperties([
                'attributes' => ['status' => $to->value],
                'old' => ['status' => $from->value],
                'comment' => $comment,
            ])
            ->tap(function (Activity $activity) use ($subject, $to): void {
                $activity->is_critical = $this->isCritical($subject, $to);
            })
            ->log("Статус: {$from->label()} → {$to->label()}");
    }

    private function isCritical(Model&Workflowable $subject, ContentStatus $to): bool
    {
        if (! $subject instanceof Alert) {
            return $to === ContentStatus::Cancelled;
        }

        return in_array($to, [
            ContentStatus::Published,
            ContentStatus::Updated,
            ContentStatus::Cancelled,
        ], true) || $subject->severity === Severity::Critical;
    }

    private function notify(Model&Workflowable $subject, ContentStatus $to, ?User $actor, ?string $comment = null): void
    {
        $title = $this->subjectTitle($subject);

        if ($to === ContentStatus::Returned) {
            $author = $this->relatedUser($subject->getAttribute('author_id'));

            // The reviewer's comment is the whole point of a return: the
            // author must see what to fix without hunting for it.
            if ($author && $author->isNot($actor)) {
                $author->notify(new WorkflowNotification(
                    $subject,
                    'Материал возвращён на доработку',
                    filled($comment)
                        ? "«{$title}» возвращён вам на доработку. Комментарий: {$comment}"
                        : "«{$title}» возвращён вам на доработку.",
                    'danger',
                ));
            }

            return;
        }

        if (in_array($to, [ContentStatus::Review, ContentStatus::TranslationCheck, ContentStatus::Approved], true)) {
            $approver = $this->relatedUser($subject->getAttribute('approver_id'));

            $approvers = $approver
                ? collect([$approver])
                : $this->approversFor($subject);

            foreach ($approvers as $recipient) {
                if ($recipient->is($actor)) {
                    continue;
                }

                // Approvers act in the approval center; the editor itself is
                // closed to reviewers without the edit permission (403).
                $recipient->notify(new WorkflowNotification(
                    $subject,
                    'Материал ожидает согласования',
                    "«{$title}» ожидает вашего решения.",
                    'warn',
                    route('approvals', [], false),
                ));
            }
        }
    }

    /**
     * @return EloquentCollection<int, User>
     */
    public function approversFor(Model&Workflowable $subject): EloquentCollection
    {
        $type = ContentTypes::slugFor($subject);
        if ($type === null) {
            return new EloquentCollection;
        }

        return User::query()
            ->where('is_active', true)
            ->permission(ContentTypes::module($type).'.approve')
            ->get();
    }

    private function relatedUser(mixed $id): ?User
    {
        return $id ? User::find((int) $id) : null;
    }

    private function subjectTitle(Model&Workflowable $subject): string
    {
        $title = ContentTitle::of($subject);

        if ($subject instanceof Alert) {
            return $title ?: $subject->internal_title;
        }

        return $title ?: '—';
    }
}
