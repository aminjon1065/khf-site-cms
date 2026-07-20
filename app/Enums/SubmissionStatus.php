<?php

namespace App\Enums;

/**
 * Processing status of a citizen submission (electronic reception).
 */
enum SubmissionStatus: string
{
    case New = 'new';
    case Reviewed = 'reviewed';
    case InProgress = 'in_progress';
    case Awaiting = 'awaiting';
    case Completed = 'completed';
    case Rejected = 'rejected';
    case Spam = 'spam';

    public function label(): string
    {
        return match ($this) {
            self::New => 'Новое',
            self::Reviewed => 'Просмотрено',
            self::InProgress => 'В работе',
            self::Awaiting => 'Ожидает ответа',
            self::Completed => 'Завершено',
            self::Rejected => 'Отклонено',
            self::Spam => 'Спам',
        };
    }

    /**
     * Dot colour role for the StatusBadge.
     */
    public function tone(): string
    {
        return match ($this) {
            self::New => 'danger',
            self::Reviewed, self::InProgress, self::Awaiting => 'warn',
            self::Completed => 'ok',
            self::Rejected, self::Spam => 'neutral',
        };
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
