<?php

namespace App\Support;

/**
 * Languages of editorial content. Materials are written in Tajik and Russian;
 * English is optional (owner decision, 2026-09-23): a missing English version
 * is neither a warning, nor a translation task, nor incompleteness.
 */
final class ContentLocales
{
    /**
     * @var list<string>
     */
    public const ALL = ['tg', 'ru', 'en'];

    /**
     * @var list<string>
     */
    public const REQUIRED = ['tg', 'ru'];

    public static function isRequired(string $locale): bool
    {
        return in_array($locale, self::REQUIRED, true);
    }

    /**
     * Required languages that are not filled completely.
     *
     * @param  array<string, int>  $completeness
     * @return list<string>
     */
    public static function missingRequired(array $completeness): array
    {
        return array_values(array_filter(
            self::REQUIRED,
            fn (string $locale): bool => (int) ($completeness[$locale] ?? 0) < 100,
        ));
    }
}
