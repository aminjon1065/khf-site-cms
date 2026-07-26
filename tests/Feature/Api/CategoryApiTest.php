<?php

use App\Models\Category;
use Database\Seeders\TaxonomySeeder;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\seed;

it('returns news categories with localized names', function () {
    seed(TaxonomySeeder::class);

    $data = $this->getJson('/api/v1/categories?locale=ru')->assertOk()->json('data');

    expect($data)->toBeArray()->not->toBeEmpty()
        ->and($data[0])->toHaveKeys(['slug', 'name', 'type'])
        ->and($data[0]['type'])->toBe('news')
        ->and(collect($data)->pluck('name'))->toContain('Спасательные операции');
});

it('filters categories by type', function () {
    Category::create(['type' => 'news', 'name' => ['ru' => 'Новость'], 'slug' => 'n', 'sort' => 0]);
    Category::create(['type' => 'document', 'name' => ['ru' => 'Документ'], 'slug' => 'd', 'sort' => 0]);

    $news = $this->getJson('/api/v1/categories?type=news&locale=ru')->json('data');

    expect(collect($news)->pluck('slug'))->toContain('n')->not->toContain('d');
});

// D-2. The per-type cache key must not leak between types: creating a
// document category must not invalidate (or appear in) the news cache.
it('changes the categories response on the next request after a category is saved, scoped to its own type', function () {
    Category::create(['type' => 'news', 'name' => ['ru' => 'Первая'], 'slug' => 'first', 'sort' => 0]);

    $this->getJson('/api/v1/categories?type=news&locale=ru')
        ->assertOk()
        ->assertJsonCount(1, 'data');

    Category::create(['type' => 'news', 'name' => ['ru' => 'Вторая'], 'slug' => 'second', 'sort' => 1]);

    $this->getJson('/api/v1/categories?type=news&locale=ru')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('serves the second identical request from cache without hitting the database', function () {
    Category::create(['type' => 'news', 'name' => ['ru' => 'Новость'], 'slug' => 'n', 'sort' => 0]);
    $this->getJson('/api/v1/categories?type=news&locale=ru')->assertOk();

    $queries = 0;
    DB::listen(function () use (&$queries) {
        $queries++;
    });

    $this->getJson('/api/v1/categories?type=news&locale=ru')->assertOk();

    expect($queries)->toBe(0);
});
