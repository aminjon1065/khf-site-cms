<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Public addresses of materials. The site turns a slug into a file name when
 * it builds and into cache tags, so it has to stay short: 180 characters is
 * the limit agreed with khf-site-front (MAX_CMS_SLUG_LENGTH in
 * lib/cache-tags.ts). resources/js/lib/slugify.ts cuts the same way.
 */
final class Slug
{
    public const MAX_LENGTH = 180;

    /**
     * A slug from a title: transliterated and cut to the limit.
     */
    public static function fromTitle(string $title): string
    {
        return self::limit(Str::slug($title, '-', 'ru'));
    }

    /**
     * The first free address — base, base-2, base-3… — each within the limit.
     *
     * @param  callable(string): bool  $taken
     */
    public static function unique(string $base, callable $taken): string
    {
        $slug = self::limit($base);

        for ($suffix = 2; $taken($slug); $suffix++) {
            $tail = '-'.$suffix;
            $slug = self::limit($base, self::MAX_LENGTH - strlen($tail)).$tail;
        }

        return $slug;
    }

    /**
     * Cut to $max characters between words; a single overlong word is cut
     * where the limit falls.
     */
    public static function limit(string $slug, int $max = self::MAX_LENGTH): string
    {
        if (mb_strlen($slug) <= $max) {
            return $slug;
        }

        $cut = mb_substr($slug, 0, $max);

        if (mb_substr($slug, $max, 1) !== '-') {
            $boundary = mb_strrpos($cut, '-');

            if ($boundary !== false && $boundary >= intdiv($max, 2)) {
                $cut = mb_substr($cut, 0, $boundary);
            }
        }

        return rtrim($cut, '-');
    }
}
