<?php

use App\Enums\ContentStatus;
use App\Models\Category;
use App\Models\News;

it('returns only publicly visible news', function () {
    News::factory()->published()->create(['title' => ['ru' => 'Опубликовано', 'tg' => 'Нашр шуд', 'en' => '']]);
    News::factory()->create(['status' => ContentStatus::Draft]);
    News::factory()->create(['status' => ContentStatus::Review]);

    $response = $this->getJson('/api/v1/news');

    $response->assertOk()->assertJsonCount(1, 'data');
    expect($response->json('data.0.title'))->toBe('Опубликовано');
});

it('excludes news with a future publish date', function () {
    News::factory()->create([
        'status' => ContentStatus::Published,
        'published_at' => now()->addDay(),
    ]);

    $this->getJson('/api/v1/news')->assertOk()->assertJsonCount(0, 'data');
});

it('serves the requested locale and falls back to ru', function () {
    News::factory()->published()->create([
        'title' => ['ru' => 'Русский заголовок', 'tg' => 'Сарлавҳаи тоҷикӣ', 'en' => ''],
    ]);

    expect($this->getJson('/api/v1/news?locale=tg')->json('data.0.title'))->toBe('Сарлавҳаи тоҷикӣ');
    // en is empty → the resource falls back to the canonical ru value.
    expect($this->getJson('/api/v1/news?locale=en')->json('data.0.title'))->toBe('Русский заголовок');
});

it('returns a published item by slug with a body', function () {
    News::factory()->published()->create([
        'slug' => 'test-item',
        'body' => ['ru' => 'Полный текст новости.', 'tg' => '', 'en' => ''],
    ]);

    $response = $this->getJson('/api/v1/news/test-item');

    $response->assertOk()
        ->assertJsonPath('data.slug', 'test-item')
        ->assertJsonPath('data.body', 'Полный текст новости.');
});

it('returns 404 for a draft item requested by slug', function () {
    News::factory()->create(['slug' => 'hidden-draft', 'status' => ContentStatus::Draft]);

    $this->getJson('/api/v1/news/hidden-draft')->assertNotFound();
});

it('filters the feed by category slug', function () {
    $category = Category::create([
        'type' => 'news', 'name' => ['ru' => 'Учения'], 'slug' => 'ucheniya', 'sort' => 1,
    ]);
    News::factory()->published()->create(['category_id' => $category->id]);
    News::factory()->published()->create();

    $this->getJson('/api/v1/news?category=ucheniya')->assertOk()->assertJsonCount(1, 'data');
});

it('never leaks internal editorial fields', function () {
    News::factory()->published()->create();

    $item = $this->getJson('/api/v1/news')->json('data.0');

    expect(array_keys($item))->toEqualCanonicalizing([
        'slug', 'title', 'excerpt', 'category', 'date', 'datetime', 'image', 'image_srcset', 'featured',
    ]);
});
