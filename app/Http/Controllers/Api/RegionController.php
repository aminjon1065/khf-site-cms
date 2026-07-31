<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\PublicRegionResource;
use App\Models\Region;
use App\Services\AlertMapService;
use App\Services\PublicReadModelCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public region endpoints for the Next.js site: the live alert status per
 * region (risk map) and the regional-management directory (contacts page).
 */
class RegionController extends Controller
{
    public function __construct(
        private readonly AlertMapService $map,
        private readonly PublicReadModelCache $cache,
    ) {}

    public function index(): JsonResponse
    {
        $locale = app()->getLocale();
        $regions = $this->cache->remember(
            PublicReadModelCache::ALERTS,
            $locale,
            fn (): array => $this->map->regionStatuses($locale),
        );

        return response()->json(['data' => $regions]);
    }

    /**
     * Regional-management directory: office name, address and contacts.
     */
    public function directory(Request $request): JsonResponse
    {
        $locale = app()->getLocale();
        $perPage = min(max($request->integer('per_page', 20), 1), 50);
        $page = max($request->integer('page', 1), 1);

        $payload = $this->cache->remember(
            PublicReadModelCache::REGIONS,
            "{$locale}:directory:{$page}:{$perPage}",
            function () use ($locale, $page, $perPage): array {
                $regions = Region::query()
                    ->select([
                        'id',
                        'code',
                        'name',
                        'type',
                        'head',
                        'regional_center',
                        'address',
                        'phone',
                        'duty_phone',
                        'email',
                        'districts_count',
                    ])
                    ->with('districts:id,region_id,name')
                    ->orderBy('sort')
                    ->paginate($perPage, ['id'], 'page', $page)
                    ->appends(['locale' => $locale, 'per_page' => $perPage]);

                return PublicRegionResource::collection($regions)
                    ->response()
                    ->getData(true);
            },
        );

        return response()->json($payload);
    }
}
