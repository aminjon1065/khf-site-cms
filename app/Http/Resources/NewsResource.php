<?php

namespace App\Http\Resources;

use App\Models\News;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Admin list DTO for a news item (the "Новости" table). Editorial fields only —
 * consumed by the CMS, never by the public site.
 *
 * @mixin News
 */
class NewsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->getTranslation('title', 'ru', false) ?: '— без заголовка —',
            'slug' => $this->slug,
            'status' => $this->status->value,
            'category' => $this->whenLoaded('category', fn () => $this->category?->getTranslation('name', 'ru')),
            'languages' => $this->languageCompleteness(),
            'is_pinned' => (bool) $this->is_pinned,
            'show_on_home' => (bool) $this->show_on_home,
            'views_count' => (int) $this->views_count,
            'author' => $this->whenLoaded('author', fn () => $this->author?->name),
            'cover' => $this->getFirstMediaUrl('cover') ?: null,
            'published_at' => $this->published_at?->toIso8601String(),
            'scheduled_at' => $this->scheduled_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
