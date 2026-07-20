<?php

use App\Models\Setting;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;

beforeEach(function () {
    seed([RolePermissionSeeder::class, SettingSeeder::class]);
});

function settingUser(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

it('lets an admin open the settings screen', function () {
    actingAs(settingUser('admin'))->get('/settings')->assertOk();
});

it('forbids a role without settings access', function () {
    actingAs(settingUser('chief_editor'))->get('/settings')->assertForbidden();
});

it('persists whitelisted settings and ignores sensitive groups', function () {
    actingAs(settingUser('admin'))->put('/settings', [
        'settings' => [
            'org' => ['short_name_ru' => 'КЧС ТЕСТ'],
            'security' => ['api_key' => 'injected-secret'],
        ],
    ])->assertRedirect();

    expect(Setting::query()->where('group', 'org')->where('key', 'short_name_ru')->value('value'))
        ->toBe('КЧС ТЕСТ')
        ->and(Setting::query()->where('group', 'security')->where('key', 'api_key')->exists())
        ->toBeFalse();
});

it('forbids a non-admin from saving settings', function () {
    actingAs(settingUser('editor'))->put('/settings', [
        'settings' => ['org' => ['short_name_ru' => 'Взлом']],
    ])->assertForbidden();
});
