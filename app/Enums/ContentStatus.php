<?php

namespace App\Enums;

/**
 * Editorial workflow status, shared by alerts / news / instructions / documents.
 */
enum ContentStatus: string
{
    case Draft = 'draft';
    case Review = 'review';
    case TranslationCheck = 'translation_check';
    case Approved = 'approved';
    case Scheduled = 'scheduled';
    case Published = 'published';
    case Updated = 'updated';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Returned = 'returned';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Черновик',
            self::Review => 'На согласовании',
            self::TranslationCheck => 'Проверка перевода',
            self::Approved => 'Одобрено',
            self::Scheduled => 'Запланировано',
            self::Published => 'Опубликовано',
            self::Updated => 'Обновлено',
            self::Completed => 'Завершено',
            self::Cancelled => 'Отменено',
            self::Returned => 'Возвращено',
            self::Archived => 'В архиве',
        };
    }

    /**
     * Dot color role for the StatusBadge (maps to CSS semantic tokens on the client).
     */
    public function tone(): string
    {
        return match ($this) {
            self::Draft, self::Completed, self::Cancelled, self::Archived => 'neutral',
            self::Review, self::TranslationCheck, self::Scheduled => 'warn',
            self::Approved, self::Published => 'ok',
            self::Updated => 'accent',
            self::Returned => 'danger',
        };
    }

    public function isPublic(): bool
    {
        return in_array($this, [self::Published, self::Updated, self::Completed], true);
    }

    /**
     * @return array<int, array{value: string, label: string, tone: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $case): array => [
                'value' => $case->value,
                'label' => $case->label(),
                'tone' => $case->tone(),
            ],
            self::cases(),
        );
    }
}
