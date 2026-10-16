<?php

namespace App\Enums;

/**
 * Publication / dissemination channels for alerts. No SMS: messages cost
 * money and the Committee has no budget for them (owner decision, 2026-10-16).
 */
enum Channel: string
{
    case Site = 'site';
    case SosApp = 'sos_app';
    case Rss = 'rss';

    public function label(): string
    {
        return match ($this) {
            self::Site => 'Официальный сайт khf.tj',
            self::SosApp => 'Мобильное приложение SOS',
            self::Rss => 'Лента для СМИ (RSS)',
        };
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $case): array => ['value' => $case->value, 'label' => $case->label()],
            self::cases(),
        );
    }
}
