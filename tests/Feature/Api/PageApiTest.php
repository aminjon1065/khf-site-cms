<?php

use App\Models\Page;

it('lists only published pages', function () {
    $published = Page::factory()->published()->create(['title' => ['ru' => 'Опубликованная']]);
    Page::factory()->create(['title' => ['ru' => 'Черновик']]); // draft

    $data = $this->getJson('/api/v1/pages?locale=ru')->assertOk()->json('data');

    $slugs = collect($data)->pluck('slug');

    expect($slugs)->toContain($published->slug)
        ->and($slugs)->not->toContain(Page::query()->where('title->ru', 'Черновик')->value('slug'))
        ->and($data[0])->toHaveKeys(['slug', 'title'])
        ->and($data[0])->not->toHaveKey('body');
});

it('returns a published page with its body by slug', function () {
    $page = Page::factory()->published()->create([
        'title' => ['ru' => 'О Комитете'],
        'body' => ['ru' => "Первый абзац.\n\nВторой абзац."],
    ]);

    $data = $this->getJson("/api/v1/pages/{$page->slug}?locale=ru")->assertOk()->json('data');

    expect($data['title'])->toBe('О Комитете')
        ->and($data['body'])->toContain('Первый абзац')
        ->and($data)->toHaveKey('updated');
});

it('returns 404 for a draft page', function () {
    $draft = Page::factory()->create(); // draft status

    $this->getJson("/api/v1/pages/{$draft->slug}")->assertNotFound();
});

it('returns 404 for an unknown slug', function () {
    $this->getJson('/api/v1/pages/does-not-exist')->assertNotFound();
});
