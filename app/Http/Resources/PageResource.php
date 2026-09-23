<?php

namespace App\Http\Resources;

use App\Models\Page;
use App\Support\ContentTitle;
use App\Support\PublicSite;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Admin index row for a site content page (editorial fields only).
 *
 * @mixin Page
 */
class PageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => ContentTitle::of($this->resource) ?: '— без заголовка —',
            'slug' => $this->slug,
            'public_path' => PublicSite::pagePath($this->slug),
            'public_url' => PublicSite::urlFor($this->resource),
            'is_system' => PublicSite::isSystemPage($this->slug),
            'status' => $this->status->value,
            'parent' => $this->whenLoaded('parent', fn () => $this->parent ? ContentTitle::of($this->parent) : null),
            'languages' => $this->languageCompleteness(),
            'author' => $this->whenLoaded('author', fn () => $this->author?->name),
            'published_at' => $this->published_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
