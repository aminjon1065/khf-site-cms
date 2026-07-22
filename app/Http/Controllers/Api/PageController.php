<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\PublicPageResource;
use App\Models\Page;
use App\Support\PublicLocale;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public content pages for the Next.js site. `index` lists published pages
 * (slug + title, for static generation / navigation); `show` returns a single
 * page with its localized body.
 */
class PageController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $pages = Page::query()
            ->public()
            ->orderBy('sort')
            ->orderBy('id');

        PublicLocale::available($pages, 'title');

        $perPage = min(max($request->integer('per_page', 50), 1), 50);

        return PublicPageResource::collection($pages->paginate($perPage)->withQueryString());
    }

    public function show(string $slug): JsonResource
    {
        $query = Page::query()
            ->public()
            ->where('slug', $slug);

        PublicLocale::available($query, 'title');

        $page = $query->firstOrFail();

        return (new PublicPageResource($page))->withBody();
    }
}
