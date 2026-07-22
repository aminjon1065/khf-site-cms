<?php

namespace App\Http\Resources\Api;

use App\Models\Alert;
use App\Support\PublicApiLabels;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public DTO for an emergency alert. `level` is the 5-point public scale mapped
 * from the CMS severity; the structured detail (instructions, official text,
 * affected regions, contacts) is attached on the detail view.
 *
 * @mixin Alert
 */
class PublicAlertResource extends JsonResource
{
    /**
     * The five fixed region codes — a country-wide alert affects all of them.
     *
     * @var list<string>
     */
    private const ALL_REGIONS = ['dushanbe', 'sughd', 'khatlon', 'gbao', 'rrp'];

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
        $level = $this->severity->level();
        $active = $this->isActive();

        $data = [
            'slug' => $this->slug,
            'level' => $level,
            'level_label' => PublicApiLabels::get('alert_level', $level, $locale),
            'severity' => $this->severity->value,
            'status' => PublicApiLabels::get('alert_status', $active ? 'active' : 'completed', $locale),
            'status_code' => $active ? 'active' : 'completed',
            'is_active' => $active,
            'hazard' => $this->hazard_type->value,
            'hazard_label' => PublicApiLabels::get('hazard', $this->hazard_type->value, $locale),
            'title' => $this->tr('title', $locale),
            'summary' => $this->tr('summary', $locale),
            'region' => $this->regionLabel($locale),
            'region_codes' => $this->regionCodes(),
            'datetime' => $this->published_at?->format('d.m.Y, H:i'),
            'starts_at' => $this->starts_at?->format('d.m.Y, H:i'),
            'ends_at' => $this->ends_at?->format('d.m.Y, H:i'),
            'published_at' => $this->published_at?->toIso8601String(),
            'starts_at_iso' => $this->starts_at?->toIso8601String(),
            'ends_at_iso' => $this->ends_at?->toIso8601String(),
        ];

        if ($this->withDetail) {
            $data['body'] = $this->tr('body', $locale);
            $data['instructions'] = $this->steps($locale);
            $data['contacts'] = $this->tr('contacts', $locale);
            $data['source'] = $this->source;
            $data['territory_type'] = $this->territory_type;
            $data['regions'] = $this->whenLoaded('regions', fn () => $this->regions
                ->map(fn ($r): array => ['code' => $r->code, 'name' => $r->getTranslation('name', $locale, false)])
                ->all(), []);
            $data['meta'] = $this->metaItems($locale, $active);
        }

        return $data;
    }

    /**
     * Region codes the alert covers (all regions for a country-wide alert),
     * used by the client map to fill affected regions.
     *
     * @return list<string>
     */
    private function regionCodes(): array
    {
        if ($this->territory_type === 'country') {
            return self::ALL_REGIONS;
        }

        if (! $this->relationLoaded('regions')) {
            return [];
        }

        return array_values(array_map(
            fn ($region): string => (string) $region->code,
            $this->regions->all(),
        ));
    }

    /**
     * The affected-territory label for the compact list view.
     */
    private function regionLabel(string $locale): string
    {
        if ($this->territory_type === 'country') {
            return PublicApiLabels::get('territory', 'country', $locale);
        }

        if (! $this->relationLoaded('regions')) {
            return $this->territory_note ?? '';
        }

        $names = $this->regions
            ->map(fn ($r): string => $r->getTranslation('name', $locale, false))
            ->filter(fn (string $name): bool => trim($name) !== '')
            ->all();

        return $names !== [] ? implode(', ', $names) : ($this->territory_note ?? '');
    }

    /**
     * Instruction steps, split from the translatable instructions text.
     *
     * @return list<string>
     */
    private function steps(string $locale): array
    {
        $text = $this->tr('instructions', $locale);

        if (trim($text) === '') {
            return [];
        }

        return array_values(array_filter(
            array_map('trim', preg_split('/\r?\n+/', $text) ?: []),
            fn (string $s): bool => $s !== '',
        ));
    }

    /**
     * @return list<array{label: string, value: string}>
     */
    private function metaItems(string $locale, bool $active): array
    {
        $items = [];

        if ($this->published_at) {
            $items[] = ['label' => PublicApiLabels::get('alert_meta', 'published', $locale), 'value' => $this->published_at->format('d.m.Y, H:i')];
        }
        if ($this->ends_at) {
            $items[] = ['label' => PublicApiLabels::get('alert_meta', $active ? 'active_until' : 'ended_at', $locale), 'value' => $this->ends_at->format('d.m.Y, H:i')];
        }
        $items[] = ['label' => PublicApiLabels::get('alert_meta', 'regions', $locale), 'value' => $this->regionLabel($locale)];
        if ($this->source) {
            $items[] = ['label' => PublicApiLabels::get('alert_meta', 'source', $locale), 'value' => $this->source];
        }

        return $items;
    }

    private function tr(string $field, string $locale): string
    {
        return (string) $this->getTranslation($field, $locale, false);
    }
}
