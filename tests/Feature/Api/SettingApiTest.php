<?php

use App\Models\Setting;
use Database\Seeders\SettingSeeder;

use function Pest\Laravel\seed;

beforeEach(function () {
    seed(SettingSeeder::class);
});

it('exposes only whitelisted public settings groups', function () {
    $data = $this->getJson('/api/v1/settings')->assertOk()->json('data');

    expect($data)->toHaveKeys(['org', 'contacts', 'social', 'emergency_services', 'copyright', 'seo'])
        ->and($data)->not->toHaveKey('security')
        ->and($data)->not->toHaveKey('integrations')
        ->and($data)->not->toHaveKey('backup');
});

it('never leaks sensitive settings even when they exist in the database', function () {
    Setting::updateOrCreate(['group' => 'security', 'key' => 'api_key'], ['value' => 'super-secret-token']);
    Setting::updateOrCreate(['group' => 'integrations', 'key' => 'smtp_password'], ['value' => 'p@ssw0rd']);

    $body = $this->getJson('/api/v1/settings')->assertOk()->getContent();

    expect($body)->not->toContain('super-secret-token')
        ->and($body)->not->toContain('p@ssw0rd');
});

it('returns the emergency services list and organisation identity', function () {
    $data = $this->getJson('/api/v1/settings')->json('data');

    expect($data['emergency_services'])->toBeArray()->not->toBeEmpty()
        ->and($data['emergency_services'][0])->toHaveKeys(['num', 'label'])
        ->and($data['org']['short_name'])->not->toBe('')
        ->and($data['org']['emergency_number'])->toBe('112');
});

it('returns social links as a keyed map', function () {
    $data = $this->getJson('/api/v1/settings')->json('data');

    expect($data['social'])->toBeArray()
        ->and($data['social'])->toHaveKey('telegram');
});
