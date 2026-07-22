<?php

use App\Enums\ContentStatus;
use App\Enums\HazardType;
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
        'title' => ['ru' => $ru, 'tg' => 'Огоҳӣ', 'en' => 'Alert'],
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

    $response = $this->getJson('/api/v1/alerts?locale=ru');

    $response->assertOk()->assertJsonCount(2, 'data');
    expect($response->json('data.0.title'))->toBe('Critical alert')
        ->and($response->json('data.0.level'))->toBe('critical')
        ->and($response->json('data.1.level'))->toBe('warning');
});

it('maps severity to the public level and exposes a slug + active status', function () {
    activeAlert(Severity::Info, 'Инфо-предупреждение');

    $item = $this->getJson('/api/v1/alerts?locale=ru')->json('data.0');

    expect($item['level'])->toBe('info')
        ->and($item['slug'])->not->toBeEmpty()
        ->and($item['status'])->toBe('Действует')
        ->and($item['is_active'])->toBeTrue();
});

it('localizes public labels and keeps stable machine-readable alert fields', function () {
    $publishedAt = now()->subHour()->startOfMinute();
    $startsAt = now()->subMinutes(30)->startOfMinute();
    $endsAt = now()->addHour()->startOfMinute();

    Alert::factory()->published()->create([
        'severity' => Severity::Warning,
        'hazard_type' => HazardType::Mudflow,
        'territory_type' => 'country',
        'published_at' => $publishedAt,
        'starts_at' => $startsAt,
        'ends_at' => $endsAt,
        'title' => ['ru' => 'Сель', 'tg' => 'Сел', 'en' => 'Mudflow'],
    ]);

    $tg = $this->getJson('/api/v1/alerts?locale=tg')->assertOk()->json('data.0');
    expect($tg['level'])->toBe('warning')
        ->and($tg['level_label'])->toBe('Сатҳи норанҷӣ')
        ->and($tg['status_code'])->toBe('active')
        ->and($tg['status'])->toBe('Амал мекунад')
        ->and($tg['hazard'])->toBe('mudflow')
        ->and($tg['hazard_label'])->toBe('Сел')
        ->and($tg['region'])->toBe('Тамоми Ҷумҳурии Тоҷикистон')
        ->and($tg['published_at'])->toBe($publishedAt->toIso8601String())
        ->and($tg['starts_at_iso'])->toBe($startsAt->toIso8601String())
        ->and($tg['ends_at_iso'])->toBe($endsAt->toIso8601String());

    $en = $this->getJson('/api/v1/alerts?locale=en')->assertOk()->json('data.0');
    expect($en['level_label'])->toBe('Orange level')
        ->and($en['status'])->toBe('Active')
        ->and($en['hazard_label'])->toBe('Mudflow')
        ->and($en['region'])->toBe('Entire Republic of Tajikistan');
});

it('returns alert detail by slug with split instructions and regions', function () {
    $alert = Alert::factory()->published()->create([
        'severity' => Severity::Warning,
        'territory_type' => 'regions',
        'title' => ['ru' => 'Детальное предупреждение', 'tg' => '', 'en' => ''],
        'instructions' => ['ru' => "Шаг один\nШаг два", 'tg' => '', 'en' => ''],
    ]);
    $alert->regions()->attach(Region::query()->where('code', 'khatlon')->value('id'));

    $data = $this->getJson("/api/v1/alerts/{$alert->slug}?locale=ru")->assertOk()->json('data');

    expect($data['instructions'])->toBe(['Шаг один', 'Шаг два'])
        ->and($data['regions'])->toHaveCount(1)
        ->and($data['region_codes'])->toContain('khatlon');
});

it('never exposes the internal editorial title through the public API', function () {
    Alert::factory()->published()->create([
        'internal_title' => 'INTERNAL: do not publish',
        'title' => ['ru' => '', 'tg' => '', 'en' => ''],
        'summary' => ['ru' => '', 'tg' => '', 'en' => ''],
    ]);

    $response = $this->getJson('/api/v1/alerts?locale=en')->assertOk();

    $response->assertJsonCount(0, 'data');
    expect($response->getContent())->not->toContain('INTERNAL: do not publish');
});

it('returns 404 for an unknown alert slug', function () {
    $this->getJson('/api/v1/alerts/nope?locale=ru')->assertNotFound();
});

it('computes the region map snapshot from active alerts', function () {
    activeAlert(Severity::Critical, 'Крит', 'khatlon');
    activeAlert(Severity::Info, 'Инфо', 'sughd');

    $data = $this->getJson('/api/v1/alerts/active?locale=ru')->assertOk()->json('data');

    expect($data['state'])->toBe('critical')
        ->and($data['count'])->toBe(2);

    $byKey = collect($data['regions'])->keyBy('key');
    expect($byKey['khatlon']['level'])->toBe('critical')
        ->and($byKey['khatlon']['count'])->toBe(1)
        ->and($byKey['sughd']['level'])->toBe('info')
        ->and($byKey['dushanbe']['level'])->toBe('none');
});

it('localizes the region map status text', function () {
    activeAlert(Severity::Info, 'Инфо', 'sughd');

    $tg = collect($this->getJson('/api/v1/regions?locale=tg')->assertOk()->json('data'))->keyBy('key');
    $en = collect($this->getJson('/api/v1/regions?locale=en')->assertOk()->json('data'))->keyBy('key');

    expect($tg['sughd']['statusText'])->toBe('Огоҳии иттилоотӣ')
        ->and($tg['dushanbe']['statusText'])->toBe('Вазъият муқаррарӣ')
        ->and($en['sughd']['statusText'])->toBe('Information notice')
        ->and($en['dushanbe']['statusText'])->toBe('Normal conditions');
});

it('applies a country-wide alert to every region', function () {
    activeAlert(Severity::Warning, 'Вся страна', null, 'country');

    $regions = $this->getJson('/api/v1/regions?locale=ru')->assertOk()->json('data');

    expect($regions)->toHaveCount(5);

    foreach ($regions as $region) {
        expect($region['level'])->toBe('warning')
            ->and($region['count'])->toBe(1);
    }
});
