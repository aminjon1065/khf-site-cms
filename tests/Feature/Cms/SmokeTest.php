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

it('renders the section stub and settings/profile stubs', function () {
    $user = admin();
    actingAs($user)->get('/section/pages')->assertOk();
    actingAs($user)->get('/control')->assertOk();
    actingAs($user)->get('/approvals')->assertOk();
});

it('logs in directly without a two-factor challenge (2FA disabled for testing)', function () {
    $user = User::factory()->withTwoFactor()->create(['password' => bcrypt('password')]);

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect('/dashboard');

    $this->assertAuthenticatedAs($user);
});
