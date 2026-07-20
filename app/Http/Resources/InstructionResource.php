<?php

namespace App\Http\Resources;

use App\Models\Instruction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Admin list DTO for an instruction ("Инструкции населению"). Editorial fields
 * only — consumed by the CMS, never by the public site.
 *
 * @mixin Instruction
 */
class InstructionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->getTranslation('name', 'ru', false) ?: '— без названия —',
            'slug' => $this->slug,
            'status' => $this->status->value,
            'hazard_type' => $this->hazard_type?->value,
            'hazard_label' => $this->hazard_type?->label(),
            'is_priority' => (bool) $this->is_priority,
            'sort' => (int) $this->sort,
            'languages' => $this->languageCompleteness(),
            'author' => $this->whenLoaded('author', fn () => $this->author?->name),
            'published_at' => $this->published_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
