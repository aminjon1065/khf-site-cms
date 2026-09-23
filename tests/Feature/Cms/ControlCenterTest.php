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

it('shows the technical state of the system to administrators only', function () {
    $operator = User::factory()->withTwoFactor()->create();
    $operator->assignRole('alert_operator');
    $admin = User::factory()->withTwoFactor()->create();
    $admin->assignRole('admin');

    actingAs($operator)->get('/control')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('web_vitals', null)
            ->where('operations', null)
            ->where('pending_migrations', []));

    actingAs($admin)->get('/control')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('web_vitals.metrics', 3)
            ->where('web_vitals.total_samples', 0)
            ->has('operations.api')
            ->has('operations.queue'));
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

it('no longer serves the read-only copy of emergency contacts', function () {
    // Контакты редактируются там, откуда их берёт сайт: «Настройки»
    // (телефон доверия, адрес, почта, подписи 112–103) и «Регионы».
    actingAs(controlUser('viewer'))->get('/contacts')->assertNotFound();
});

it('keeps the RUM dashboard table semantically labelled', function () {
    $source = file_get_contents(resource_path('js/pages/control/index.tsx'));

    expect($source)->toContain(
        'section aria-labelledby="rum-heading"',
        '<caption className="sr-only">',
        'scope="col"',
        'scope="row"',
    );
});
