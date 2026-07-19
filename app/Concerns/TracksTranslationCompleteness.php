<?php

namespace App\Concerns;

/**
 * Computes per-locale (tg/ru/en) completeness for translatable content models.
 * Requires the model to use Spatie's HasTranslations; it scores every
 * translatable attribute.
 */
trait TracksTranslationCompleteness
{
    /**
     * @var list<string>
     */
    public const CONTENT_LOCALES = ['tg', 'ru', 'en'];

    /**
     * @return array<string, int>
     */
    public function languageCompleteness(): array
    {
        /** @var list<string> $fields */
        $fields = $this->getTranslatableAttributes();
        $result = [];

        foreach (self::CONTENT_LOCALES as $locale) {
            if ($fields === []) {
                $result[$locale] = 0;

                continue;
            }

            $filled = 0;

            foreach ($fields as $field) {
                if (trim((string) ($this->getTranslations($field)[$locale] ?? '')) !== '') {
                    $filled++;
                }
            }

            $result[$locale] = (int) round($filled / count($fields) * 100);
        }

        return $result;
    }
}
