<?php

namespace App\Enums;

/**
 * Publication / dissemination channels for alerts.
 */
enum Channel: string
{
    case Site = 'site';
    case SosApp = 'sos_app';
    case Rss = 'rss';
    case Sms = 'sms';

    public function label(): string
    {
        return match ($this) {
            self::Site => 'Официальный сайт khf.tj',
            self::SosApp => 'Мобильное приложение SOS',
            self::Rss => 'Лента для СМИ (RSS)',
            self::Sms => 'SMS-шлюз оповещения',
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
