<?php

use App\Enums\RegionType;
use App\Models\Activity;
use App\Models\Alert;
use App\Models\District;
use App\Models\Region;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;

beforeEach(function () {
    seed(RolePermissionSeeder::class);
});

function regionUser(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

function makeRegion(string $code = 'test-region', int $sort = 50): Region
{
    return Region::create([
        'name' => ['ru' => 'Тестовый регион'],
        'code' => $code,
        'type' => RegionType::Oblast,
        'districts_count' => 5,
        'sort' => $sort,
    ]);
}

it('lets an admin open the regions list', function () {
    actingAs(regionUser('admin'))->get('/regions')->assertOk();
});

it('lets a chief editor view the regions list (view-only grant)', function () {
    actingAs(regionUser('chief_editor'))->get('/regions')->assertOk();
});

it('forbids a role without regions access', function () {
    actingAs(regionUser('editor'))->get('/regions')->assertForbidden();
});

it('creates a region with curated districts', function () {
    actingAs(regionUser('admin'))->post('/regions', [
        'name' => ['ru' => 'Согдийская область', 'tg' => 'вилояти Суғд'],
        'head' => ['ru' => 'Управление по Согдийской области'],
        'address' => ['ru' => 'г. Худжанд'],
        'code' => 'sughd-x',
        'type' => 'oblast',
        'regional_center' => 'Худжанд',
        'phone' => '+992 (3422) 6-44-71',
        'email' => 'sughd@khf.tj',
        'districts_count' => 18,
        'sort' => 1,
        'districts' => [
            ['id' => null, 'name' => ['ru' => 'Худжанд']],
            ['id' => null, 'name' => ['ru' => 'Исфара']],
        ],
    ])->assertRedirect('/regions');

    $region = Region::query()->where('code', 'sughd-x')->first();

    expect($region)->not->toBeNull()
        ->and($region->getTranslation('name', 'ru'))->toBe('Согдийская область')
        ->and($region->getTranslation('head', 'ru'))->toBe('Управление по Согдийской области')
        ->and($region->email)->toBe('sughd@khf.tj')
        ->and($region->districts()->count())->toBe(2)
        ->and(Activity::query()->where('log_name', 'regions')->where('subject_id', $region->id)->exists())->toBeTrue();
});

it('forbids a view-only role from creating a region', function () {
    actingAs(regionUser('chief_editor'))->post('/regions', [
        'name' => ['ru' => 'Взлом'],
        'code' => 'hack',
        'type' => 'oblast',
        'districts_count' => 0,
    ])->assertForbidden();
});

it('syncs districts on update: adds new and removes omitted', function () {
    $region = makeRegion('sync-region');
    $keep = District::create(['region_id' => $region->id, 'name' => ['ru' => 'Оставить'], 'sort' => 0]);
    $drop = District::create(['region_id' => $region->id, 'name' => ['ru' => 'Удалить'], 'sort' => 1]);

    actingAs(regionUser('admin'))->put("/regions/{$region->id}", [
        'name' => ['ru' => 'Тестовый регион'],
        'code' => 'sync-region',
        'type' => 'oblast',
        'districts_count' => 5,
        'districts' => [
            ['id' => $keep->id, 'name' => ['ru' => 'Оставить']],
            ['id' => null, 'name' => ['ru' => 'Новый район']],
        ],
    ])->assertRedirect('/regions');

    expect(District::query()->find($drop->id))->toBeNull()
        ->and(District::query()->find($keep->id))->not->toBeNull()
        ->and($region->districts()->count())->toBe(2)
        ->and($region->districts()->where('name->ru', 'Новый район')->exists())->toBeTrue();
});

it('rejects a district named only in a non-Russian language instead of dropping it', function () {
    $region = makeRegion('drop-region');

    actingAs(regionUser('admin'))->put("/regions/{$region->id}", [
        'name' => ['ru' => 'Тестовый регион'],
        'code' => 'drop-region',
        'type' => 'oblast',
        'districts_count' => 5,
        'districts' => [
            ['id' => null, 'name' => ['ru' => '', 'en' => 'Orphan']],
        ],
    ])->assertSessionHasErrors('districts.0.name.ru');

    expect($region->districts()->count())->toBe(0);
});

it('silently ignores a fully empty district row', function () {
    $region = makeRegion('empty-row-region');

    actingAs(regionUser('admin'))->put("/regions/{$region->id}", [
        'name' => ['ru' => 'Тестовый регион'],
        'code' => 'empty-row-region',
        'type' => 'oblast',
        'districts_count' => 5,
        'districts' => [
            ['id' => null, 'name' => ['ru' => '', 'tg' => '', 'en' => '']],
        ],
    ])->assertRedirect('/regions');

    expect($region->districts()->count())->toBe(0);
});

it('blocks deleting a region that is used by an alert', function () {
    $region = makeRegion('used-region');
    $alert = Alert::factory()->create();
    $region->alerts()->attach($alert->id);

    actingAs(regionUser('admin'))->delete("/regions/{$region->id}")->assertRedirect();

    expect(Region::query()->find($region->id))->not->toBeNull();
});

it('deletes an unused region and cascades its districts', function () {
    $region = makeRegion('free-region');
    District::create(['region_id' => $region->id, 'name' => ['ru' => 'Район'], 'sort' => 0]);

    actingAs(regionUser('admin'))->delete("/regions/{$region->id}")->assertRedirect('/regions');

    expect(Region::query()->find($region->id))->toBeNull()
        ->and(District::query()->where('region_id', $region->id)->count())->toBe(0);
});

it('forbids a view-only role from deleting a region', function () {
    $region = makeRegion('protected-region');

    actingAs(regionUser('chief_editor'))->delete("/regions/{$region->id}")->assertForbidden();

    expect(Region::query()->find($region->id))->not->toBeNull();
});

it('rejects a duplicate region code', function () {
    makeRegion('dup-code');

    actingAs(regionUser('admin'))->post('/regions', [
        'name' => ['ru' => 'Другой'],
        'code' => 'dup-code',
        'type' => 'oblast',
        'districts_count' => 0,
    ])->assertSessionHasErrors('code');
});
