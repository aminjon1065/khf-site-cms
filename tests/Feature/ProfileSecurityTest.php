<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

it('renders and updates the authenticated users profile', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/profile')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('settings/profile'));

    $this->actingAs($user)->patch('/profile', [
        'name' => 'Обновлённое имя',
        'email' => 'updated@khf.tj',
    ])->assertRedirect('/profile');

    expect($user->refresh()->name)->toBe('Обновлённое имя')
        ->and($user->email)->toBe('updated@khf.tj');
});

it('requires recent password confirmation before opening security settings', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/profile/security')
        ->assertRedirect(route('password.confirm'));
});

it('renders security settings after password confirmation', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get('/profile/security')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/security')
            ->where('twoFactor.enabled', false)
            ->where('twoFactor.pending', false));
});

it('updates the password with the current password', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->put('/profile/password', [
        'current_password' => 'password',
        'password' => 'New-secure-password-2026!',
        'password_confirmation' => 'New-secure-password-2026!',
    ])->assertRedirect();

    expect(Hash::check('New-secure-password-2026!', $user->refresh()->password))->toBeTrue();
});

it('starts and cancels two factor authentication setup', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post('/user/two-factor-authentication')
        ->assertRedirect();

    expect($user->refresh()->two_factor_secret)->not->toBeNull()
        ->and($user->two_factor_confirmed_at)->toBeNull();

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->delete('/user/two-factor-authentication')
        ->assertRedirect();

    expect($user->refresh()->two_factor_secret)->toBeNull()
        ->and($user->two_factor_recovery_codes)->toBeNull();
});
