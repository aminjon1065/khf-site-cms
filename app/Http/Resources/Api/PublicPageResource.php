<?php

namespace App\Http\Resources\Api;

use App\Models\Page;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public DTO for a site content page. The list form exposes slug + title; the
 * detail form (withBody) adds the localized body. The body is plain text (the
 * frontend renders it as escaped paragraphs — no HTML is emitted).
 *
 * @mixin Page
 */
class PublicPageResource extends JsonResource
{
    public bool $withBody = false;

    public function withBody(bool $value = true): static
    {
        $this->withBody = $value;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $locale = app()->getLocale();

        $data = [
            'slug' => $this->slug,
            'title' => $this->tr('title', $locale),
        ];

        if ($this->withBody) {
            $data['body'] = $this->tr('body', $locale);
            $data['updated'] = $this->localizedDate($this->published_at ?? $this->updated_at, $locale);
        }

        return $data;
    }

    private function localizedDate(?CarbonInterface $date, string $locale): ?string
    {
        return $date?->locale($locale)->translatedFormat('j F Y');
    }

    private function tr(string $field, string $locale): string
    {
        $value = $this->getTranslation($field, $locale, false);

        if (trim($value) !== '') {
            return $value;
        }

        return $this->getTranslation($field, 'ru', false);
    }
}
