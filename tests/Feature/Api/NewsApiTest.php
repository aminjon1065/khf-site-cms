<?php

use App\Enums\ContentStatus;
use App\Models\Category;
use App\Models\News;

it('returns only publicly visible news', function () {
    News::factory()->published()->create(['title' => ['ru' => 'Опубликовано', 'tg' => 'Нашр шуд', 'en' => '']]);
    News::factory()->create(['status' => ContentStatus::Draft]);
    News::factory()->create(['status' => ContentStatus::Review]);

    $response = $this->getJson('/api/v1/news?locale=ru');

    $response->assertOk()->assertJsonCount(1, 'data');
    expect($response->json('data.0.title'))->toBe('Опубликовано');
});

it('excludes news with a future publish date', function () {
    News::factory()->create([
        'status' => ContentStatus::Published,
        'published_at' => now()->addDay(),
    ]);

    $this->getJson('/api/v1/news?locale=ru')->assertOk()->assertJsonCount(0, 'data');
});

it('serves the requested locale and omits an unpublished translation', function () {
    News::factory()->published()->create([
        'title' => ['ru' => 'Русский заголовок', 'tg' => 'Сарлавҳаи тоҷикӣ', 'en' => ''],
    ]);

    expect($this->getJson('/api/v1/news?locale=tg')->json('data.0.title'))->toBe('Сарлавҳаи тоҷикӣ');
    $this->getJson('/api/v1/news?locale=en')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('returns a published item by slug with a body', function () {
    News::factory()->published()->create([
        'slug' => 'test-item',
        'body' => ['ru' => 'Полный текст новости.', 'tg' => '', 'en' => ''],
        'seo' => [
            'ru' => ['title' => 'SEO новости', 'description' => 'Описание для поиска.'],
            'tg' => ['title' => '', 'description' => ''],
            'en' => ['title' => '', 'description' => ''],
        ],
    ]);

    $response = $this->getJson('/api/v1/news/test-item?locale=ru');

    $response->assertOk()
        ->assertJsonPath('data.slug', 'test-item')
        ->assertJsonPath('data.body', 'Полный текст новости.')
        ->assertJsonPath('data.seo.title', 'SEO новости')
        ->assertJsonPath('data.seo.description', 'Описание для поиска.');
});

it('returns 404 for a draft item requested by slug', function () {
    News::factory()->create(['slug' => 'hidden-draft', 'status' => ContentStatus::Draft]);

    $this->getJson('/api/v1/news/hidden-draft?locale=ru')->assertNotFound();
});

it('filters the feed by category slug', function () {
    $category = Category::create([
        'type' => 'news', 'name' => ['ru' => 'Учения'], 'slug' => 'ucheniya', 'sort' => 1,
    ]);
    News::factory()->published()->create(['category_id' => $category->id]);
    News::factory()->published()->create();

    $this->getJson('/api/v1/news?locale=ru&category=ucheniya')->assertOk()->assertJsonCount(1, 'data');
});

it('never leaks internal editorial fields', function () {
    News::factory()->published()->create();

    $item = $this->getJson('/api/v1/news?locale=ru')->json('data.0');

    expect(array_keys($item))->toEqualCanonicalizing([
        'slug', 'title', 'excerpt', 'category', 'date', 'datetime', 'image', 'image_srcset', 'featured',
    ]);
});

it('supports correlation IDs and conditional GETs', function () {
    News::factory()->published()->create();

    $first = $this->withHeader('X-Request-ID', 'test-request-123')
        ->getJson('/api/v1/news?locale=ru')
        ->assertOk()
        ->assertHeader('X-Request-ID', 'test-request-123');

    $etag = (string) $first->headers->get('ETag');
    expect($etag)->not->toBeEmpty();

    $this->withHeader('If-None-Match', $etag)
        ->getJson('/api/v1/news?locale=ru')
        ->assertNotModified()
        ->assertHeader('ETag', $etag);
});
