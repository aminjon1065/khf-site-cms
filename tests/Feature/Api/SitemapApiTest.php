<?php

use App\Enums\ContentStatus;
use App\Models\Alert;
use App\Models\Announcement;
use App\Models\Instruction;
use App\Models\News;
use App\Models\Page;
use App\Models\Project;
use App\Support\PublicAddresses;
use Illuminate\Support\Facades\Cache;

/**
 * sitemap.xml of the public site in one response: every material with an
 * address, the languages it is published in and when it last changed. The
 * date must be the one the page itself states — the last real edit of the
 * text, else the publication — or search engines stop trusting lastmod.
 */
beforeEach(function (): void {
    Cache::flush();
});

/**
 * @return array<string, mixed>|null
 */
function sitemapEntry(string $type, string $slug): ?array
{
    return collect(test()->getJson('/api/v1/sitemap')->assertOk()->json('data'))
        ->first(fn (array $entry): bool => $entry['type'] === $type && $entry['slug'] === $slug);
}

it('lists a material with its languages and the date it was published', function () {
    $news = News::factory()->published()->create([
        'published_at' => now()->subDays(3)->startOfSecond(),
        'title' => ['ru' => 'Учения', 'tg' => 'Машқҳо', 'en' => ''],
        'body' => ['ru' => '<p>Текст</p>', 'tg' => '<p>Матн</p>', 'en' => ''],
    ]);

    expect(sitemapEntry('news', $news->slug))->toBe([
        'type' => 'news',
        'slug' => $news->slug,
        'locales' => ['tg', 'ru'],
        'modified_at' => $news->published_at->toIso8601String(),
    ]);
});

it('dates a material by its last edit, not by its publication', function () {
    $page = Page::factory()->published()->create(['published_at' => now()->subWeek()]);
    $this->travel(2)->hours();

    $page->setTranslation('body', 'ru', '<p>Новый текст страницы.</p>');
    $page->save();

    expect(sitemapEntry('pages', $page->slug)['modified_at'])->toBe(now()->toIso8601String());
});

it('lists a language only where the title and the text are there', function () {
    $news = News::factory()->published()->create([
        'title' => ['ru' => 'Только по-русски', 'tg' => 'Сарлавҳа', 'en' => 'Title'],
        'body' => ['ru' => '<p>Текст</p>', 'tg' => '', 'en' => ''],
    ]);

    expect(sitemapEntry('news', $news->slug)['locales'])->toBe(['ru']);
});

it('shows exactly the materials and languages the slug listings show', function () {
    News::factory()->published()->create();
    News::factory()->published()->create(['title' => ['ru' => 'Только по-русски'], 'body' => ['ru' => '<p>Текст</p>']]);
    Project::factory()->published()->create();
    Announcement::factory()->published()->create();
    Instruction::factory()->published()->create();
    Page::factory()->published()->create();
    Alert::factory()->published()->create();

    $sitemap = collect($this->getJson('/api/v1/sitemap')->assertOk()->json('data'));

    foreach (PublicAddresses::TYPES as $type) {
        foreach (['tg', 'ru', 'en'] as $locale) {
            $listed = $this->getJson("/api/v1/slugs/{$type}?locale={$locale}")->assertOk()->json('data');
            $mapped = $sitemap
                ->filter(fn (array $entry): bool => $entry['type'] === $type && in_array($locale, $entry['locales'], true))
                ->pluck('slug')
                ->sort()
                ->values()
                ->all();

            expect($mapped)->toBe($listed, "{$type} / {$locale}");
        }
    }
});

it('leaves out drafts and alerts that are over', function () {
    $draft = News::factory()->create(['status' => ContentStatus::Draft]);
    $ended = Alert::factory()->published()->create(['starts_at' => now()->subDays(3), 'ends_at' => now()->subDay()]);
    $active = Alert::factory()->published()->create();

    $types = collect($this->getJson('/api/v1/sitemap')->assertOk()->json('data'))
        ->map(fn (array $entry): string => "{$entry['type']}:{$entry['slug']}");

    expect($types)->not->toContain("news:{$draft->slug}")
        ->and($types)->not->toContain("alerts:{$ended->slug}")
        ->and($types)->toContain("alerts:{$active->slug}");
});

it('stops serving a stale date after an edit', function () {
    $news = News::factory()->published()->create(['published_at' => now()->subDays(2)]);
    $published = sitemapEntry('news', $news->slug)['modified_at'];

    $this->travel(1)->hours();
    $news->setTranslation('title', 'ru', 'Учения завершены');
    $news->save();

    expect(sitemapEntry('news', $news->slug)['modified_at'])
        ->not->toBe($published)
        ->toBe(now()->toIso8601String());
});

it('counts what it lists', function () {
    News::factory()->count(2)->published()->create();

    $response = $this->getJson('/api/v1/sitemap')->assertOk();

    expect($response->json('meta.total'))->toBe(count($response->json('data')));
});
