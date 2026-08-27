<?php

namespace App\Concerns;

/**
 * Computes per-locale (tg/ru/en) completeness for translatable content models.
 * Requires the model to use Spatie's HasTranslations.
 *
 * Полнота считается по обязательным полям, а не по всем переводимым. Разница
 * не косметическая: 100% полноты — условие публикации (см.
 * PublicationChecklist), поэтому включение сюда необязательного поля молча
 * запретило бы публиковать материал, пока это поле не заполнят на всех
 * языках. Модель перечисляет такие поля в `$completenessOptional`.
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
     * @return array<string, int>
     */
    public function languageCompleteness(): array
    {
        $optional = $this->completenessOptionalFields();
        /** @var list<string> $fields */
        $fields = array_values(array_diff($this->getTranslatableAttributes(), $optional));
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
