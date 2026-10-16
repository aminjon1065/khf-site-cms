<?php

use App\Enums\ContentStatus;
use App\Enums\HazardType;
use App\Enums\Severity;
use App\Models\Alert;
use App\Models\District;
use App\Models\Instruction;
use App\Models\Region;
use App\Services\AlertMapService;
use Carbon\CarbonImmutable;
use Database\Seeders\RegionSeeder;
use Illuminate\Support\Facades\Cache;

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

it('says as of when the alert state is known, even when there are no alerts', function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-16 09:42:37', 'Asia/Dushanbe'));
    Cache::forever(AlertMapService::RECONCILED_AT_KEY, now()->subMinute()->toIso8601String());

    $this->getJson('/api/v1/alerts/active?locale=ru')
        ->assertOk()
        ->assertJsonPath('data.count', 0)
        ->assertJsonPath('data.updated_at', '2026-09-16T09:42:00+05:00');

    $this->getJson('/api/v1/home?locale=ru')
        ->assertOk()
        ->assertJsonPath('data.alerts.updated_at', '2026-09-16T09:42:00+05:00');
});

it('dates the alert state back to the last reconciliation when the scheduler has stopped', function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-16 12:00:00', 'Asia/Dushanbe'));
    Cache::forever(AlertMapService::RECONCILED_AT_KEY, '2026-09-16T09:15:00+05:00');
    activeAlert(Severity::Warning, 'Сель');

    $this->getJson('/api/v1/alerts/active?locale=ru')
        ->assertOk()
        ->assertJsonPath('data.count', 1)
        ->assertJsonPath('data.updated_at', '2026-09-16T09:15:00+05:00');
});

it('lists the languages an alert is published in on its detail', function () {
    $alert = activeAlert(Severity::Warning, 'Сель');
    $alert->forceFill(['title' => ['ru' => 'Сель', 'tg' => 'Сел', 'en' => '']])->save();

    $this->getJson("/api/v1/alerts/{$alert->slug}?locale=tg")
        ->assertOk()
        ->assertJsonPath('data.available_locales', ['tg', 'ru']);
});

it('keeps the state current between the five-minute scheduler runs', function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-16 10:07:20', 'Asia/Dushanbe'));
    Cache::forever(AlertMapService::RECONCILED_AT_KEY, '2026-09-16T10:00:04+05:00');

    $this->getJson('/api/v1/alerts/active?locale=ru')
        ->assertJsonPath('data.updated_at', '2026-09-16T10:07:00+05:00');
});

it('tells a citizen that an alert published ahead of time is not in effect yet', function () {
    // Published now, in effect from tomorrow: it is not on the list or the
    // map, and its page must not say «Действует» either.
    $startsAt = now()->addDay()->startOfMinute();
    $alert = Alert::factory()->published()->create([
        'territory_type' => 'country',
        'starts_at' => $startsAt,
        'ends_at' => $startsAt->copy()->addDay(),
        'title' => ['ru' => 'Штормовое предупреждение', 'tg' => 'Огоҳии тӯфон', 'en' => 'Storm warning'],
    ]);

    expect($alert->isActive())->toBeFalse()
        ->and($alert->isUpcoming())->toBeTrue();

    $ru = $this->getJson("/api/v1/alerts/{$alert->slug}?locale=ru")->assertOk()->json('data');

    expect($ru['status_code'])->toBe('upcoming')
        ->and($ru['status'])->toBe('Ещё не действует')
        ->and($ru['is_active'])->toBeFalse()
        ->and(collect($ru['meta'])->pluck('value', 'label')->all())
        ->toMatchArray([
            'Действует с' => $startsAt->format('d.m.Y, H:i'),
            'Действует до' => $startsAt->copy()->addDay()->format('d.m.Y, H:i'),
        ]);

    expect($this->getJson("/api/v1/alerts/{$alert->slug}?locale=tg")->json('data.status'))
        ->toBe('Ҳанӯз амал намекунад');

    $this->getJson('/api/v1/alerts?locale=ru')->assertOk()->assertJsonCount(0, 'data');
});

