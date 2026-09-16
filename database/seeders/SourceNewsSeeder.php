<?php

namespace Database\Seeders;

use App\Enums\ContentStatus;
use App\Models\Category;
use App\Models\News;
use App\Models\User;
use App\Support\PublicLocale;
use Database\Seeders\Concerns\SeedsFromSource;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Replays the news fixtures produced by `php artisan khf:scrape-source` into
 * the CMS, so development runs against a few hundred real materials instead of
 * a handful of samples: deep pagination, working category filters, search that
 * returns more than one row, and covers on every card.
 *
 * kchs.tj and khf.tj are separate sites, not translations of each other, so an
 * article usually exists in one language only — which is exactly what the
 * public locale contract expects ({@see PublicLocale}). Where the
 * two sites published the same story, the shared cover image identifies the
 * pair and the two texts land on one record.
 */
class SourceNewsSeeder extends Seeder
{
    use SeedsFromSource;

    /**
     * Editors the imported material is attributed to, in rotation.
     */
    private const AUTHORS = [
        'd.sattorov@khf.tj',
        'z.nazarova@khf.tj',
        'a.usmonov@khf.tj',
        'm.rahimova@khf.tj',
        'sh.karimov@khf.tj',
    ];

    public function run(): void
    {
        $items = array_map(
            $this->toRecord(...),
            $this->pairSourceItems('news-ru.json', 'news-tg.json'),
        );

        if ($items === []) {
            Log::warning('No news fixtures under database/seeders/data/source — run `php artisan khf:scrape-source`.');

            return;
        }

        usort($items, static fn (array $a, array $b): int => strcmp($b['published_at'], $a['published_at']));

        /** @var array<int, int> $authorIds */
        $authorIds = array_values(User::query()
            ->whereIn('email', self::AUTHORS)
            ->orderBy('id')
            ->pluck('id')
            ->all());
        $categories = $this->categories();

        foreach ($items as $position => $item) {
            $this->seedItem($item, $position, $authorIds, $categories);
        }
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<int, int>  $authorIds
     * @param  array<string, int>  $categories
     */
    private function seedItem(array $item, int $position, array $authorIds, array $categories): void
    {
        /** @var array<string, string> $title */
        $title = $item['title'];
        /** @var array<string, string> $summary */
        $summary = $item['summary'];
        /** @var array<string, string> $body */
        $body = $item['body'];

        $status = $this->status($position);
        $publishedAt = Carbon::parse((string) $item['published_at']);

        $attributes = [
            'title' => $title,
            'summary' => $summary,
            'body' => $body,
            'category_id' => $categories[$this->classify($title)] ?? null,
            'status' => $status,
            'cover_alt' => $title['ru'] !== '' ? $title['ru'] : $title['tg'],
            'is_pinned' => $position < 2,
            'show_on_home' => $position < 10,
            'views_count' => (int) $item['views'],
            'seo' => $this->seo($title, $summary),
            'published_at' => $status === ContentStatus::Scheduled ? null : $publishedAt,
            'scheduled_at' => $status === ContentStatus::Scheduled ? now()->addDays(2) : null,
            'author_id' => $authorIds === [] ? null : $authorIds[$position % count($authorIds)],
        ];

        $news = $this->existing($title);

        if ($news instanceof News) {
            $news->update($attributes);
        } else {
            // Slug задаём здесь, а не полагаемся на хук `saving` модели:
            // `DatabaseSeeder` выполняется через `WithoutModelEvents`, и при
            // полном `db:seed` хук бы не сработал — материалы уехали бы в базу
            // без slug, то есть без публичного адреса.
            $source = $title['ru'] !== '' ? $title['ru'] : $title['tg'];
            $news = News::create($attributes + ['slug' => News::uniqueSlug($source)]);
        }

        $this->attachSourceImage($news, $item['cover'], 'cover');
    }

    /**
     * Looks the record up by its published title, the only stable identity a
     * scraped item has once it is in the CMS.
     *
     * @param  array<string, string>  $title
     */
    private function existing(array $title): ?News
    {
        $locale = $title['ru'] !== '' ? 'ru' : 'tg';

        return News::query()->where("title->{$locale}", $title[$locale])->first();
    }

    /**
     * Most material is published — the public site is the point. The three
     * newest items are held back in draft, review and scheduled state so the
     * editorial queues are not empty either.
     */
    private function status(int $position): ContentStatus
    {
        return match ($position) {
            2 => ContentStatus::Review,
            5 => ContentStatus::Draft,
            8 => ContentStatus::Scheduled,
            default => ContentStatus::Published,
        };
    }

    /**
     * @param  array<string, string>  $title
     * @param  array<string, string>  $summary
     * @return array<string, array{title: string, description: string}>
     */
    private function seo(array $title, array $summary): array
    {
        $seo = [];

        foreach (['ru', 'tg', 'en'] as $locale) {
            $seo[$locale] = [
                'title' => $title[$locale] ?? '',
                'description' => Str::limit($summary[$locale] ?? '', 155),
            ];
        }

        return $seo;
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
            'summary' => [
                'ru' => (string) ($russian['summary'] ?? ''),
                'tg' => (string) ($tajik['summary'] ?? ''),
                'en' => '',
            ],
            'body' => [
                'ru' => (string) ($russian['body_html'] ?? ''),
                'tg' => (string) ($tajik['body_html'] ?? ''),
                'en' => '',
            ],
            'cover' => is_string($primary['cover'] ?? null) ? $primary['cover'] : null,
            'views' => max((int) ($russian['views'] ?? 0), (int) ($tajik['views'] ?? 0)),
            'published_at' => (string) ($primary['published_at'] ?? ''),
        ];
    }

