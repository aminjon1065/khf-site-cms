<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\PublicStructureUnitResource;
use App\Models\StructureUnit;
use App\Services\PublicReadModelCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Public structure-units endpoint for the Next.js site: the specialised
 * departments, in display order.
 */
class StructureUnitController extends Controller
{
    /**
     * Полный (небольшой и редко меняющийся) список подразделений кэшируется целиком и
     * постранично режется в памяти: вариантов page/per_page слишком мало,
     * чтобы кэшировать каждый. Кэш — общий `PublicReadModelCache`: те же
     * версионные ключи и та же инвалидация по наблюдателю, что у остальных
     * публичных read-моделей, вместо собственного TTL.
     */
    public function __construct(private readonly PublicReadModelCache $cache) {}

    public function index(Request $request): JsonResponse
    {
        $locale = app()->getLocale();

        /** @var list<array<string, mixed>> $all */
        $all = $this->cache->remember(
            PublicReadModelCache::STRUCTURE,
            $locale,
            fn (): array => PublicStructureUnitResource::collection(
                StructureUnit::query()
                    ->select(['id', 'num', 'name', 'desc'])
                    ->ordered()
                    ->get(),
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
