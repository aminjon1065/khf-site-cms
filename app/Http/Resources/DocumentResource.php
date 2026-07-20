<?php

namespace App\Http\Resources;

use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Admin list DTO for a document ("Документы"). Editorial fields only —
 * consumed by the CMS, never by the public site.
 *
 * @mixin Document
 */
class DocumentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->getTranslation('name', 'ru', false) ?: '— без названия —',
            'doc_type' => $this->doc_type->value,
            'doc_type_label' => $this->doc_type->label(),
            'number' => $this->number,
            'section' => $this->section,
            'status' => $this->status->value,
            'files' => $this->fileLanguages(),
            'has_file' => $this->hasAnyFile(),
            'author' => $this->whenLoaded('author', fn () => $this->author?->name),
            'doc_date' => $this->doc_date?->toDateString(),
            'published_at' => $this->published_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
