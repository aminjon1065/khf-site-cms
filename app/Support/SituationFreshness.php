<?php

namespace App\Support;

/**
 * How old the situation on the public site may be before visitors are told it
 * may be out of date (audit A-2). The site shows the time the CMS last checked
 * the alerts; past this age it adds a warning and the advice to call 112. The
 * administrator picks the age in «Настройки сайта»; a day unless changed
 * (owner decision, 2026-09-24).
 */
class SituationFreshness
{
    public const DEFAULT_MINUTES = 1440;

    /**
     * The ages to choose from, in minutes.
     *
     * @var array<int, string>
     */
    public const CHOICES = [
        30 => '30 минут',
        60 => '1 час',
        180 => '3 часа',
        360 => '6 часов',
        720 => '12 часов',
        1440 => 'Сутки',
    ];

    /**
     * The stored choice, or the default for anything else.
     */
    public static function minutes(mixed $stored): int
    {
        $minutes = filter_var($stored, FILTER_VALIDATE_INT);

        return is_int($minutes) && array_key_exists($minutes, self::CHOICES)
            ? $minutes
            : self::DEFAULT_MINUTES;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map('strval', array_keys(self::CHOICES));
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::CHOICES as $minutes => $label) {
            $options[] = [
                'value' => (string) $minutes,
                'label' => $minutes === self::DEFAULT_MINUTES ? "{$label} (по умолчанию)" : $label,
            ];
        }

        return $options;
    }
}
