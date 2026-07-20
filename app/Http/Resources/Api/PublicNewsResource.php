<?php

namespace App\Http\Resources\Api;

use App\Models\News;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public DTO for a news item. Emits exactly the shape the Next.js site expects
 * (`lib/types.ts` → `NewsItem`), pre-formatted for display so the frontend does
 * no locale/date work. Internal fields (author, workflow status, internal
 * title, translation completeness) are never exposed.
 *
 * @mixin News
 */
class PublicNewsResource extends JsonResource
{
    /**
     * When true, the full body and view counter are included (detail view).
     */
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
            'excerpt' => $this->tr('summary', $locale),
            'category' => $this->category
                ? $this->category->getTranslation('name', $locale, true)
                : null,
            'date' => $this->localizedDate($this->published_at, $locale),
            'datetime' => $this->published_at?->toIso8601String(),
            'image' => $this->coverUrl(),
            'featured' => (bool) $this->is_pinned,
        ];

        if ($this->withBody) {
            $data['body'] = $this->tr('body', $locale);
            $data['views'] = (int) $this->views_count;
        }

        return $data;
    }

    /**
     * A locale-aware display date, e.g. "16 июля 2026".
     */
    private function localizedDate(?CarbonInterface $date, string $locale): ?string
    {
        return $date?->locale($locale)->translatedFormat('j F Y');
    }

    /**
     * Translation in the requested locale, falling back to the canonical `ru`.
     */
    private function tr(string $field, string $locale): string
    {
        $value = $this->getTranslation($field, $locale, false);

        if (trim($value) !== '') {
            return $value;
        }

        return $this->getTranslation($field, 'ru', false);
    }

    private function coverUrl(): ?string
    {
        $url = $this->getFirstMediaUrl('cover');

        return $url !== '' ? $url : null;
    }
}
