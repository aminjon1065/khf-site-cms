<?php

use App\Models\Category;
use App\Models\Setting;
use App\Observers\InvalidatePublicReadModels;
use Database\Seeders\HomeBlockSeeder;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RegionSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\TaxonomySeeder;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

use function Pest\Laravel\seed;

beforeEach(function () {
    seed([
        HomeBlockSeeder::class,
        MenuSeeder::class,
        RegionSeeder::class,
        SettingSeeder::class,
        TaxonomySeeder::class,
    ]);

    Cache::clear();
});

it('caches ready public read models instead of rebuilding them from application tables', function (string $uri, array $tables) {
    $queries = collect();

    DB::listen(function (QueryExecuted $query) use ($queries): void {
        $queries->push($query->sql);
    });

    $first = $this->getJson($uri)->assertOk()->json();
    $firstRequestQueryCount = $queries
        ->filter(fn (string $query): bool => collect($tables)->contains(
            fn (string $table): bool => Str::contains($query, ["from \"{$table}\"", "from `{$table}`"]),
        ))
        ->count();

    $queries->forget($queries->keys()->all());

    $second = $this->getJson($uri)->assertOk()->json();
    $secondRequestQueryCount = $queries
        ->filter(fn (string $query): bool => collect($tables)->contains(
            fn (string $table): bool => Str::contains($query, ["from \"{$table}\"", "from `{$table}`"]),
        ))
        ->count();

    expect($firstRequestQueryCount)->toBeGreaterThan(0)
        ->and($secondRequestQueryCount)->toBe(0)
        ->and($second)->toBe($first);
})->with([
    'settings' => ['/api/v1/settings?locale=ru', ['settings']],
    'menu' => ['/api/v1/menu?locale=ru', ['menu_items']],
    'home' => ['/api/v1/home?locale=ru', [
        'home_blocks',
        'alerts',
        'news',
        'instructions',
        'documents',
        'announcements',
        'projects',
    ]],
    'region directory' => ['/api/v1/regions/directory?locale=ru', ['regions', 'districts']],
    'categories' => ['/api/v1/categories?locale=ru', ['categories']],
]);

it('invalidates settings read models through an after-commit observer', function () {
    $before = $this->getJson('/api/v1/settings?locale=ru')
        ->assertOk()
        ->json('data.org.short_name');

    $setting = Setting::query()
        ->where('group', 'org')
        ->where('key', 'short_name_ru')
        ->firstOrFail();
    $setting->update(['value' => 'Новое краткое название']);

    app(InvalidatePublicReadModels::class)->saved($setting);

    $this->getJson('/api/v1/settings?locale=ru')
        ->assertOk()
        ->assertJsonPath('data.org.short_name', 'Новое краткое название');

    expect($before)->not->toBe('Новое краткое название')
        ->and(is_subclass_of(InvalidatePublicReadModels::class, ShouldHandleEventsAfterCommit::class))
        ->toBeTrue();
});

it('isolates cache entries by locale and query parameters', function () {
    Category::query()->create([
        'type' => 'document',
        'name' => ['ru' => 'Документы', 'tg' => 'Ҳуҷҷатҳо', 'en' => 'Documents'],
        'slug' => 'documents',
        'sort' => 1,
    ]);

    $ruName = $this->getJson('/api/v1/settings?locale=ru')->assertOk()->json('data.org.short_name');
    $enName = $this->getJson('/api/v1/settings?locale=en')->assertOk()->json('data.org.short_name');
    $newsSlugs = $this->getJson('/api/v1/categories?locale=ru&type=news')->assertOk()->json('data');
    $documentSlugs = $this->getJson('/api/v1/categories?locale=ru&type=document')->assertOk()->json('data');

    expect($ruName)->not->toBe($enName)
        ->and(collect($newsSlugs)->pluck('type')->unique()->all())->toBe(['news'])
        ->and(collect($documentSlugs)->pluck('slug'))->toContain('documents');
});
