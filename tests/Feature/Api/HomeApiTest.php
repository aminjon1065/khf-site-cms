<?php

use App\Models\Alert;
use App\Models\Announcement;
use App\Models\Category;
use App\Models\Document;
use App\Models\HomeBlock;
use App\Models\Instruction;
use App\Models\News;
use App\Models\Project;
use App\Models\Region;
use App\Services\HomePageReadModel;
use Database\Seeders\HomeBlockSeeder;
use Database\Seeders\RegionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\seed;

beforeEach(function () {
    seed([RegionSeeder::class, HomeBlockSeeder::class, SettingSeeder::class]);
});

it('returns enabled blocks in editor order', function () {
    $data = $this->getJson('/api/v1/home?locale=ru')->assertOk()->json('data');

    $types = collect($data['blocks'])->pluck('type');

    expect($types)->toContain('active_alerts', 'latest_news', 'documents')
        ->and($types)->not->toContain('emergency_contacts') // disabled in seed
        ->and($data['blocks'][0]['type'])->toBe('active_alerts'); // first by sort
});

it('respects a block limit for news', function () {
    News::factory()->count(8)->published()->create();

    $block = HomeBlock::query()->where('type', 'latest_news')->first();
    $block->config = ['limit' => 2];
    $block->save();

    $data = $this->getJson('/api/v1/home?locale=ru')->json('data');

    expect($data['news'])->toHaveCount(2);
});

it('omits a disabled block from the blocks list', function () {
    $block = HomeBlock::query()->where('type', 'documents')->first();
    $block->enabled = false;
    $block->save();

    $types = collect($this->getJson('/api/v1/home?locale=ru')->json('data.blocks'))->pluck('type');

    expect($types)->not->toContain('documents');
});

it('returns only news explicitly enabled for the home page', function () {
    News::factory()->published()->create([
        'title' => ['ru' => 'Показывать на главной', 'tg' => '', 'en' => ''],
        'show_on_home' => true,
    ]);
    News::factory()->published()->create([
        'title' => ['ru' => 'Не показывать на главной', 'tg' => '', 'en' => ''],
        'show_on_home' => false,
    ]);

    $response = $this->getJson('/api/v1/home?locale=ru')->assertOk();

    $response->assertJsonCount(1, 'data.news')
        ->assertJsonPath('data.news.0.title', 'Показывать на главной')
        ->assertJsonMissing([
            'title' => 'Не показывать на главной',
        ]);
});

it('includes the alert snapshot with every region', function () {
    $data = $this->getJson('/api/v1/home?locale=ru')->json('data');

    expect($data['alerts'])->toHaveKeys(['state', 'count', 'regions', 'items'])
        ->and($data['alerts']['regions'])->toHaveCount(5);
});

it('includes locale-aware emergency contacts for the home block renderer', function () {
    $data = $this->getJson('/api/v1/home?locale=en')->assertOk()->json('data');

    expect($data['emergency_contacts']['emergency_number'])->toBe('112')
        ->and($data['emergency_contacts']['services'][0])->toBe([
            'num' => '112',
            'label' => 'unified rescue service',
        ]);
});

it('returns each home-block title in the requested locale', function () {
    $blocks = collect($this->getJson('/api/v1/home?locale=en')->assertOk()->json('data.blocks'))->keyBy('type');

    expect($blocks['latest_news']['title'])->toBe('Latest news')
        ->and($blocks['instructions']['title'])->toBe('Public safety guides');
});

it('builds a populated home read model within a fixed query budget', function () {
    $region = Region::query()->firstOrFail();
    $alert = Alert::factory()->published()->create();
    $alert->regions()->attach($region);
    $category = Category::query()->create([
        'type' => 'news',
        'name' => ['ru' => 'Новости', 'tg' => 'Хабарҳо', 'en' => 'News'],
        'slug' => 'home-query-budget',
        'sort' => 1,
    ]);
    News::factory()->published()->create([
        'category_id' => $category->id,
        'show_on_home' => true,
    ]);
    Instruction::factory()->published()->create();
    Document::factory()->published()->create();
    Announcement::factory()->published()->create();
    Project::factory()->published()->create();

    Cache::flush();
    DB::flushQueryLog();
    DB::enableQueryLog();

    $data = app(HomePageReadModel::class)->build('ru');

    $queries = collect(DB::getQueryLog())
        ->pluck('query')
        ->filter(fn (string $query): bool => str_starts_with($query, 'select ')
            && preg_match('/from [`"]cache[`"]/', $query) !== 1)
        ->values();
    DB::disableQueryLog();

    expect($data['alerts']['items'])->toHaveCount(1)
        ->and($data['news'])->not->toBeEmpty()
        ->and($data['instructions'])->not->toBeEmpty()
        ->and($data['documents'])->not->toBeEmpty()
        ->and($data['announcements'])->not->toBeEmpty()
        ->and($data['projects'])->not->toBeEmpty()
        ->and($queries)->toHaveCount(15)
        ->and($queries->filter(fn (string $query): bool => preg_match('/from [`"]alerts[`"]/', $query) === 1))->toHaveCount(1);

    $newsQuery = $queries->first(fn (string $query): bool => preg_match('/from [`"]news[`"]/', $query) === 1);
    expect($newsQuery)->toBeString()
        ->and($newsQuery)->not->toContain('"body"', '`body`', '"seo"', '`seo`', '"views_count"', '`views_count`');
});

// D-2 (линия main): тесты выше, которые сохраняют HomeBlock и сразу
// перечитывают ответ, уже доказывают инвалидацию при save(). Этот проверяет
// вторую половину — повторный идентичный запрос не должен идти в БД вообще.
it('serves the second identical request from cache without hitting the database', function () {
    $this->getJson('/api/v1/home?locale=ru')->assertOk();

    $queries = 0;
    DB::listen(function () use (&$queries) {
        $queries++;
    });

    $this->getJson('/api/v1/home?locale=ru')->assertOk();

    expect($queries)->toBe(0);
});
