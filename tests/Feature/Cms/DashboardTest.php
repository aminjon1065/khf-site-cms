<?php

use App\Models\Activity;
use App\Models\Alert;
use App\Models\News;
use App\Models\Region;
use App\Models\User;
use Database\Seeders\RegionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;

beforeEach(function () {
    seed([RolePermissionSeeder::class, RegionSeeder::class]);
});

function dashboardUser(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

it('hides recent audit activity from users without the users view permission', function () {
    Activity::query()->create([
        'log_name' => 'settings',
        'description' => 'Изменена настройка',
        'event' => 'updated',
    ]);

    actingAs(dashboardUser('editor'))->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->has('activity', 0));
});

it('shows recent audit activity to administrators', function () {
    Activity::query()->create([
        'log_name' => 'settings',
        'description' => 'Изменена настройка',
        'event' => 'updated',
    ]);

    actingAs(dashboardUser('admin'))->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->has('activity.0'));
});

it('limits dashboard data to the assigned region and own editorial content', function () {
    $assignedRegion = Region::query()->where('code', 'khatlon')->firstOrFail();
    $foreignRegion = Region::query()->where('code', 'sughd')->firstOrFail();
    $user = dashboardUser('regional_editor');
    $user->update(['region_id' => $assignedRegion->id]);

    $ownAlert = Alert::factory()->published()->create([
        'starts_at' => now()->subHour(),
        'ends_at' => now()->addHour(),
    ]);
    $ownAlert->regions()->attach($assignedRegion);

    $foreignAlert = Alert::factory()->published()->create([
        'starts_at' => now()->subHour(),
        'ends_at' => now()->addHour(),
    ]);
    $foreignAlert->regions()->attach($foreignRegion);

    News::factory()->create(['author_id' => $user->id, 'status' => 'draft']);
    News::factory()->create(['author_id' => User::factory()->create()->id, 'status' => 'draft']);

    actingAs($user)->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('metrics.0.value', 1)
            ->where('metrics.1.value', 1)
            ->has('activeAlerts', 1)
            ->has('regionStatuses', 1));
});
