<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\PublicStructureUnitResource;
use App\Models\StructureUnit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;

/**
 * Public structure-units endpoint for the Next.js site: the specialised
 * departments, in display order.
 */
class StructureUnitController extends Controller
{
    /**
     * D-2: the full (small, rarely-changing) list is cached and paginated in
     * memory — far too few units to bother caching per page/per_page
     * combination. Flushed from StructureUnit's FlushesPublicCache on every
     * save/delete.
     */
    private const CACHE_TTL_SECONDS = 60;

    public function index(Request $request): JsonResponse
    {
        $locale = app()->getLocale();

        /** @var list<array<string, mixed>> $all */
        $all = Cache::remember(
            "public-api:structure:{$locale}",
            self::CACHE_TTL_SECONDS,
            fn (): array => PublicStructureUnitResource::collection(
                StructureUnit::query()->ordered()->get(),
            )->resolve(),
        );

        $perPage = min(max($request->integer('per_page', 50), 1), 50);
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
