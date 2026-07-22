<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

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
}
