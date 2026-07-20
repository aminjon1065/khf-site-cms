<?php

namespace App\Http\Resources\Api;

use App\Models\Announcement;
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
            'kind' => $this->kind->value,
            'kind_label' => $this->kind->label(),
            'title' => $this->tr('title', $locale),
            'org' => $this->org,
            'desc' => $this->tr('body', $locale),
            'deadline' => $this->deadlineLabel($open),
            'open' => $open,
        ];
    }

    private function deadlineLabel(bool $open): string
    {
        if ($this->deadline === null) {
            return 'бессрочно';
        }

        $formatted = $this->deadline->format('d.m.Y');

        return ($open ? 'до ' : 'завершён ').$formatted;
    }

    private function tr(string $field, string $locale): string
    {
        $value = $this->getTranslation($field, $locale, false);

        return trim($value) !== '' ? $value : $this->getTranslation($field, 'ru', false);
    }
}
