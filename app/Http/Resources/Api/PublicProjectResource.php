<?php

namespace App\Http\Resources\Api;

use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public DTO for a project (the /projects list + detail on the Next.js site).
 * `status` is the human-readable lifecycle label the frontend renders directly.
 * Structured goals/timeline/direction are attached on detail.
 *
 * @mixin Project
 */
class PublicProjectResource extends JsonResource
{
    public bool $withDetail = false;

    public function withDetail(bool $value = true): static
    {
        $this->withDetail = $value;

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
            'status' => $this->lifecycle_status->label(),
            'status_tone' => $this->lifecycle_status->tone(),
            'years' => $this->years,
            'partner' => $this->partner,
            'budget' => $this->budget,
            'desc' => $this->tr('summary', $locale),
            'image' => $this->coverUrl(),
        ];

        if ($this->withDetail) {
            $data['code'] = $this->code;
            $data['customer'] = $this->customer;
            $data['body'] = $this->tr('body', $locale);
            $data['goals'] = $this->localizedGoals($locale);
            $data['timeline'] = is_array($this->timeline) ? array_values($this->timeline) : [];
            $data['direction'] = $this->direction ?? ['address' => '', 'phone' => '', 'email' => ''];
        }

        return $data;
    }

    /**
     * Goals resolved to the requested locale, falling back to `ru`.
     *
     * @return list<string>
     */
    private function localizedGoals(string $locale): array
    {
        $goals = is_array($this->goals) ? $this->goals : [];
        $items = $goals[$locale] ?? [];

        if (! is_array($items) || $items === []) {
            $items = $goals['ru'] ?? [];
        }

        return array_values(array_filter(
            array_map(fn ($s): string => is_string($s) ? $s : '', is_array($items) ? $items : []),
            fn (string $s): bool => $s !== '',
        ));
    }

    private function tr(string $field, string $locale): string
    {
        $value = $this->getTranslation($field, $locale, false);

        return trim($value) !== '' ? $value : $this->getTranslation($field, 'ru', false);
    }

    private function coverUrl(): ?string
    {
        $url = $this->getFirstMediaUrl('cover');

        return $url !== '' ? $url : null;
    }
}
