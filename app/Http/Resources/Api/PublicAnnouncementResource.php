<?php

namespace App\Http\Resources\Api;

use App\Models\Announcement;
use App\Support\PublicApiLabels;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public DTO for an announcement (vacancy/tender) on the Next.js /announcements
 * page. `open` (accepting applications) is derived from the deadline; the
 * deadline is emitted as a ready-to-render display string.
 *
 * @mixin Announcement
 */
class PublicAnnouncementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $locale = app()->getLocale();
        $open = $this->isOpen();

        return [
            'slug' => $this->slug,
            'kind' => $this->kind->value,
            'kind_label' => PublicApiLabels::get('announcement_kind', $this->kind->value, $locale),
            'title' => $this->tr('title', $locale),
            'org' => $this->org,
            'desc' => $this->tr('body', $locale),
            'deadline' => $this->deadlineLabel($open, $locale),
            'deadline_at' => $this->deadline?->toDateString(),
            'deadline_state' => $this->deadline === null ? 'unlimited' : ($open ? 'open' : 'closed'),
            'open' => $open,
            'application_url' => $this->application_url,
        ];
    }

    private function deadlineLabel(bool $open, string $locale): string
    {
        if ($this->deadline === null) {
            return PublicApiLabels::get('deadline', 'unlimited', $locale);
        }

        $formatted = $this->deadline->format('d.m.Y');

        $prefix = PublicApiLabels::get('deadline', $open ? 'until' : 'closed', $locale);

        return $prefix.' '.$formatted;
    }

    private function tr(string $field, string $locale): string
    {
        return (string) $this->getTranslation($field, $locale, false);
    }
}
