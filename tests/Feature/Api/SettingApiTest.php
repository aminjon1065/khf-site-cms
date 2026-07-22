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

it('returns organisation, footer and SEO copy in the requested locale', function () {
    $tg = $this->getJson('/api/v1/settings?locale=tg')->assertOk();
    $en = $this->getJson('/api/v1/settings?locale=en')->assertOk();

    $tg->assertJsonPath('data.org.short_name', 'КҲФ ва МГ Ҷумҳурии Тоҷикистон')
        ->assertJsonPath('data.emergency_services.0.label', 'хадамоти ягонаи наҷот')
        ->assertJsonPath('data.seo.meta_title', 'КҲФ ва МГ Ҷумҳурии Тоҷикистон')
        ->assertJsonPath('meta.requested_locale', 'tg')
        ->assertJsonPath('meta.fallback_used', false);

    $en->assertJsonPath('data.org.short_name', 'CESCD of Tajikistan')
        ->assertJsonPath('data.emergency_services.0.label', 'unified rescue service')
        ->assertJsonPath('data.seo.meta_title', 'CESCD of the Republic of Tajikistan')
        ->assertJsonPath('meta.requested_locale', 'en')
        ->assertJsonPath('meta.fallback_used', false);
});

it('marks a locale fallback explicitly', function () {
    $setting = Setting::query()
        ->where('group', 'org')
        ->where('key', 'about_en')
        ->firstOrFail();
    $setting->value = '';
    $setting->save();

    $this->getJson('/api/v1/settings?locale=en')
        ->assertOk()
        ->assertJsonPath('data.org.about', 'Государственный орган по предупреждению и ликвидации чрезвычайных ситуаций, защите населения и территорий Республики Таджикистан.')
        ->assertJsonPath('meta.fallback_used', true);
});

it('returns social links as a keyed map', function () {
    $data = $this->getJson('/api/v1/settings')->json('data');

    expect($data['social'])->toBeArray()
        ->and($data['social'])->toHaveKey('telegram');
});
