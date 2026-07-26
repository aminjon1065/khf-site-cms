<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PublicSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

/**
 * Public site settings for the Next.js header/footer. Only whitelisted,
 * non-sensitive groups are exposed — never security / integrations / backup.
 */
class SettingController extends Controller
{
    /** D-2: complements the ETag layer (still recomputes the body to hash
     * it) by skipping the DB/service work entirely on a cache hit. Flushed
     * from Setting's FlushesPublicCache on every save/delete. */
    private const CACHE_TTL_SECONDS = 60;

    public function __construct(private readonly PublicSettingsService $settings) {}

    public function index(): JsonResponse
    {
        $locale = app()->getLocale();

        return response()->json(Cache::remember(
            "public-api:settings:{$locale}",
            self::CACHE_TTL_SECONDS,
            fn () => $this->settings->resolve($locale),
        ));
    }
}
