<?php

namespace App\Enums;

/**
 * Alert severity — 5 escalating levels (SeverityBadge).
 */
enum Severity: string
{
    case Info = 'info';
    case Attention = 'attention';
    case Warning = 'warning';
    case Danger = 'danger';
    case Critical = 'critical';

    public function label(): string
    {
        return match ($this) {
            self::Info => 'Информация',
            self::Attention => 'Внимание',
            self::Warning => 'Предупреждение',
            self::Danger => 'Опасность',
            self::Critical => 'Критический',
        };
    }

    /**
     * Escalation weight — higher is more severe.
     */
    public function weight(): int
    {
        return match ($this) {
            self::Info => 1,
            self::Attention => 2,
            self::Warning => 3,
            self::Danger => 4,
            self::Critical => 5,
        };
    }

    /**
     * Whether publishing this level requires the alert_operator role and confirmation.
     */
    public function requiresOperator(): bool
    {
        return $this === self::Critical;
    }

    /**
     * @return array<int, array{value: string, label: string, weight: int}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $case): array => [
                'value' => $case->value,
                'label' => $case->label(),
                'weight' => $case->weight(),
            ],
            self::cases(),
        );
    }
}
