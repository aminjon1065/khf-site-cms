<?php

use App\Models\Alert;
use App\Models\Announcement;
use App\Models\Instruction;
use App\Models\News;
use App\Models\Page;
use App\Models\Project;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The slug endpoint exists so that static generation and the sitemap stop
 * downloading full DTOs to read one field. Two properties must hold: it must
 * show exactly the rows the matching list endpoint shows (otherwise the set of
 * pre-rendered pages silently drifts), and it must stay a single column
 * (otherwise the saving quietly evaporates).
 */
beforeEach(function (): void {
    Cache::flush();
});

/**
 * @return array{0: string, 1: string}
 */
function khfSlugFixture(string $type): array
{
    return match ($type) {
        'news' => [News::factory()->published()->create()->slug, '/api/v1/news'],
        'projects' => [Project::factory()->published()->create()->slug, '/api/v1/projects'],
        'announcements' => [Announcement::factory()->published()->create()->slug, '/api/v1/announcements'],
        'instructions' => [Instruction::factory()->published()->create()->slug, '/api/v1/instructions'],
        'pages' => [Page::factory()->published()->create()->slug, '/api/v1/pages'],
        'alerts' => [Alert::factory()->published()->create()->slug, '/api/v1/alerts'],
    };
}

it('serves the same rows as the matching list endpoint', function (string $type) {
    [$slug, $listEndpoint] = khfSlugFixture($type);

    $listed = collect($this->getJson("{$listEndpoint}?locale=ru&per_page=50")->assertOk()->json('data'))
        ->pluck('slug')
        ->filter()
        ->sort()
        ->values()
        ->all();

    $response = $this->getJson("/api/v1/slugs/{$type}?locale=ru")->assertOk();

    expect($response->json('data'))->toBe($listed)
        ->and($response->json('meta.total'))->toBe(count($listed))
        ->and($listed)->toContain($slug);
})->with(['news', 'projects', 'announcements', 'instructions', 'pages', 'alerts']);

it('hides materials that have no title in the requested locale', function () {
    $translated = News::factory()->published()->create([
        'title' => ['ru' => 'Есть перевод', 'en' => 'Translated'],
    ]);
    $russianOnly = News::factory()->published()->create([
        'title' => ['ru' => 'Только по-русски'],
    ]);

    $ru = $this->getJson('/api/v1/slugs/news?locale=ru')->assertOk()->json('data');
    $en = $this->getJson('/api/v1/slugs/news?locale=en')->assertOk()->json('data');

    expect($ru)->toContain($translated->slug)
        ->and($ru)->toContain($russianOnly->slug)
        ->and($en)->toContain($translated->slug)
        ->and($en)->not->toContain($russianOnly->slug);
});

it('excludes materials that are not publicly visible', function () {
    $published = News::factory()->published()->create();
    $draft = News::factory()->create();

    $slugs = $this->getJson('/api/v1/slugs/news?locale=ru')->assertOk()->json('data');

    expect($slugs)->toContain($published->slug)
        ->and($slugs)->not->toContain($draft->slug);
});

it('returns bare slug strings and nothing else', function () {
    News::factory()->published()->create();

    $data = $this->getJson('/api/v1/slugs/news?locale=ru')->assertOk()->json('data');

    expect($data)->not->toBeEmpty()
        ->and($data)->each->toBeString();
});

it('reads a single column and never selects every field', function () {
    News::factory()->published()->create();
    Cache::flush();

    $queries = [];
    DB::listen(function ($query) use (&$queries): void {
        $queries[] = strtolower($query->sql);
    });

    $this->getJson('/api/v1/slugs/news?locale=ru')->assertOk();

    $newsQueries = array_values(array_filter(
        array_map(fn (string $sql): string => str_replace(['"', '`'], '', $sql), $queries),
        fn (string $sql): bool => str_contains($sql, 'from news'),
    ));

    expect($newsQueries)->not->toBeEmpty()
        ->each->toContain('select slug');
});

it('costs a fraction of the list endpoint it replaces', function () {
    News::factory()->count(5)->published()->create();

    $list = strlen((string) $this->getJson('/api/v1/news?locale=ru&per_page=50')->assertOk()->getContent());
    $slugs = strlen((string) $this->getJson('/api/v1/slugs/news?locale=ru')->assertOk()->getContent());

    // The whole point of the endpoint. Deliberately loose (5×) so it fails on a
    // regression in kind — someone adding titles or dates "while we're here" —
    // rather than on fixture noise; the measured ratio on real content is ~14×.
    expect($slugs * 5)->toBeLessThan($list);
});

it('rejects an unknown content type', function () {
    $this->getJson('/api/v1/slugs/documents?locale=ru')->assertNotFound();
    $this->getJson('/api/v1/slugs/../news?locale=ru')->assertNotFound();
});

it('stops serving a stale list after content changes', function () {
    $first = News::factory()->published()->create();

    expect($this->getJson('/api/v1/slugs/news?locale=ru')->assertOk()->json('data'))
        ->toBe([$first->slug]);

    $second = News::factory()->published()->create();

    expect($this->getJson('/api/v1/slugs/news?locale=ru')->assertOk()->json('data'))
        ->toContain($second->slug);
});
