<?php

use App\Models\Alert;
use App\Models\Region;
use App\Models\User;
use Database\Seeders\RegionSeeder;
use Database\Seeders\RolePermissionSeeder;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;

beforeEach(function () {
    seed([RolePermissionSeeder::class, RegionSeeder::class]);
});

function userWithRole(string $role, ?int $regionId = null): User
{
    $user = User::factory()->create(['region_id' => $regionId]);
    $user->assignRole($role);

    return $user;
}

it('lets an editor open the alert wizard', function () {
    actingAs(userWithRole('editor'))->get('/alerts/create')->assertOk();
});

it('forbids a viewer from creating an alert', function () {
    actingAs(userWithRole('viewer'))->get('/alerts/create')->assertForbidden();
});

it('forbids a translator from deleting an alert', function () {
    $alert = Alert::factory()->create();
    actingAs(userWithRole('translator'))->delete("/alerts/{$alert->id}")->assertForbidden();
});

it('lets a chief editor create an alert draft', function () {
    $region = Region::query()->where('code', 'khatlon')->first();

    actingAs(userWithRole('chief_editor'))
        ->post('/alerts', [
            'internal_title' => 'Тестовое предупреждение',
            'hazard_type' => 'mudflow',
            'severity' => 'warning',
            'territory_type' => 'regions',
            'regions' => [$region->id],
            'title' => ['ru' => 'Заголовок'],
            'action' => 'draft',
        ])
        ->assertRedirect('/alerts');

    expect(Alert::query()->where('internal_title', 'Тестовое предупреждение')->exists())->toBeTrue();
});

it('confines a regional editor to alerts in their own region', function () {
    $khatlon = Region::query()->where('code', 'khatlon')->first();
    $sughd = Region::query()->where('code', 'sughd')->first();

    $regional = userWithRole('regional_editor', $khatlon->id);

    $inRegion = Alert::factory()->create();
    $inRegion->regions()->attach($khatlon->id);

    $outOfRegion = Alert::factory()->create();
    $outOfRegion->regions()->attach($sughd->id);

    actingAs($regional)->put("/alerts/{$outOfRegion->id}", [
        'internal_title' => $outOfRegion->internal_title,
        'hazard_type' => $outOfRegion->hazard_type->value,
        'severity' => $outOfRegion->severity->value,
        'territory_type' => 'regions',
    ])->assertForbidden();

    actingAs($regional)->get('/alerts')->assertOk();
});
