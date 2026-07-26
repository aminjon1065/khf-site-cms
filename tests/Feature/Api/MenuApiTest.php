<?php

use App\Models\MenuItem;
use Database\Seeders\MenuSeeder;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\seed;

beforeEach(function () {
    seed(MenuSeeder::class);
});

it('returns main and footer menus as localized trees', function () {
    // The Next.js frontend always requests an explicit content locale.
    $data = $this->getJson('/api/v1/menu?locale=ru')->assertOk()->json('data');

    expect($data)->toHaveKeys(['main', 'footer'])
        ->and($data['main'])->toBeArray()->not->toBeEmpty()
        ->and($data['footer'])->toBeArray()->not->toBeEmpty()
        ->and($data['main'][0])->toHaveKeys(['label', 'url', 'children'])
        ->and($data['main'][0]['label'])->toBeString()->not->toBe('');
});

it('excludes disabled menu items', function () {
    $hidden = MenuItem::query()->where('location', 'main')->first();
    $hidden->update(['enabled' => false, 'url' => '/hidden-secret-link']);

    $body = $this->getJson('/api/v1/menu')->getContent();

    expect($body)->not->toContain('/hidden-secret-link');
});

it('resolves labels to the requested locale', function () {
    $item = MenuItem::query()->where('location', 'main')->orderBy('sort')->first();
    $item->setTranslations('label', ['ru' => 'Новости', 'tg' => 'Хабарҳо', 'en' => 'News']);
    $item->save();

    $tg = $this->getJson('/api/v1/menu', ['Accept-Language' => 'tg'])->json('data.main');
    $en = $this->getJson('/api/v1/menu', ['Accept-Language' => 'en'])->json('data.main');

    expect(collect($tg)->pluck('label'))->toContain('Хабарҳо')
        ->and(collect($en)->pluck('label'))->toContain('News');
});

// D-2: the test above already proves invalidation-on-save (it saves a label
// change and immediately sees it) — this adds the other half: a repeat
// request must skip the database entirely.
it('serves the second identical request from cache without hitting the database', function () {
    $this->getJson('/api/v1/menu')->assertOk();

    $queries = 0;
    DB::listen(function () use (&$queries) {
        $queries++;
    });

    $this->getJson('/api/v1/menu')->assertOk();

    expect($queries)->toBe(0);
});
