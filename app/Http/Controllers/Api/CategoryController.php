<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public content categories for the Next.js site (e.g. the news filter).
 * Filterable by `?type=` (defaults to `news`).
 */
class CategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $type = $request->query('type', 'news');
        $locale = app()->getLocale();

        $categories = Category::query()
            ->when(is_string($type) && $type !== '', fn ($q) => $q->where('type', $type))
            ->orderBy('sort')
            ->get();

        $data = array_values(array_map(fn (Category $c): array => [
            'slug' => $c->slug,
            'name' => (string) $c->getTranslation('name', $locale, true),
            'type' => $c->type,
        ], $categories->all()));

        return response()->json(['data' => $data]);
    }
}
