<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\PublicRegionResource;
use App\Models\Region;
use App\Services\AlertMapService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;

/**
 * Public region endpoints for the Next.js site: the live alert status per
 * region (risk map) and the regional-management directory (contacts page).
 */
class RegionController extends Controller
{
    /**
     * D-2: only the directory is cached, not index() — its data comes from
     * AlertMapService (live alert status), not the Region model, so a
     * Region save wouldn't be the right thing to flush it on. Flushed from
     * Region's FlushesPublicCache on every save/delete.
     */
    private const CACHE_TTL_SECONDS = 60;

    public function __construct(private readonly AlertMapService $map) {}

    public function index(): JsonResponse
    {
        return response()->json(['data' => $this->map->regionStatuses(app()->getLocale())]);
    }

    /**
     * Regional-management directory: office name, address and contacts. The
     * full (small, rarely-changing) directory is cached as resolved DTOs and
     * paginated in memory, since there are far too few regions to bother
     * caching per page/per_page combination.
     */
    public function directory(Request $request): JsonResponse
    {
        $locale = app()->getLocale();

        /** @var list<array<string, mixed>> $all */
        $all = Cache::remember(
            "public-api:regions-directory:{$locale}",
            self::CACHE_TTL_SECONDS,
            fn (): array => PublicRegionResource::collection(
                Region::query()->with('districts')->orderBy('sort')->get(),
            )->resolve(),
        );

        $perPage = min(max($request->integer('per_page', 20), 1), 50);
        $page = max($request->integer('page', 1), 1);

        $paginator = new LengthAwarePaginator(
            array_slice($all, ($page - 1) * $perPage, $perPage),
            count($all),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'links' => [
                'prev' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
            ],
        ]);
    }
}
