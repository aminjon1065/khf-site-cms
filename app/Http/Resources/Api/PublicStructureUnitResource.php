<?php

namespace App\Http\Resources\Api;

use App\Models\StructureUnit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public DTO for a structure unit (the "Structure" page on the Next.js site),
 * resolved to the requested locale, with its subunits nested in `children`.
 * The tree is assembled by StructureTree; children are resolved to plain
 * arrays here because the whole response is cached.
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
            'children' => $this->relationLoaded('children')
                ? self::collection($this->children)->resolve($request)
                : [],
        ];
    }
}
