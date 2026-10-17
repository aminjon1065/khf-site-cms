<?php

use App\Enums\ContentStatus;
use App\Models\LegacyRedirect;
use App\Models\News;

// Ссылки на старые сайты (Drupal: khf.tj — таджикский, kchs.tj — русский)
// живут в документах, на других госсайтах и в поиске. Сайт спрашивает CMS,
// куда теперь ведёт старый адрес, и отвечает постоянной переадресацией.

it('leads an old khf.tj page to the Tajik version of the material', function () {
    $news = News::factory()->published()->create(['slug' => 'sel-v-varzobe']);
    LegacyRedirect::remember('https://khf.tj/node/6112', $news, 'tg');

    $this->getJson('/api/v1/redirects/legacy?source=khf.tj/node/6112')
        ->assertOk()
        ->assertExactJson(['data' => ['path' => '/tj/news/sel-v-varzobe']]);
});

it('tells the two old sites apart: their node numbers overlap', function () {
    $tajik = News::factory()->published()->create(['slug' => 'tajik-story']);
    $russian = News::factory()->published()->create(['slug' => 'russian-story']);
    LegacyRedirect::remember('khf.tj/node/100', $tajik, 'tg');
    LegacyRedirect::remember('kchs.tj/node/100', $russian, 'ru');

    $this->getJson('/api/v1/redirects/legacy?source=kchs.tj/node/100')
        ->assertJsonPath('data.path', '/ru/news/russian-story');
    $this->getJson('/api/v1/redirects/legacy?source=khf.tj/node/100')
        ->assertJsonPath('data.path', '/tj/news/tajik-story');
});

it('finds the old page however the link was written', function () {
    $news = News::factory()->published()->create(['slug' => 'pavodok']);
    LegacyRedirect::remember('https://www.kchs.tj/node/4167', $news, 'ru');

    foreach (['kchs.tj/node/4167', 'https://kchs.tj/node/4167/', 'http://www.KCHS.tj/node/4167?utm=x'] as $source) {
        $this->getJson('/api/v1/redirects/legacy?source='.urlencode($source))
            ->assertOk()
            ->assertJsonPath('data.path', '/ru/news/pavodok');
    }
});

it('opens another language when the old page language is not on the site', function () {
    $news = News::factory()->published()->create([
        'slug' => 'only-in-russian',
        'title' => ['ru' => 'Новость', 'tg' => '', 'en' => ''],
        'body' => ['ru' => 'Текст новости', 'tg' => '', 'en' => ''],
    ]);
    LegacyRedirect::remember('khf.tj/node/7', $news, 'tg');

    $this->getJson('/api/v1/redirects/legacy?source=khf.tj/node/7')
        ->assertJsonPath('data.path', '/ru/news/only-in-russian');
});

it('does not lead to a material that is not public any more', function () {
    $news = News::factory()->published()->create();
    LegacyRedirect::remember('khf.tj/node/8', $news, 'tg');
    $news->update(['status' => ContentStatus::Draft]);

    $this->getJson('/api/v1/redirects/legacy?source=khf.tj/node/8')->assertNotFound();
});

it('answers 404 for an address it does not know and 422 without one', function () {
    $this->getJson('/api/v1/redirects/legacy?source=khf.tj/node/999999')->assertNotFound();
    $this->getJson('/api/v1/redirects/legacy')->assertUnprocessable();
});

it('keeps one row per old address when the import runs again', function () {
    $first = News::factory()->published()->create();
    $second = News::factory()->published()->create();

    LegacyRedirect::remember('khf.tj/node/9', $first, 'tg');
    LegacyRedirect::remember('https://khf.tj/node/9/', $second, 'tg');

    expect(LegacyRedirect::query()->where('source', 'khf.tj/node/9')->count())->toBe(1)
        ->and(LegacyRedirect::query()->first()?->redirectable_id)->toBe($second->id);
});
