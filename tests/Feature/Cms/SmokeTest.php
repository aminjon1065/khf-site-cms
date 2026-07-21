<?php

use App\Models\User;
use Database\Seeders\RegionSeeder;
use Database\Seeders\RolePermissionSeeder;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\seed;

beforeEach(function () {
    seed([RolePermissionSeeder::class, RegionSeeder::class]);
});

function admin(): User
{
    $user = User::factory()->create();
    $user->assignRole('superadmin');

    return $user;
}

it('renders the login screen for guests', function () {
    get('/login')->assertOk();
});

it('redirects guests away from the dashboard', function () {
    get('/dashboard')->assertRedirect('/login');
});

it('renders the dashboard for an authenticated user', function () {
    actingAs(admin())->get('/dashboard')->assertOk();
});

it('renders the alerts list', function () {
    actingAs(admin())->get('/alerts')->assertOk();
});

it('renders the alert wizard', function () {
    actingAs(admin())->get('/alerts/create')->assertOk();
});

it('renders the implemented operational workspace pages', function () {
    $user = admin();
    actingAs($user)->get('/control')->assertOk();
    actingAs($user)->get('/contacts')->assertOk();
    actingAs($user)->get('/notifications')->assertOk();
    actingAs($user)->get('/approvals')->assertOk();
});

it('requires a two-factor challenge for a protected account', function () {
    $user = User::factory()->withTwoFactor()->create(['password' => bcrypt('password')]);

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect('/two-factor-challenge');

    $this->assertGuest();
});
