<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Support\PublicLocale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;

/**
 * Public content categories for the Next.js site (e.g. the news filter).
 * Filterable by `?type=` (defaults to `news`).
 */
class CategoryController extends Controller
{
    /**
     * D-2: the full (small, rarely-changing) list per type is cached and
     * paginated in memory — far too few categories per type to bother
     * caching per page/per_page combination. Flushed from Category's own
     * booted() hook (per-type, not just per-locale) on every save/delete.
     */
    private const CACHE_TTL_SECONDS = 60;

    public function index(Request $request): JsonResponse
    {
        $typeParam = $request->query('type', 'news');
        // Preserves the original behavior: an explicit empty ?type= means
        // "no filter", not "type=news" — Category::flushPublicCache() clears
        // this bucket too on every save/delete, not just the typed ones.
        $type = is_string($typeParam) && $typeParam !== '' ? $typeParam : null;
        $locale = app()->getLocale();

        /** @var list<array<string, mixed>> $all */
        $all = Cache::remember(
            'public-api:categories:'.($type ?? '_all').":{$locale}",
            self::CACHE_TTL_SECONDS,
            function () use ($type, $locale): array {
                $categories = Category::query()
                    ->when($type !== null, fn ($q) => $q->where('type', $type))
                    ->orderBy('sort');
                PublicLocale::available($categories, 'name', $locale);

                return $categories->get()->map(fn (Category $c): array => [
                    'slug' => $c->slug,
                    'name' => (string) $c->getTranslation('name', $locale, false),
                    'type' => $c->type,
                ])->values()->all();
            },
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
