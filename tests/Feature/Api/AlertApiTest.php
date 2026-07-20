<?php

use App\Enums\ContentStatus;
use App\Enums\Severity;
use App\Models\Alert;
use App\Models\Region;
use Database\Seeders\RegionSeeder;

use function Pest\Laravel\seed;

beforeEach(function () {
    seed(RegionSeeder::class);
});

function activeAlert(Severity $severity, string $ru, ?string $regionCode = 'khatlon', string $territory = 'regions'): Alert
{
    $alert = Alert::factory()->published()->create([
        'severity' => $severity,
        'territory_type' => $territory,
        'title' => ['ru' => $ru, 'tg' => '', 'en' => ''],
    ]);

    if ($regionCode !== null && $territory === 'regions') {
        $alert->regions()->attach(Region::query()->where('code', $regionCode)->value('id'));
    }

    return $alert;
}

it('lists only active alerts, most severe first', function () {
    activeAlert(Severity::Warning, 'Warning alert');
    activeAlert(Severity::Critical, 'Critical alert');
    Alert::factory()->create(['status' => ContentStatus::Draft]);
    Alert::factory()->published()->create(['ends_at' => now()->subDay()]); // expired

    $response = $this->getJson('/api/v1/alerts');

    $response->assertOk()->assertJsonCount(2, 'data');
    expect($response->json('data.0.title'))->toBe('Critical alert')
        ->and($response->json('data.0.level'))->toBe('critical')
        ->and($response->json('data.1.level'))->toBe('warning');
});

it('maps severity to the public level and exposes a slug + active status', function () {
    activeAlert(Severity::Info, 'Инфо-предупреждение');

    $item = $this->getJson('/api/v1/alerts')->json('data.0');

    expect($item['level'])->toBe('info')
        ->and($item['slug'])->not->toBeEmpty()
        ->and($item['status'])->toBe('Действует')
        ->and($item['is_active'])->toBeTrue();
});

it('returns alert detail by slug with split instructions and regions', function () {
    $alert = Alert::factory()->published()->create([
        'severity' => Severity::Warning,
        'territory_type' => 'regions',
        'title' => ['ru' => 'Детальное предупреждение', 'tg' => '', 'en' => ''],
        'instructions' => ['ru' => "Шаг один\nШаг два", 'tg' => '', 'en' => ''],
    ]);
    $alert->regions()->attach(Region::query()->where('code', 'khatlon')->value('id'));

    $data = $this->getJson("/api/v1/alerts/{$alert->slug}")->assertOk()->json('data');

    expect($data['instructions'])->toBe(['Шаг один', 'Шаг два'])
        ->and($data['regions'])->toHaveCount(1)
        ->and($data['region_codes'])->toContain('khatlon');
});

it('returns 404 for an unknown alert slug', function () {
    $this->getJson('/api/v1/alerts/nope')->assertNotFound();
});

it('computes the region map snapshot from active alerts', function () {
    activeAlert(Severity::Critical, 'Крит', 'khatlon');
    activeAlert(Severity::Info, 'Инфо', 'sughd');

    $data = $this->getJson('/api/v1/alerts/active')->assertOk()->json('data');

    expect($data['state'])->toBe('critical')
        ->and($data['count'])->toBe(2);

    $byKey = collect($data['regions'])->keyBy('key');
    expect($byKey['khatlon']['level'])->toBe('critical')
        ->and($byKey['khatlon']['count'])->toBe(1)
        ->and($byKey['sughd']['level'])->toBe('info')
        ->and($byKey['dushanbe']['level'])->toBe('none');
});

it('applies a country-wide alert to every region', function () {
    activeAlert(Severity::Warning, 'Вся страна', null, 'country');

    $regions = $this->getJson('/api/v1/regions')->assertOk()->json('data');

    expect($regions)->toHaveCount(5);

    foreach ($regions as $region) {
        expect($region['level'])->toBe('warning')
            ->and($region['count'])->toBe(1);
    }
});
