<?php

use App\Models\Alert;
use App\Models\Region;
use App\Models\User;
use Database\Seeders\RegionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;

beforeEach(function () {
    seed([RolePermissionSeeder::class, RegionSeeder::class, SettingSeeder::class]);
});

function controlUser(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

it('renders a live control center instead of a section stub', function () {
    Alert::factory()->published()->create([
        'starts_at' => now()->subHour(),
        'ends_at' => now()->addHour(),
    ]);

    actingAs(controlUser('viewer'))->get('/control')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('control/index')
            ->where('metrics.active', 1)
            ->has('alerts', 1)
            ->has('regions'));
});

it('forbids the control center without alerts permission', function () {
    actingAs(User::factory()->create())->get('/control')->assertForbidden();
});

it('limits the control center to the assigned region', function () {
    $assignedRegion = Region::query()->where('code', 'khatlon')->firstOrFail();
    $foreignRegion = Region::query()->where('code', 'sughd')->firstOrFail();
    $user = controlUser('regional_editor');
    $user->update(['region_id' => $assignedRegion->id]);

    $ownAlert = Alert::factory()->published()->create([
        'internal_title' => 'Своё предупреждение',
        'title' => ['ru' => 'Своё предупреждение', 'tg' => 'Огоҳии худ', 'en' => 'Own alert'],
        'starts_at' => now()->subHour(),
        'ends_at' => now()->addHour(),
    ]);
    $ownAlert->regions()->attach($assignedRegion);

    $foreignAlert = Alert::factory()->published()->create([
        'starts_at' => now()->subHour(),
        'ends_at' => now()->addHour(),
    ]);
    $foreignAlert->regions()->attach($foreignRegion);

    Alert::factory()->published()->create([
        'territory_type' => 'country',
        'starts_at' => now()->subHour(),
        'ends_at' => now()->addHour(),
    ]);

    actingAs($user)->get('/control')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('metrics.active', 1)
            ->has('regions', 1)
            ->has('alerts', 1)
            ->where('alerts.0.title', 'Своё предупреждение'));
});

it('renders central services and regional emergency contacts', function () {
    actingAs(controlUser('viewer'))->get('/contacts')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('contacts/index')
            ->where('central.emergency_number', '112')
            ->has('services', 4)
            ->has('regions'));
});
