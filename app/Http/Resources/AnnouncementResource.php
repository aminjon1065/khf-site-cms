<?php

namespace App\Http\Resources;

use App\Models\Announcement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Admin list DTO for an announcement ("Объявления"). Editorial fields only —
 * consumed by the CMS, never by the public site.
 *
 * @mixin Announcement
 */
class AnnouncementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->getTranslation('title', 'ru', false) ?: '— без заголовка —',
            'kind' => $this->kind->value,
            'kind_label' => $this->kind->label(),
            'org' => $this->org,
            'status' => $this->status->value,
            'is_open' => $this->isOpen(),
            'languages' => $this->languageCompleteness(),
            'author' => $this->whenLoaded('author', fn () => $this->author?->name),
            'deadline' => $this->deadline?->toDateString(),
            'published_at' => $this->published_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
