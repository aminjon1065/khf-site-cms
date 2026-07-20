<?php

namespace App\Http\Controllers\Api;

use App\Enums\ContentStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\PublicNewsResource;
use App\Models\News;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public news feed for the Next.js site. Returns only publicly-visible items
 * ({@see ContentStatus::isPublic()} with a past publish date),
 * newest first, with category + full-text search and pagination.
 */
class NewsController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = min(max($request->integer('per_page', 12), 1), 50);

        $query = News::query()
            ->public()
            ->with('category', 'media')
            ->orderByDesc('is_pinned')
            ->orderByDesc('published_at');

        $this->applyFilters($query, $request);

        $news = $query->paginate($perPage)->withQueryString();

        return PublicNewsResource::collection($news->items())
            ->additional([
                'meta' => [
                    'total' => $news->total(),
                    'per_page' => $news->perPage(),
                    'current_page' => $news->currentPage(),
                    'last_page' => $news->lastPage(),
                ],
            ]);
    }

    public function show(string $slug): JsonResource
    {
        $news = News::query()
            ->public()
            ->with('category', 'media')
            ->where('slug', $slug)
            ->firstOrFail();

        return (new PublicNewsResource($news))->withBody();
    }

    /**
     * @param  Builder<News>  $query
     */
    private function applyFilters(Builder $query, Request $request): void
    {
        if ($category = $request->string('category')->toString()) {
            $query->whereHas('category', fn (Builder $q) => $q->where('slug', $category));
        }

        if ($search = $request->string('q')->toString()) {
            $query->where(function (Builder $q) use ($search): void {
                $q->where('title->ru', 'like', "%{$search}%")
                    ->orWhere('title->tg', 'like', "%{$search}%")
                    ->orWhere('title->en', 'like', "%{$search}%")
                    ->orWhere('summary->ru', 'like', "%{$search}%");
            });
        }
    }
}
