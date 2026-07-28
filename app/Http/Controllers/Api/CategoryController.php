<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Services\PublicReadModelCache;
use App\Support\PublicLocale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public content categories for the Next.js site (e.g. the news filter).
 * Filterable by `?type=` (defaults to `news`).
 */
class CategoryController extends Controller
{
    public function __construct(private readonly PublicReadModelCache $cache) {}

    public function index(Request $request): JsonResponse
    {
        $type = $request->query('type', 'news');
        $locale = app()->getLocale();
        $perPage = min(max($request->integer('per_page', 50), 1), 50);
        $page = max($request->integer('page', 1), 1);
        $categoryType = is_string($type) ? $type : 'news';

        $payload = $this->cache->remember(
            PublicReadModelCache::CATEGORIES,
            "{$locale}:{$categoryType}:{$page}:{$perPage}",
            function () use ($categoryType, $locale, $page, $perPage): array {
                $categories = Category::query()
                    ->when($categoryType !== '', fn ($query) => $query->where('type', $categoryType))
                    ->orderBy('sort');

                PublicLocale::available($categories, 'name', $locale);

                $categories = $categories
                    ->paginate($perPage, ['*'], 'page', $page)
                    ->appends([
                        'locale' => $locale,
                        'type' => $categoryType,
                        'per_page' => $perPage,
                    ]);

                return [
                    'data' => array_values(array_map(fn (Category $category): array => [
                        'slug' => $category->slug,
                        'name' => (string) $category->getTranslation('name', $locale, false),
                        'type' => $category->type,
                    ], $categories->items())),
                    'meta' => [
                        'current_page' => $categories->currentPage(),
                        'last_page' => $categories->lastPage(),
                        'per_page' => $categories->perPage(),
                        'total' => $categories->total(),
                    ],
                    'links' => [
                        'prev' => $categories->previousPageUrl(),
                        'next' => $categories->nextPageUrl(),
                    ],
                ];
            },
        );

        return response()->json($payload);
    }
}
