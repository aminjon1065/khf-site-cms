<?php

namespace App\Enums;

/**
 * Natural / man-made hazard classification for alerts.
 */
enum HazardType: string
{
    case Mudflow = 'mudflow';
    case Earthquake = 'earthquake';
    case Flood = 'flood';
    case Avalanche = 'avalanche';
    case Fire = 'fire';
    case Wind = 'wind';
    case Heat = 'heat';
    case Frost = 'frost';
    case Landslide = 'landslide';
    case Storm = 'storm';

    public function label(): string
    {
        return match ($this) {
            self::Mudflow => 'Сель',
            self::Earthquake => 'Землетрясение',
            self::Flood => 'Наводнение',
            self::Avalanche => 'Лавина',
            self::Fire => 'Пожар',
            self::Wind => 'Сильный ветер',
            self::Heat => 'Жара',
            self::Frost => 'Мороз',
            self::Landslide => 'Оползень',
            self::Storm => 'Гроза',
        };
    }

    /**
     * Lucide icon name used on the client.
     */
    public function icon(): string
    {
        return match ($this) {
            self::Mudflow, self::Flood => 'waves',
            self::Earthquake => 'activity',
            self::Avalanche => 'mountain-snow',
            self::Fire => 'flame',
            self::Wind, self::Storm => 'wind',
            self::Heat => 'thermometer-sun',
            self::Frost => 'snowflake',
            self::Landslide => 'mountain',
        };
    }

    /**
     * @return array<int, array{value: string, label: string, icon: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $case): array => [
                'value' => $case->value,
                'label' => $case->label(),
                'icon' => $case->icon(),
            ],
            self::cases(),
        );
    }
}
