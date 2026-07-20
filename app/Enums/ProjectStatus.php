<?php

namespace App\Enums;

/**
 * Project lifecycle status (distinct from the editorial {@see ContentStatus}).
 * The public site renders the Russian label directly.
 */
enum ProjectStatus: string
{
    case Preparation = 'preparation';
    case Implementing = 'implementing';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Preparation => 'Подготовка',
            self::Implementing => 'Реализуется',
            self::Completed => 'Завершён',
        };
    }

    /**
     * Semantic tone for the status tag (maps to the frontend hazard palette).
     */
    public function tone(): string
    {
        return match ($this) {
            self::Implementing => 'success',
            self::Preparation => 'info',
            self::Completed => 'neutral',
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
