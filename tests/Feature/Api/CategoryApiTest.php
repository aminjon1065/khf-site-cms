<?php

use App\Models\Category;
use Database\Seeders\TaxonomySeeder;

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
