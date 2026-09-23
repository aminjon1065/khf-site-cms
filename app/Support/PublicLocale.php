<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Enforces the public locale contract: a material is visible only when its
 * public title exists in the requested locale. Public resources must resolve
 * translations with fallback disabled so fields from different languages are
 * never mixed in one response.
 */
final class PublicLocale
{
    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function available(Builder $query, string $field, ?string $locale = null): Builder
    {
        $locale ??= app()->getLocale();
        $column = "{$field}->{$locale}";

        return $query
            ->whereNotNull($column)
            ->where($column, '!=', '');
    }

    /**
     * The API locales a material is published in, by the same rule as
     * available(): its public title exists there. The site builds hreflang
     * and the «no translation» notice from this list.
     *
     * @return list<string>
     */
    public static function publishedIn(Model $material, string $field): array
    {
        if (! method_exists($material, 'getTranslation')) {
            return [];
        }

        return array_values(array_filter(
            ContentLocales::ALL,
            fn (string $locale): bool => trim((string) $material->getTranslation($field, $locale, false)) !== '',
        ));
    }
}
