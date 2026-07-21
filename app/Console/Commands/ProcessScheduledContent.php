<?php

namespace App\Console\Commands;

use App\Enums\ContentStatus;
use App\Models\Alert;
use App\Models\News;
use App\Models\User;
use App\Notifications\WorkflowNotification;
use App\Services\WorkflowService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class ProcessScheduledContent extends Command
{
    protected $signature = 'content:process-scheduled';

    protected $description = 'Публикует запланированные материалы, автоматически завершает истёкшие предупреждения и уведомляет об истечении срока.';

    public function handle(WorkflowService $workflow): int
    {
        $published = $this->publishScheduled($workflow);
        $completed = $this->completeExpired($workflow);
        $notified = $this->notifyExpiring();

        Cache::put('health.scheduler.last_run', now()->toIso8601String(), now()->addHour());

        $this->info("Опубликовано: {$published} · завершено: {$completed} · уведомлений об истечении: {$notified}");

        return self::SUCCESS;
    }

    private function publishScheduled(WorkflowService $workflow): int
    {
        $count = 0;

        foreach (Alert::query()->where('status', ContentStatus::Scheduled->value)->where('scheduled_at', '<=', now())->get() as $alert) {
            $workflow->transition($alert, ContentStatus::Published);
            $count++;
        }

        foreach (News::query()->where('status', ContentStatus::Scheduled->value)->where('scheduled_at', '<=', now())->get() as $news) {
            $workflow->transition($news, ContentStatus::Published);
            $count++;
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
            $workflow->transition($alert, ContentStatus::Completed);
            $count++;
        }

        return $count;
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
