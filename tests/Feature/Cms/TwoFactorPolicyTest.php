<?php

use App\Models\Setting;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;

beforeEach(function () {
    seed(RolePermissionSeeder::class);
    Setting::query()->updateOrCreate(
        ['group' => 'security', 'key' => 'require_2fa'],
        ['value' => true],
    );
    Setting::query()->updateOrCreate(
        ['group' => 'security', 'key' => 'require_2fa_from'],
        ['value' => now()->subDay()->toDateString()],
    );
});

function twoFactorPolicyUser(string $role, bool $enabled = false): User
{
    $user = User::factory()->create([
        'two_factor_confirmed_at' => $enabled ? now() : null,
    ]);
    $user->assignRole($role);

    return $user;
}

it('redirects a privileged user without 2FA to security settings', function () {
    actingAs(twoFactorPolicyUser('admin'))->get('/dashboard')
        ->assertRedirect('/profile/security')
        ->assertSessionHas('warning');
});

it('allows a privileged user after 2FA confirmation', function () {
    actingAs(twoFactorPolicyUser('admin', enabled: true))->get('/dashboard')->assertOk();
});

it('does not require 2FA from a view-only role', function () {
    actingAs(twoFactorPolicyUser('viewer'))->get('/dashboard')->assertOk();
});

it('keeps the security screen reachable while 2FA is required', function () {
    actingAs(twoFactorPolicyUser('admin'))->get('/profile/security')
        ->assertRedirect('/user/confirm-password');
});
