<?php

namespace App\Support;

use App\Models\Announcement;
use App\Models\Instruction;
use App\Models\News;
use App\Models\Page;
use App\Models\Project;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Enforces the public locale contract: a material appears on a language
 * version of the site only when that language has its public title and its
 * text (owner decision, 2026-09-23) — no page with a title over an empty
 * body. Alerts, documents and categories need only the title: a threat must
 * never be hidden for want of a translated text, and a document's content is
 * its file. Public resources must resolve translations with fallback disabled
 * so fields from different languages are never mixed in one response.
 */
final class PublicLocale
{
    /**
     * Instruction steps (Instruction::STEP_SECTIONS) count as its text.
     */
    private const STEPS = 'sections';

    /**
     * The text a language version needs, per type: any one of the fields.
     *
     * @var array<class-string<Model>, list<string>>
     */
    private const TEXT_FIELDS = [
        News::class => ['body'],
        Page::class => ['body'],
        Announcement::class => ['body'],
        Project::class => ['summary', 'body'],
        Instruction::class => ['body', self::STEPS],
    ];

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

        $query
            ->whereNotNull($column)
            ->where($column, '!=', '');

        $textFields = self::TEXT_FIELDS[$query->getModel()::class] ?? [];

        if ($textFields !== []) {
            $query->where(function (Builder $text) use ($textFields, $locale): void {
                foreach ($textFields as $textField) {
                    if ($textField === self::STEPS) {
                        foreach (Instruction::STEP_SECTIONS as $section) {
                            $text->orWhereJsonLength("sections->{$section}->{$locale}", '>', 0);
                        }

                        continue;
                    }

                    $text->orWhere(fn (Builder $filled) => $filled
                        ->whereNotNull("{$textField}->{$locale}")
                        ->where("{$textField}->{$locale}", '!=', ''));
                }
            });
        }

        return $query;
    }

    /**
     * The API locales a material is published in, by the same rule as
     * available(). The site builds hreflang and the «no translation» notice
     * from this list.
     *
     * @return list<string>
     */
    public static function publishedIn(Model $material, string $field): array
    {
        return array_values(array_filter(
            ContentLocales::ALL,
            fn (string $locale): bool => self::isPublishedIn($material, $locale, $field),
        ));
    }

    /**
     * The first language the material appears in on the site, in the order
     * the CMS shows titles in: Russian, then Tajik, then English.
     */
    public static function firstPublishedLocale(Model $material): ?string
    {
        foreach (['ru', 'tg', 'en'] as $locale) {
            if (self::isPublishedIn($material, $locale)) {
                return $locale;
            }
        }

        return null;
    }

    /**
     * Whether one language version of the material appears on the site.
     */
    public static function isPublishedIn(Model $material, string $locale, ?string $field = null): bool
    {
        if (! method_exists($material, 'getTranslation')) {
            return false;
        }

        $field ??= ContentTitle::field($material);

        return self::filled($material->getTranslation($field, $locale, false))
            && self::hasText($material, $locale);
    }

    /**
     * Whether the language has the text the type needs (always true for the
     * types that need only a title).
     */
    public static function hasText(Model $material, string $locale): bool
    {
        $textFields = self::TEXT_FIELDS[$material::class] ?? [];

        if ($textFields === []) {
            return true;
        }

        foreach ($textFields as $textField) {
            if ($textField === self::STEPS) {
                if ($material instanceof Instruction && $material->hasStepsIn($locale)) {
                    return true;
                }

                continue;
            }

            if (method_exists($material, 'getTranslation')
                && self::filled($material->getTranslation($textField, $locale, false))) {
                return true;
            }
        }

        return false;
    }

    private static function filled(mixed $value): bool
    {
        return is_string($value) && trim($value) !== '';
    }
}
