<?php

namespace App\Support;

use App\Contracts\Workflowable;
use App\Enums\ContentStatus;
use Illuminate\Database\Eloquent\Model;

/**
 * The flash message after an editorial save, built from what actually
 * happened rather than from the button that was pressed: «Опубликовать» by
 * someone without the publish permission sends the material to approval, and
 * the message must say so.
 */
final class SaveOutcome
{
    /**
     * @param  array{published: string, review: string, scheduled?: string}  $phrases
     */
    public static function message(Model&Workflowable $subject, bool $submitted, array $phrases): string
    {
        $status = $subject->getWorkflowStatus();

        if (! $submitted) {
            return $status->isPublic()
                ? 'Изменения сохранены — они уже на сайте.'
                : 'Черновик сохранён.';
        }

        return match ($status) {
            ContentStatus::Published, ContentStatus::Updated => $phrases['published'],
            ContentStatus::Scheduled => $phrases['scheduled'] ?? 'Публикация запланирована.',
            ContentStatus::Review, ContentStatus::TranslationCheck, ContentStatus::Approved => $phrases['review'],
            default => 'Изменения сохранены.',
        };
    }
}
