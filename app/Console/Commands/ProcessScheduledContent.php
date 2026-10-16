<?php

namespace App\Console\Commands;

use App\Enums\ContentStatus;
use App\Models\Alert;
use App\Models\News;
use App\Models\User;
use App\Notifications\WorkflowNotification;
use App\Services\AlertMapService;
use App\Services\WorkflowService;
use App\Support\ContentTitle;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class ProcessScheduledContent extends Command
{
    protected $signature = 'content:process-scheduled';

    protected $description = 'Публикует запланированные материалы, автоматически завершает истёкшие предупреждения и уведомляет об истечении срока.';

    public function handle(WorkflowService $workflow): int
    {
        $published = $this->publishScheduled($workflow);
        $completed = $this->completeExpired($workflow);
        $notified = $this->notifyExpiring();

        // Отметка ставится последней строкой намеренно: она означает «цикл
        // планировщика дошёл до конца», а не «планировщик запустился». Сбой
        // одного материала изолирован (attempt), а если падает весь цикл —
        // например, недоступна база, — `/ready` перестанет отвечать «готов»,
        // и это правильный сигнал, а не ложная бодрость.
        Cache::put('health.scheduler.last_run', now()->toIso8601String(), now()->addHour());
        Cache::forever(AlertMapService::RECONCILED_AT_KEY, now()->toIso8601String());

        $this->info("Опубликовано: {$published} · завершено: {$completed} · уведомлений об истечении: {$notified}");

        return self::SUCCESS;
    }

    private function publishScheduled(WorkflowService $workflow): int
    {
        $count = 0;

        $due = [
            ...Alert::query()->where('status', ContentStatus::Scheduled->value)->where('scheduled_at', '<=', now())->get()->all(),
            ...News::query()->where('status', ContentStatus::Scheduled->value)->where('scheduled_at', '<=', now())->get()->all(),
        ];

        foreach ($due as $item) {
            if ($this->attempt('publish', $item, fn () => $workflow->transition($item, ContentStatus::Published))) {
                $count++;
            }
        }

        return $count;
    }

    private function completeExpired(WorkflowService $workflow): int
    {
        $count = 0;

        $alerts = Alert::query()
            ->whereIn('status', [ContentStatus::Published->value, ContentStatus::Updated->value])
            ->whereNotNull('ends_at')
            ->where('ends_at', '<=', now())
            ->get();

        foreach ($alerts as $alert) {
            if ($this->attempt('complete', $alert, fn () => $workflow->transition($alert, ContentStatus::Completed))) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * One material that can't be published or completed must not stop the
     * rest: the alerts after it would stay unpublished, and the run would
     * never reach its end mark, so the site would call all its data stale.
     * The material stays as it is and is tried again on the next run; the
     * failure is logged as critical (forwarded to Telegram) and the author is
     * told, both at most once a day per material.
     *
     * @param  'publish'|'complete'  $action
     * @param  callable(): mixed  $step
     */
    private function attempt(string $action, Alert|News $item, callable $step): bool
    {
        try {
            $step();

            return true;
        } catch (Throwable $exception) {
            $this->reportFailure($action, $item, $exception);

            return false;
        }
    }

    /**
     * @param  'publish'|'complete'  $action
     */
    private function reportFailure(string $action, Alert|News $item, Throwable $exception): void
    {
        if (! $exception instanceof ValidationException) {
            report($exception);
        }

        if (! Cache::add("scheduler.failed.{$action}.{$item->getMorphClass()}.{$item->getKey()}", true, now()->addDay())) {
            return;
        }

        $reason = $exception instanceof ValidationException
            ? implode(' ', Arr::flatten($exception->errors()))
            : $exception->getMessage();

        Log::critical('scheduled_content_failed', [
            'event' => 'scheduled_content_failed',
            'action' => $action,
            'type' => $item->getMorphClass(),
            'id' => $item->getKey(),
            'error' => $reason,
        ]);

        $author = $item->author_id ? User::find($item->author_id) : null;

        if ($author instanceof User) {
            $title = ContentTitle::of($item) ?: (string) ($item->internal_title ?? '');

            $author->notify(new WorkflowNotification(
                $item,
                $action === 'publish' ? 'Публикация по расписанию не состоялась' : 'Предупреждение не завершилось по сроку',
                "«{$title}»: {$reason} Система повторит попытку через минуту.",
                'warn',
            ));
        }
    }

    private function notifyExpiring(): int
    {
        $count = 0;

        $alerts = Alert::query()
            ->whereIn('status', [ContentStatus::Published->value, ContentStatus::Updated->value])
            ->whereNull('expiry_notified_at')
            ->whereNotNull('ends_at')
            ->whereBetween('ends_at', [now(), now()->addDay()])
            ->with('author')
            ->get();

        foreach ($alerts as $alert) {
            $recipient = $alert->author_id ? User::find($alert->author_id) : null;

            if ($recipient instanceof User) {
                $title = $alert->getTranslation('title', 'ru', false) ?: $alert->internal_title;
                $recipient->notify(new WorkflowNotification(
                    $alert,
                    'Истекает предупреждение',
                    "«{$title}» — срок действия истекает в течение 24 часов.",
                    'warn',
                ));
            }

            $alert->forceFill(['expiry_notified_at' => now()])->saveQuietly();
            $count++;
        }

        return $count;
    }
}
