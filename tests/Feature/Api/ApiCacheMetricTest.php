<?php

use App\Models\Leader;
use App\Models\StructureUnit;
use App\Services\OperationalTelemetry;
use Database\Seeders\HomeBlockSeeder;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RegionSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\TaxonomySeeder;
use Illuminate\Support\Facades\Cache;

use function Pest\Laravel\getJson;
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

/**
 * Доля попаданий в read-model-кэш. Метрика существует не ради красивой цифры:
 * без неё «медленный ответ» и «холодный кэш» неразличимы ни на конкретном
 * запросе, ни в сводке.
 */
it('marks a cold read model as a miss and the repeat as a hit', function () {
    $first = getJson('/api/v1/settings?locale=ru');

    $first->assertOk()->assertHeader('X-Cache', 'MISS');

    getJson('/api/v1/settings?locale=ru')
        ->assertOk()
        ->assertHeader('X-Cache', 'HIT');
});

it('reports a partial hit when a request builds only part of what it needs', function () {
    // Главная собирается из своей read-модели, а та внутри берёт настройки
    // (`HomePageReadModel` → `PublicSettingsService`). Прогреваем настройки —
    // тогда холодный запрос главной обязан отличаться и от полного попадания,
    // и от полного промаха: одно обращение из кэша, второе построено.
    getJson('/api/v1/settings?locale=ru')->assertOk()->assertHeader('X-Cache', 'MISS');
    getJson('/api/v1/settings?locale=ru')->assertOk()->assertHeader('X-Cache', 'HIT');

    getJson('/api/v1/home?locale=ru')
        ->assertOk()
        ->assertHeader('X-Cache', 'PARTIAL');

    getJson('/api/v1/home?locale=ru')
        ->assertOk()
        ->assertHeader('X-Cache', 'HIT');
});

it('does not label a request that never touched the read-model cache', function () {
    // `health` кэш не трогает: заголовок «MISS» здесь означал бы промах,
    // которого не было. Отсутствие заголовка и промах — разные вещи.
    getJson('/api/v1/health')
        ->assertOk()
        ->assertHeaderMissing('X-Cache');
});

it('counts every request, not only the sampled ones', function () {
    // Выборка метрик по построению смещена к медленным и ошибочным ответам,
    // то есть к промахам. Поэтому счётчик кэша отдельный и работает всегда —
    // при нулевой доле сэмплирования тоже.
    config()->set('observability.api.sample_rate', 0);

    getJson('/api/v1/settings?locale=ru')->assertOk();   // miss
    getJson('/api/v1/settings?locale=ru')->assertOk();   // hit
    getJson('/api/v1/settings?locale=ru')->assertOk();   // hit

    $cache = app(OperationalTelemetry::class)->summary()['cache'];

    expect($cache['requests'])->toBe(3)
        ->and($cache['hits'])->toBe(2)
        ->and($cache['misses'])->toBe(1)
        ->and($cache['hit_rate'])->toBe(0.667);
});

it('has no cache rate before any request', function () {
    // Ноль запросов — это «нет данных», а не «нулевая доля попаданий».
    expect(app(OperationalTelemetry::class)->summary()['cache']['hit_rate'])->toBeNull();
});

it('invalidates leadership and structure through the shared observer', function () {
    // Раньше эти два эндпоинта жили на собственном 60-секундном TTL и своём
    // трейте сброса. После переезда на общий кэш инвалидация должна идти тем
    // же путём, что у остальных read-моделей, — иначе правка руководства
    // висела бы в кэше до истечения окна.
    $leader = Leader::factory()->create(['name' => ['ru' => 'Прежний руководитель', 'tg' => '', 'en' => '']]);

    getJson('/api/v1/leadership?locale=ru')->assertOk()->assertHeader('X-Cache', 'MISS');
    getJson('/api/v1/leadership?locale=ru')
        ->assertOk()
        ->assertHeader('X-Cache', 'HIT')
        ->assertJsonFragment(['name' => 'Прежний руководитель']);

    $leader->update(['name' => ['ru' => 'Новый руководитель', 'tg' => '', 'en' => '']]);

    getJson('/api/v1/leadership?locale=ru')
        ->assertOk()
        ->assertHeader('X-Cache', 'MISS')
        ->assertJsonFragment(['name' => 'Новый руководитель']);

    $unit = StructureUnit::factory()->create(['name' => ['ru' => 'Прежнее подразделение', 'tg' => '', 'en' => '']]);

    getJson('/api/v1/structure?locale=ru')->assertOk()->assertHeader('X-Cache', 'MISS');
    getJson('/api/v1/structure?locale=ru')->assertOk()->assertHeader('X-Cache', 'HIT');

    $unit->update(['name' => ['ru' => 'Новое подразделение', 'tg' => '', 'en' => '']]);

    getJson('/api/v1/structure?locale=ru')
        ->assertOk()
        ->assertHeader('X-Cache', 'MISS')
        ->assertJsonFragment(['name' => 'Новое подразделение']);
});
