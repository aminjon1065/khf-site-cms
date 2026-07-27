<?php

namespace App\Http\Resources\Api;

use App\Models\StructureUnit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public DTO for a structure-unit entry (the "Structure" page on the
 * Next.js site), resolved to the requested locale.
 *
 * @mixin StructureUnit
 */
class PublicStructureUnitResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $locale = app()->getLocale();

        return [
            'num' => $this->num,
            'name' => $this->getTranslation('name', $locale, true),
            'desc' => $this->getTranslation('desc', $locale, true),
        ];
    }
}
