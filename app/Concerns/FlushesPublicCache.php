<?php

namespace App\Concerns;

use Illuminate\Support\Facades\Cache;

/**
 * D-2: flushes the public-API response cache (Cache::remember, keyed by
 * locale) whenever a row of this model is saved or deleted. For these
 * models the cached response depends on every row at once (there's no
 * per-record locale to narrow the invalidation to), so a save/delete just
 * clears every locale variant.
 */
trait FlushesPublicCache
{
    protected static function bootFlushesPublicCache(): void
    {
        static::saved(fn () => self::flushPublicCache());
        static::deleted(fn () => self::flushPublicCache());
    }

    private static function flushPublicCache(): void
    {
        foreach (['tg', 'ru', 'en'] as $locale) {
            Cache::forget(static::publicCacheKey($locale));
        }
    }

    abstract protected static function publicCacheKey(string $locale): string;
}
