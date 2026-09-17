<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;

/**
 * Title of a content item as the CMS displays it. A material may be written in
 * a single language, so the admin shows the first filled translation — Russian,
 * then Tajik, then English — instead of assuming a Russian one.
 *
 * Public resources must not use this: they resolve strictly per requested
 * locale and hide a material that has no title there (PublicLocale).
 */
final class ContentTitle
{
    /**
     * @var list<string>
     */
    private const LOCALES = ['ru', 'tg', 'en'];

    public static function of(Model $model): string
    {
        $locale = self::firstLocale($model);

        return $locale === null ? '' : self::in($model, $locale);
    }

    /**
     * The first language the material has a title in, if any.
     */
    public static function firstLocale(Model $model): ?string
    {
        foreach (self::LOCALES as $locale) {
            if (self::in($model, $locale) !== '') {
                return $locale;
            }
        }

        return null;
    }

    /**
     * The title in exactly this locale, without falling back to another one.
     */
    public static function in(Model $model, string $locale): string
    {
        $field = self::field($model);

        if (! method_exists($model, 'getTranslations')
            || ! method_exists($model, 'isTranslatableAttribute')
            || ! $model->isTranslatableAttribute($field)) {
            return '';
        }

        $title = $model->getTranslations($field)[$locale] ?? '';

        return is_string($title) ? trim($title) : '';
    }

    /**
     * Instructions and documents are named, every other material is titled.
     */
    public static function field(Model $model): string
    {
        return method_exists($model, 'isTranslatableAttribute') && $model->isTranslatableAttribute('title')
            ? 'title'
            : 'name';
    }
}
