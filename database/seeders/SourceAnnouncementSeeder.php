<?php

namespace Database\Seeders;

use App\Enums\AnnouncementKind;
use App\Enums\ContentStatus;
use App\Models\Announcement;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\Concerns\SeedsFromSource;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Replays the announcement fixtures from `php artisan khf:scrape-source`: the
 * Committee's real vacancy competitions and procurement notices, so the public
 * announcements page has both kinds, a working kind filter and enough rows to
 * page through.
 *
 * Three fields are derived rather than scraped, because the source has no
 * structured equivalent: the kind (vacancy vs tender) is read from the
 * headline, which follows very consistent wording on both sites; the closing
 * date is stated in prose inside the notice, so `deadline` is set relative to
 * publication; and `application_url` gets the Committee's HR mailbox, so the
 * public "apply" action has somewhere to point.
 */
class SourceAnnouncementSeeder extends Seeder
{
    use SeedsFromSource;

    /**
     * How long a notice stays open, counted from its publication date.
     */
    private const OPEN_FOR_DAYS = 21;

    public function run(): void
    {
        $records = array_map(
            $this->toRecord(...),
            $this->pairSourceItems('announcements-ru.json', 'announcements-tg.json'),
        );

        if ($records === []) {
            Log::warning('No announcement fixtures under database/seeders/data/source — run `php artisan khf:scrape-source`.');

            return;
        }

        usort($records, static fn (array $a, array $b): int => strcmp($b['published_at'], $a['published_at']));

        $authorId = User::query()->where('email', 'f.nazarov@khf.tj')->value('id');
        $projects = $this->projectsByKeyword();

        foreach ($records as $position => $record) {
            $this->seedAnnouncement($record, $position, is_int($authorId) ? $authorId : null, $projects);
        }
    }

    /**
     * @param  array<string, mixed>  $record
     * @param  array<string, int>  $projects
     */
    private function seedAnnouncement(array $record, int $position, ?int $authorId, array $projects): void
    {
        /** @var array<string, string> $title */
        $title = $record['title'];
        /** @var array<string, string> $body */
        $body = $record['body'];

        $locale = $title['ru'] !== '' ? 'ru' : 'tg';
        $publishedAt = Carbon::parse((string) $record['published_at']);

        $announcement = Announcement::query()->where("title->{$locale}", $title[$locale])->first()
            ?? new Announcement;

        $announcement->fill([
            'title' => $title,
            'body' => $body,
            'kind' => $this->classifyKind($title),
            'org' => $this->organisation($title, $body),
            'project_id' => $this->projectFor($body, $projects),
            'deadline' => $publishedAt->copy()->addDays(self::OPEN_FOR_DAYS),
            'application_url' => 'mailto:kadr@khf.tj',
            'slug' => $announcement->slug ?? Announcement::uniqueSlug($title[$locale], $announcement->getKey()),
            'status' => $position === 3 ? ContentStatus::Draft : ContentStatus::Published,
            'published_at' => $position === 3 ? null : $publishedAt,
            'author_id' => $authorId,
        ])->save();
    }

    /**
     * Flattens one ru/tg pair into the record shape this seeder writes.
     *
     * @param  array{ru: array<string, mixed>|null, tg: array<string, mixed>|null}  $pair
     * @return array<string, mixed>
     */
    private function toRecord(array $pair): array
    {
        $russian = $pair['ru'];
        $tajik = $pair['tg'];
        $primary = $russian ?? $tajik ?? [];

        return [
            'title' => [
                'ru' => (string) ($russian['title'] ?? ''),
                'tg' => (string) ($tajik['title'] ?? ''),
                'en' => '',
            ],
            'body' => [
                'ru' => (string) ($russian['body_html'] ?? ''),
                'tg' => (string) ($tajik['body_html'] ?? ''),
                'en' => '',
            ],
            'published_at' => (string) ($primary['published_at'] ?? ''),
        ];
    }

    /**
     * Procurement notices on both sites open with an unmistakable formula —
     * a request for expressions of interest, for price quotations, or for
     * consulting services. Everything else is a staffing competition.
     *
     * @param  array<string, string>  $title
     */
    private function classifyKind(array $title): AnnouncementKind
    {
        $haystack = Str::lower($title['ru'].' '.$title['tg']);

        $tenderMarkers = [
            'запрос', 'ценов', 'консультацион', 'консалтинг', 'заинтересованност',
            'отбор фирм', 'закупк', 'тендер', 'поставк',
            'машварат', 'интихоби ширкат', 'хариди', 'дархост',
        ];

        foreach ($tenderMarkers as $marker) {
            if (str_contains($haystack, $marker)) {
                return AnnouncementKind::Tender;
            }
        }

        return AnnouncementKind::Vacancy;
    }

    /**
     * @param  array<string, string>  $title
     * @param  array<string, string>  $body
     */
    private function organisation(array $title, array $body): string
    {
        $haystack = Str::lower($title['ru'].' '.$body['ru'].' '.$title['tg'].' '.$body['tg']);

        return match (true) {
            str_contains($haystack, 'готовности и устойчивости') || str_contains($haystack, 'd9780') => 'Группа реализации проекта «Повышение готовности и устойчивости к бедствиям в Таджикистане»',
            str_contains($haystack, 'абр') || str_contains($haystack, 'азиатск') => 'Группа реализации проекта управления риском стихийных бедствий (АБР)',
            str_contains($haystack, 'академи') || str_contains($haystack, 'академия') => 'Управление кадров и по работе с личным составом',
            default => 'Комитет по чрезвычайным ситуациям и гражданской обороне',
        };
    }

    /**
     * Links a procurement notice to the programme that funds it, where the
     * notice names one.
     *
     * @param  array<string, string>  $body
     * @param  array<string, int>  $projects
     */
    private function projectFor(array $body, array $projects): ?int
    {
        $haystack = Str::lower($body['ru'].' '.$body['tg']);

        foreach ($projects as $keyword => $projectId) {
            if (str_contains($haystack, $keyword)) {
                return $projectId;
            }
        }

        return null;
    }

    /**
     * @return array<string, int>
     */
    private function projectsByKeyword(): array
    {
        $keywords = [
            'готовности и устойчивости' => 'disaster-preparedness-resilience',
            'd9780' => 'disaster-preparedness-resilience',
            'управления рисками стихийных бедствий' => 'national-drm',
        ];

        /** @var array<string, int> $ids */
        $ids = Project::query()
            ->whereIn('slug', array_values($keywords))
            ->pluck('id', 'slug')
            ->all();

        $map = [];

        foreach ($keywords as $keyword => $slug) {
            if (isset($ids[$slug])) {
                $map[$keyword] = $ids[$slug];
            }
        }

        return $map;
    }
}
