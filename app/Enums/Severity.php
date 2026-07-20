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
     * The public-site alert level (5-point `none|info|warning|danger|critical`
     * scale used by the map and badges). `none` is not produced here — it means
     * "no active alert".
     */
    public function level(): string
    {
        return match ($this) {
            self::Info => 'info',
            self::Attention => 'warning',
            self::Warning => 'warning',
            self::Danger => 'danger',
            self::Critical => 'critical',
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
