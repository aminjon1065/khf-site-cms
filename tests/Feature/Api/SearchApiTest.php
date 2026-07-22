<?php

use App\Models\News;
use App\Models\Page;

it('searches across public content and excludes drafts', function () {
    $news = News::factory()->published()->create([
        'title' => ['ru' => 'Наводнение в долине', 'tg' => '', 'en' => ''],
        'summary' => ['ru' => 'Оперативная информация', 'tg' => '', 'en' => ''],
    ]);
    $page = Page::factory()->published()->create([
        'title' => ['ru' => 'Наводнение: памятка населению'],
        'body' => ['ru' => 'Порядок действий населения.'],
    ]);
    News::factory()->create([
        'title' => ['ru' => 'Скрытое наводнение', 'tg' => '', 'en' => ''],
    ]);

    $response = $this->getJson('/api/v1/search?'.http_build_query([
        'locale' => 'ru',
        'q' => 'наводнение',
    ]));

    $response->assertOk()
        ->assertJsonPath('meta.total', 2)
        ->assertJsonFragment([
            'type' => 'news',
            'title' => 'Наводнение в долине',
            'path' => "/news/{$news->slug}",
        ]);

    $response->assertJsonFragment([
        'type' => 'page',
        'path' => "/pages/{$page->slug}",
    ]);
});

it('validates the query and clamps result pagination', function () {
    $this->getJson('/api/v1/search?q=x')->assertUnprocessable()->assertJsonValidationErrors('q');

    $this->getJson('/api/v1/search?q=ничего&per_page=999')
        ->assertOk()
        ->assertJsonPath('meta.per_page', 50);
});

it('paginates the combined result set in the database without a 500-item cap', function () {
    News::factory()->count(505)->published()->create([
        'title' => ['ru' => 'Уникальный поисковый маркер', 'tg' => '', 'en' => ''],
        'summary' => ['ru' => 'Проверка пагинации', 'tg' => '', 'en' => ''],
    ]);

    $response = $this->getJson('/api/v1/search?'.http_build_query([
        'locale' => 'ru',
        'q' => 'маркер',
        'per_page' => 50,
        'page' => 11,
    ]))->assertOk();

    $response->assertJsonPath('meta.total', 505)
        ->assertJsonPath('meta.last_page', 11)
        ->assertJsonCount(5, 'data');
});

it('searches only the requested locale fields', function () {
    News::factory()->published()->create([
        'title' => ['ru' => 'Только русский термин', 'tg' => 'Хабари тоҷикӣ', 'en' => 'English story'],
        'summary' => ['ru' => '', 'tg' => '', 'en' => ''],
    ]);

    $this->getJson('/api/v1/search?'.http_build_query(['locale' => 'en', 'q' => 'русский']))
        ->assertOk()
        ->assertJsonPath('meta.total', 0);
    $this->getJson('/api/v1/search?'.http_build_query(['locale' => 'ru', 'q' => 'русский']))
        ->assertOk()
        ->assertJsonPath('meta.total', 1);
});
