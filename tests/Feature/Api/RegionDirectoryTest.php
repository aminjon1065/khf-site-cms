<?php

use Database\Seeders\RegionSeeder;

use function Pest\Laravel\seed;

beforeEach(function () {
    seed(RegionSeeder::class);
});

it('returns the regional-management directory', function () {
    $data = $this->getJson('/api/v1/regions/directory?locale=ru')->assertOk()->json('data');

    expect($data)->toBeArray()->toHaveCount(5)
        ->and($data[0])->toHaveKeys([
            'code', 'name', 'type', 'head', 'regional_center',
            'address', 'phone', 'phone_href', 'duty_phone', 'email',
            'districts_count', 'districts',
        ])
        ->and($data[0]['districts'])->toBeArray();
});

it('resolves directory fields to the requested locale', function () {
    $ru = $this->getJson('/api/v1/regions/directory?locale=ru')->json('data');
    $sughd = collect($ru)->firstWhere('code', 'sughd');

    expect($sughd['name'])->toBe('Согдийская область')
        ->and($sughd['head'])->toBe('Управление по Согдийской области')
        ->and($sughd['email'])->toBe('sughd@khf.tj')
        ->and($sughd['phone_href'])->toBe('+992342264471');

    // РРП uses the compact map/contacts label; the full designation is `head`.
    $rrp = collect($ru)->firstWhere('code', 'rrp');
    expect($rrp['name'])->toBe('РРП')
        ->and($rrp['head'])->toBe('Управление по районам республиканского подчинения');
});

it('exposes curated districts as localized names', function () {
    $data = $this->getJson('/api/v1/regions/directory?locale=ru')->json('data');
    $dushanbe = collect($data)->firstWhere('code', 'dushanbe');

    expect($dushanbe['districts'])->toContain('Исмоили Сомони', 'Сино');
});

it('keeps the map status endpoint separate from the directory', function () {
    $status = $this->getJson('/api/v1/regions')->assertOk()->json('data');

    // The status endpoint returns level/count per region, not office contacts.
    expect($status[0])->toHaveKeys(['key', 'name', 'level', 'count'])
        ->and($status[0])->not->toHaveKey('email');
});
