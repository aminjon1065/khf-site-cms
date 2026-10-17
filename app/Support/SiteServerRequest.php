<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * A request from the public site's own server (server-side rendering and
 * `next build`), recognised by the shared SITE_API_KEY in `X-Site-Key`.
 *
 * All of them come from one address, so the per-IP public API limit would
 * count every visitor's page render against a single budget: a crawler
 * walking the news archive, or a build prerendering thousands of pages, ran
 * into 429 and the site baked "data unavailable" into its cache. The key is
 * server-only — the browser never sees it.
 */
final class SiteServerRequest
{
    public const HEADER = 'X-Site-Key';

    public static function matches(Request $request): bool
    {
        $key = config('services.frontend.site_api_key');
        $presented = $request->header(self::HEADER);

        return is_string($key) && $key !== ''
            && is_string($presented) && hash_equals($key, $presented);
    }
}
