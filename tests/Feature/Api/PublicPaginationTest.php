<?php

use App\Models\Alert;
use Database\Seeders\RegionSeeder;

use function Pest\Laravel\seed;

it('paginates and clamps public catalogue endpoints', function (string $endpoint) {
    if ($endpoint === '/api/v1/regions/directory') {
        seed(RegionSeeder::class);
    }

    $this->getJson("{$endpoint}?per_page=999999")
        ->assertOk()
        ->assertJsonPath('meta.per_page', 50)
        ->assertJsonStructure(['data', 'meta', 'links']);
})->with([
    '/api/v1/documents',
    '/api/v1/instructions',
    '/api/v1/projects',
    '/api/v1/announcements',
    '/api/v1/pages',
    '/api/v1/categories',
    '/api/v1/regions/directory',
]);

/**
 * News and search paginate exactly like the catalogue above, but their
 * responses carry no `links` block (both build the collection from an array of
 * items rather than handing the paginator straight to the resource). They were
 * simply missing from the guard above — the clamp matters just as much here,
 * `/news` being the busiest list on the site.
 */
it('paginates and clamps the news feed and global search', function (string $endpoint) {
    $this->getJson("{$endpoint}per_page=999999")
        ->assertOk()
        ->assertJsonPath('meta.per_page', 50)
        ->assertJsonStructure(['data', 'meta']);
})->with([
    '/api/v1/news?',
    '/api/v1/search?q=%D0%BF%D0%BE&',
]);

/**
 * Alerts are deliberately the one public list without pagination: the map page
 * expands every active alert into per-region incidents and needs the complete
 * set, and an emergency feed that hides the rest behind "page 2" would be a
 * worse answer than a long page. That is a decision, not an oversight, so it is
 * asserted rather than left to be rediscovered.
 *
 * The cost was measured: +128 bytes gzip (3 288 bytes raw) of HTML per alert,
 * against an 80 KB document budget — the margin is several hundred alerts. If
 * that ever stops holding, this test is the place where the decision changes.
 */
it('serves active alerts unpaginated, on purpose', function () {
    Alert::factory()->count(3)->published()->create();

    $response = $this->getJson('/api/v1/alerts?locale=ru&per_page=1')
        ->assertOk()
        ->assertJsonStructure(['data']);

    expect($response->json())->not->toHaveKey('meta')
        ->and($response->json('data'))->toHaveCount(3);
});
