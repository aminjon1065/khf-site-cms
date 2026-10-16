<?php

use App\Models\Alert;
use App\Models\News;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    config(['services.frontend.rum_secret' => 'views-secret']);
});

/**
 * @param  list<array<string, mixed>>  $views
 */
function postViews(array $views, string $key = 'views-secret'): TestResponse
{
    return test()->withHeaders(['X-RUM-Key' => $key])->postJson('/api/v1/views', ['views' => $views]);
}

it('adds the site\'s views to today\'s sums per material and language', function () {
    $news = News::factory()->published()->create(['slug' => 'pavodok']);
    $alert = Alert::factory()->published()->create(['slug' => 'sel-v-khatlone']);

    postViews([
        ['type' => 'news', 'slug' => 'pavodok', 'locale' => 'tj', 'count' => 5],
        ['type' => 'news', 'slug' => 'pavodok', 'locale' => 'ru', 'count' => 2],
        ['type' => 'alert', 'slug' => 'sel-v-khatlone', 'locale' => 'tj', 'count' => 40],
    ])->assertAccepted()->assertJsonPath('recorded', 47);

    postViews([['type' => 'news', 'slug' => 'pavodok', 'locale' => 'tj', 'count' => 1]])->assertAccepted();

    $rows = DB::table('content_views')->orderBy('id')->get(['content_type', 'content_id', 'date', 'locale', 'views']);

    expect($rows->map(fn (object $r): array => (array) $r)->all())->toEqual([
        ['content_type' => 'news', 'content_id' => $news->id, 'date' => today()->toDateString(), 'locale' => 'tj', 'views' => 6],
        ['content_type' => 'news', 'content_id' => $news->id, 'date' => today()->toDateString(), 'locale' => 'ru', 'views' => 2],
        ['content_type' => 'alert', 'content_id' => $alert->id, 'date' => today()->toDateString(), 'locale' => 'tj', 'views' => 40],
    ]);
});

it('keeps the news total for the list without touching the editorial version', function () {
    $news = News::factory()->published()->create(['slug' => 'ucheniya', 'views_count' => 10]);
    $version = $news->updated_at?->toIso8601String();

    $this->travel(5)->minutes();
    postViews([['type' => 'news', 'slug' => 'ucheniya', 'locale' => 'ru', 'count' => 7]])->assertAccepted();

    $news->refresh();

    // updated_at is the version the editor's conflict check compares: a view
    // must not look like somebody edited the news.
    expect($news->views_count)->toBe(17)
        ->and($news->updated_at?->toIso8601String())->toBe($version);
});

it('drops views of unknown and unpublished addresses', function () {
    News::factory()->create(['slug' => 'chernovik']);

    postViews([
        ['type' => 'news', 'slug' => 'chernovik', 'locale' => 'ru', 'count' => 3],
        ['type' => 'page', 'slug' => 'net-takoy', 'locale' => 'ru', 'count' => 3],
    ])->assertAccepted()->assertJsonPath('recorded', 0);

    expect(DB::table('content_views')->count())->toBe(0);
});

it('takes views only from the site, with its key', function () {
    News::factory()->published()->create(['slug' => 'pavodok']);
    $views = [['type' => 'news', 'slug' => 'pavodok', 'locale' => 'ru', 'count' => 1000]];

    postViews($views, 'wrong')->assertForbidden();
    config(['services.frontend.rum_secret' => '']);
    postViews($views, '')->assertForbidden();

    expect(DB::table('content_views')->count())->toBe(0);
});

it('validates the batch', function () {
    postViews([['type' => 'document', 'slug' => 'x', 'locale' => 'tg', 'count' => 0]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['views.0.type', 'views.0.locale', 'views.0.count']);
});
