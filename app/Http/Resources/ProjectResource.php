<?php

namespace App\Http\Resources;

use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Admin list DTO for a project ("Проекты и программы"). Editorial fields only —
 * consumed by the CMS, never by the public site.
 *
 * @mixin Project
 */
class ProjectResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->getTranslation('title', 'ru', false) ?: '— без названия —',
            'slug' => $this->slug,
            'status' => $this->status->value,
            'lifecycle_status' => $this->lifecycle_status->value,
            'lifecycle_label' => $this->lifecycle_status->label(),
            'lifecycle_tone' => $this->lifecycle_status->tone(),
            'years' => $this->years,
            'partner' => $this->partner,
            'budget' => $this->budget,
            'languages' => $this->languageCompleteness(),
            'author' => $this->whenLoaded('author', fn () => $this->author?->name),
            'published_at' => $this->published_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
