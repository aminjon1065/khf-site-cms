<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\PublicPageResource;
use App\Models\Page;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public content pages for the Next.js site. `index` lists published pages
 * (slug + title, for static generation / navigation); `show` returns a single
 * page with its localized body.
 */
class PageController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $pages = Page::query()
            ->public()
            ->orderBy('sort')
            ->orderBy('id')
            ->get();

        return PublicPageResource::collection($pages);
    }

    public function show(string $slug): JsonResource
    {
        $page = Page::query()
            ->public()
            ->where('slug', $slug)
            ->firstOrFail();

        return (new PublicPageResource($page))->withBody();
    }
}
