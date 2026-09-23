<?php

namespace App\Concerns;

/**
 * Computes per-locale (tg/ru/en) completeness for translatable content models.
 * Requires the model to use Spatie's HasTranslations.
 *
 * Полнота считается по обязательным полям, а не по всем переводимым. Разница
 * не косметическая: 100% полноты хотя бы на одном языке — условие публикации
 * (см. PublicationChecklist), поэтому включение сюда необязательного поля
 * молча запретило бы публиковать материал, пока это поле не заполнят.
 * Модель перечисляет такие поля в `$completenessOptional`.
 */
trait TracksTranslationCompleteness
{
    /**
     * @var list<string>
     */
    public const CONTENT_LOCALES = ['tg', 'ru', 'en'];

    /**
     * Переводимые поля, не влияющие на полноту. Модель переопределяет свойство
     * `$completenessOptional`.
     *
     * @return list<string>
     */
    private function completenessOptionalFields(): array
    {
        /** @var list<string> */
        return property_exists($this, 'completenessOptional')
            ? $this->completenessOptional
            : [];
    }

    /**
     * Обязательное содержание языка, которое хранится не в переводимой
     * колонке (шаги инструкции). Каждый элемент — одно обязательное «поле»:
     * true, если на этом языке оно заполнено.
     *
     * @return list<bool>
     */
    protected function completenessExtras(string $locale): array
    {
        return [];
    }

    /**
     * @return array<string, int>
     */
    public function languageCompleteness(): array
    {
        $optional = $this->completenessOptionalFields();
        /** @var list<string> $fields */
        $fields = array_values(array_diff($this->getTranslatableAttributes(), $optional));
        $result = [];

        foreach (self::CONTENT_LOCALES as $locale) {
            $extras = $this->completenessExtras($locale);
            $required = count($fields) + count($extras);

            if ($required === 0) {
                $result[$locale] = 0;

                continue;
            }

            $filled = count(array_filter($extras));

            foreach ($fields as $field) {
                if (trim((string) ($this->getTranslations($field)[$locale] ?? '')) !== '') {
                    $filled++;
                }
            }

            $result[$locale] = (int) round($filled / $required * 100);
        }

        return $result;
    }
}
