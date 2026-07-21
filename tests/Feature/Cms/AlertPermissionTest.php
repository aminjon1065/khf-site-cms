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

it('rejects country-wide and foreign-region alerts from a regional editor', function () {
    $khatlon = Region::query()->where('code', 'khatlon')->firstOrFail();
    $sughd = Region::query()->where('code', 'sughd')->firstOrFail();
    $regional = userWithRole('regional_editor', $khatlon->id);

    actingAs($regional)->post('/alerts', [
        'internal_title' => 'Общенациональное предупреждение',
        'hazard_type' => 'mudflow',
        'severity' => 'warning',
        'territory_type' => 'country',
        'regions' => [],
        'title' => ['ru' => 'Заголовок'],
        'action' => 'draft',
    ])->assertSessionHasErrors('regions');

    actingAs($regional)->post('/alerts', [
        'internal_title' => 'Чужой регион',
        'hazard_type' => 'mudflow',
        'severity' => 'warning',
        'territory_type' => 'regions',
        'regions' => [$sughd->id],
        'title' => ['ru' => 'Заголовок'],
        'action' => 'draft',
    ])->assertSessionHasErrors('regions');

    expect(Alert::query()->count())->toBe(0);
});

it('prevents a regional editor from moving an alert to another region', function () {
    $khatlon = Region::query()->where('code', 'khatlon')->firstOrFail();
    $sughd = Region::query()->where('code', 'sughd')->firstOrFail();
    $regional = userWithRole('regional_editor', $khatlon->id);
    $alert = Alert::factory()->create(['author_id' => $regional->id]);
    $alert->regions()->attach($khatlon);

    actingAs($regional)->put("/alerts/{$alert->id}", [
        'internal_title' => $alert->internal_title,
        'hazard_type' => $alert->hazard_type->value,
        'severity' => $alert->severity->value,
        'territory_type' => 'regions',
        'regions' => [$sughd->id],
        'action' => 'draft',
    ])->assertSessionHasErrors('regions');

    expect($alert->regions()->pluck('regions.id')->all())->toBe([$khatlon->id]);
});

it('requires a future publication time for a scheduled alert', function () {
    $region = Region::query()->where('code', 'khatlon')->firstOrFail();

    actingAs(userWithRole('chief_editor'))->post('/alerts', [
        'internal_title' => 'Плановая публикация',
        'hazard_type' => 'mudflow',
        'severity' => 'warning',
        'territory_type' => 'regions',
        'regions' => [$region->id],
        'title' => ['ru' => 'Заголовок'],
        'action' => 'submit',
        'publish_mode' => 'schedule',
    ])->assertSessionHasErrors('scheduled_at');

    actingAs(userWithRole('chief_editor'))->post('/alerts', [
        'internal_title' => 'Плановая публикация',
        'hazard_type' => 'mudflow',
        'severity' => 'warning',
        'territory_type' => 'regions',
        'regions' => [$region->id],
        'title' => ['ru' => 'Заголовок'],
        'action' => 'submit',
        'publish_mode' => 'schedule',
        'scheduled_at' => now()->subMinute()->toDateTimeString(),
    ])->assertSessionHasErrors('scheduled_at');
});
