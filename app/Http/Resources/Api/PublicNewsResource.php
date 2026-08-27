<?php

namespace App\Http\Resources\Api;

use App\Models\News;
use App\Support\PublicAttachments;
use App\Support\PublicImageData;
use App\Support\RichTextMediaResolver;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

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
                ? $this->category->getTranslation('name', $locale, false)
                : null,
            'date' => $this->localizedDate($this->published_at, $locale),
            'datetime' => $this->published_at?->toIso8601String(),
            'image' => $this->coverUrl(),
            'image_srcset' => $this->thumbnailSrcset('cover'),
            'image_data' => $this->imageData($locale),
            'featured' => (bool) $this->is_pinned,
        ];

        if ($this->withBody) {
            // Вложения — только на детальной: в списке блок «Материалы» не
            // выводится, и тянуть медиа на каждую строку незачем.
            $data['attachments'] = PublicAttachments::fromModel($this->resource, $locale);
            $data['body'] = app(RichTextMediaResolver::class)
                ->resolve($this->tr('body', $locale));
            $data['views'] = (int) $this->views_count;
            $data['seo'] = $this->localizedSeo($locale);
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

    private function tr(string $field, string $locale): string
    {
        return (string) $this->getTranslation($field, $locale, false);
    }

    /** @return array{title: string, description: string} */
    private function localizedSeo(string $locale): array
    {
        $seo = is_array($this->seo) ? $this->seo : [];
        $entry = is_array($seo[$locale] ?? null) ? $seo[$locale] : [];

        return [
            'title' => trim((string) ($entry['title'] ?? '')) ?: $this->tr('title', $locale),
            'description' => trim((string) ($entry['description'] ?? '')) ?: $this->tr('summary', $locale),
        ];
    }

    private function coverUrl(): ?string
    {
        $url = $this->getFirstMediaUrl('cover');

        return $url !== '' ? $url : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function imageData(string $locale): ?array
    {
        $media = $this->getFirstMedia('cover');

        if (! $media instanceof Media) {
            return null;
        }

        $alt = trim((string) $this->cover_alt);

        $caption = trim((string) $this->cover_caption);

        return PublicImageData::fromMedia(
            $media,
            $alt !== '' ? $alt : $this->tr('title', $locale),
            // Подпись — необязательна: без неё публичная часть просто не
            // рисует <figcaption>, а не подставляет придуманный текст.
            $caption !== '' ? $caption : null,
        );
    }
}
