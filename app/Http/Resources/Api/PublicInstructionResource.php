<?php

namespace App\Http\Resources\Api;

use App\Models\Instruction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public DTO for a safety instruction (the /guides catalogue + detail on the
 * Next.js site). Locale-aware with a `ru` fallback; internal editorial fields
 * are never exposed. The full structured `sections` are attached on detail.
 *
 * @mixin Instruction
 */
class PublicInstructionResource extends JsonResource
{
    /**
     * @var list<string>
     */
    private const SECTIONS = ['before', 'during', 'after', 'prohibited'];

    public bool $withSections = false;

    public function withSections(bool $value = true): static
    {
        $this->withSections = $value;

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
            'title' => $this->tr('name', $locale),
            'summary' => $this->tr('summary', $locale),
            'hazard' => $this->hazard_type?->value,
            'hazard_label' => $this->hazard_type?->label(),
            'hazard_icon' => $this->hazard_type?->icon(),
            'priority' => (bool) $this->is_priority,
            'image' => $this->imageUrl(),
            'image_srcset' => $this->thumbnailSrcset('image'),
        ];

        if ($this->withSections) {
            $data['sections'] = $this->localizedSections($locale);
            $data['body'] = $this->tr('body', $locale);
        }

        return $data;
    }

    /**
     * The four sections resolved to the requested locale (falling back to `ru`
     * per section), as clean string lists.
     *
     * @return array<string, list<string>>
     */
    private function localizedSections(string $locale): array
    {
        $sections = is_array($this->sections) ? $this->sections : [];
        $result = [];

        foreach (self::SECTIONS as $key) {
            $steps = $sections[$key][$locale] ?? [];

            if (! is_array($steps) || $steps === []) {
                $steps = $sections[$key]['ru'] ?? [];
            }

            $result[$key] = array_values(array_filter(
                array_map(fn ($s): string => is_string($s) ? $s : '', is_array($steps) ? $steps : []),
                fn (string $s): bool => $s !== '',
            ));
        }

        return $result;
    }

    private function tr(string $field, string $locale): string
    {
        $value = $this->getTranslation($field, $locale, false);

        if (is_string($value) && trim($value) !== '') {
            return $value;
        }

        return (string) $this->getTranslation($field, 'ru', false);
    }

    private function imageUrl(): ?string
    {
        $url = $this->getFirstMediaUrl('image');

        return $url !== '' ? $url : null;
    }
}
