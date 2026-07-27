<?php

namespace App\Http\Resources\Api;

use App\Models\Leader;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public DTO for a leadership-roster entry (the "Leadership" page on the
 * Next.js site), resolved to the requested locale. Internal fields (sort,
 * timestamps) are never exposed.
 *
 * @mixin Leader
 */
class PublicLeaderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $locale = app()->getLocale();

        return [
            'id' => $this->id,
            'role' => $this->getTranslation('role', $locale, true),
            'name' => $this->getTranslation('name', $locale, true),
            'meta' => $this->getTranslation('meta', $locale, true) ?: null,
            'bio' => $this->getTranslation('bio', $locale, true) ?: null,
            'is_chairman' => $this->is_chairman,
            'photo_url' => $this->getFirstMediaUrl('photo') ?: null,
        ];
    }
}
