<?php

namespace App\Http\Resources;

use App\Models\Alert;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Alert
 */
class AlertResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'internal_title' => $this->internal_title,
            'title' => $this->getTranslation('title', 'ru', false) ?: $this->internal_title,
            'summary' => $this->getTranslation('summary', 'ru', false),
            'hazard_type' => $this->hazard_type->value,
            'hazard_label' => $this->hazard_type->label(),
            'hazard_icon' => $this->hazard_type->icon(),
            'severity' => $this->severity->value,
            'status' => $this->status->value,
            'territory_type' => $this->territory_type,
            'regions' => $this->whenLoaded('regions', fn () => $this->regions->map(fn ($r) => [
                'id' => $r->id,
                'name' => $r->getTranslation('name', 'ru'),
                'code' => $r->code,
            ])->all(), []),
            'districts_count' => $this->whenLoaded('districts', fn () => $this->districts->count(), 0),
            'languages' => $this->languageCompleteness(),
            'channels' => $this->channels ?? [],
            'author' => $this->whenLoaded('author', fn () => $this->author?->name),
            'approver' => $this->whenLoaded('approver', fn () => $this->approver?->name),
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'scheduled_at' => $this->scheduled_at?->toIso8601String(),
            'published_at' => $this->published_at?->toIso8601String(),
            'is_active' => $this->isActive(),
        ];
    }
}