    /**
     * The source sites tag almost everything simply "Новости" / "Ахбори рӯз",
     * so the editorial category is derived from the headline instead.
     *
     * @param  array<string, string>  $title
     */
    private function classify(array $title): string
    {
        $haystack = Str::lower($title['ru'].' '.$title['tg']);

        // Ordered from the most specific headline shape to the least: the
        // first rule that matches wins.
        $rules = [
            ['Спасательные операции', ['спасл', 'спасен', 'спасател', 'утоплен', 'эвакуир', 'наҷотдиҳанда', 'наҷот дод', 'ғарқ', 'ҷасад']],
            ['Предупреждения', ['предупрежд', 'опасност', 'прогноз погод', 'штормовое', 'огоҳӣ', 'хатари', 'пешбинии обу ҳаво']],
            ['Происшествия', ['пожар', 'землетрясен', 'паводок', 'лавин', 'оползен', 'селев', 'сход сел', 'погиб', 'сводка о чрезвычайн', 'сӯхтор', 'заминларза', 'обхезӣ', 'тармафаро', 'ҳалокат']],
            ['Сотрудничество', ['сотрудничеств', 'делегац', 'меморандум', 'соглашен', 'визит', 'переговор', 'встреч', 'саммит', 'конференц', 'юнеско', 'оон', 'ҳамкор', 'вохӯр', 'созишнома', 'сафари корӣ', 'конфронс']],
            ['Обучение', ['обучен', 'тренинг', 'семинар', 'учебн', 'омӯзиш', 'машғулият', 'таълим', 'малака']],
            ['Техника', ['техник', 'оборудован', 'автомобил', 'вертолёт', 'вертолет', 'таҷҳизот']],
            ['Отчёты', ['итоги', 'отчёт', 'отчет', 'статистик', 'сводка', 'ҳисобот', 'ҷамъбаст', 'натиҷаи фаъолият']],
            ['Гражданская оборона', ['гражданск', 'учени', 'мудофиаи граждан', 'машқ']],
        ];

        foreach ($rules as [$category, $needles]) {
            foreach ($needles as $needle) {
                if (str_contains($haystack, $needle)) {
                    return $category;
                }
            }
        }

        return 'Новости';
    }

    /**
     * @return array<string, int>
     */
    private function categories(): array
    {
        return Category::query()
            ->where('type', 'news')
            ->get()
            ->mapWithKeys(static fn (Category $category): array => [
                $category->getTranslation('name', 'ru') => $category->id,
            ])
            ->all();
    }
}