it('reports an alert past its end time as completed', function () {
    $alert = Alert::factory()->published()->create([
        'territory_type' => 'country',
        'starts_at' => now()->subDays(2),
        'ends_at' => now()->subDay(),
        'title' => ['ru' => 'Паводок', 'tg' => 'Обхезӣ', 'en' => 'Flood'],
    ]);

    expect($this->getJson("/api/v1/alerts/{$alert->slug}?locale=ru")->json('data.status_code'))
        ->toBe('completed');
});

it('lists the districts named inside the affected regions, under their region', function () {
    $khatlon = Region::query()->where('code', 'khatlon')->firstOrFail();
    $sughd = Region::query()->where('code', 'sughd')->firstOrFail();
    $first = District::query()->create(['region_id' => $khatlon->id, 'name' => ['ru' => 'Дангара', 'tg' => 'Данғара', 'en' => 'Dangara'], 'sort' => 900]);
    $second = District::query()->create(['region_id' => $khatlon->id, 'name' => ['ru' => 'Фархор', 'tg' => 'Фархор', 'en' => ''], 'sort' => 901]);

    $alert = Alert::factory()->published()->create([
        'territory_type' => 'regions',
        'title' => ['ru' => 'Сель', 'tg' => 'Сел', 'en' => 'Mudflow'],
    ]);
    $alert->regions()->attach([$khatlon->id, $sughd->id]);
    $alert->districts()->attach([$second->id, $first->id]);

    $ru = $this->getJson("/api/v1/alerts/{$alert->slug}?locale=ru")->assertOk()->json('data.districts');
    expect($ru)->toBe([
        ['name' => 'Дангара', 'region_code' => 'khatlon'],
        ['name' => 'Фархор', 'region_code' => 'khatlon'],
    ]);

    expect($this->getJson("/api/v1/alerts/{$alert->slug}?locale=tg")->json('data.districts.0.name'))->toBe('Данғара');

    // A district without a name in the page language is left out, never
    // shown in another language.
    expect($this->getJson("/api/v1/alerts/{$alert->slug}?locale=en")->json('data.districts'))
        ->toBe([['name' => 'Dangara', 'region_code' => 'khatlon']]);
});

it('links only the safety guides a citizen can open on this language version', function () {
    $alert = Alert::factory()->published()->create([
        'territory_type' => 'country',
        'title' => ['ru' => 'Землетрясение', 'tg' => 'Заминларза', 'en' => 'Earthquake'],
    ]);
    $guide = Instruction::factory()->published()->create([
        'name' => ['ru' => 'При землетрясении', 'tg' => 'Ҳангоми заминларза', 'en' => ''],
        'summary' => ['ru' => 'Что делать до, во время и после', 'tg' => 'Чӣ бояд кард', 'en' => ''],
        'hazard_type' => HazardType::Earthquake,
        'is_priority' => false,
    ]);
    $priority = Instruction::factory()->published()->create([
        'name' => ['ru' => 'Тревожный чемоданчик', 'tg' => 'Ҷомадони изтирорӣ', 'en' => ''],
        'is_priority' => true,
    ]);
    $draft = Instruction::factory()->create(['name' => ['ru' => 'Черновик инструкции', 'tg' => '', 'en' => '']]);
    $alert->relatedInstructions()->attach([$guide->id, $priority->id, $draft->id]);

    $ru = $this->getJson("/api/v1/alerts/{$alert->slug}?locale=ru")->assertOk()->json('data.related_instructions');

    expect(array_column($ru, 'slug'))->toBe([$priority->slug, $guide->slug])
        ->and($ru[1])->toBe([
            'slug' => $guide->slug,
            'title' => 'При землетрясении',
            'summary' => 'Что делать до, во время и после',
            'hazard_label' => 'Землетрясение',
        ])
        ->and($this->getJson("/api/v1/alerts/{$alert->slug}?locale=ru")->json('data.districts'))->toBe([]);

    // No English version of the guides: no links on /en.
    expect($this->getJson("/api/v1/alerts/{$alert->slug}?locale=en")->json('data.related_instructions'))->toBe([]);
});
