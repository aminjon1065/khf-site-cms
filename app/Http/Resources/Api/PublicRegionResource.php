<?php

namespace App\Http\Resources\Api;

use App\Models\District;
use App\Models\Region;
use App\Support\PublicApiLabels;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public DTO for a regional management unit (the "Региональные управления"
 * directory on the Next.js site): office name, address and contacts, resolved
 * to the requested locale. Internal/editorial fields are never exposed.
 *
 * @mixin Region
 */
class PublicRegionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $locale = app()->getLocale();

        return [
            'code' => $this->code,
            'name' => $this->getTranslation('name', $locale, true),
            'type' => PublicApiLabels::get('region_type', $this->type->value, $locale),
            'type_code' => $this->type->value,
            'head' => $this->getTranslation('head', $locale, true),
            'regional_center' => $this->regional_center,
            'address' => $this->getTranslation('address', $locale, true),
            'phone' => $this->phone,
            'phone_href' => $this->phone !== null ? preg_replace('/[^+\d]/', '', $this->phone) : null,
            'duty_phone' => $this->duty_phone,
            'email' => $this->email,
            'districts_count' => $this->districts_count,
            'districts' => $this->districts->map(
                fn (District $d): string => $d->getTranslation('name', $locale, true)
            )->values()->all(),
        ];
    }
}
