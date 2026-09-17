<?php

use App\Models\Activity;
use App\Models\StructureUnit;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;

beforeEach(function () {
    seed(RolePermissionSeeder::class);
});

function structureUser(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

it('lets an admin open the structure list', function () {
    actingAs(structureUser('admin'))->get('/structure')->assertOk();
});

it('lets a chief editor view the structure list (view-only grant)', function () {
    actingAs(structureUser('chief_editor'))->get('/structure')->assertOk();
});

it('forbids a role without structure access', function () {
    actingAs(structureUser('editor'))->get('/structure')->assertForbidden();
});

it('forbids a view-only role from creating a structure unit', function () {
    actingAs(structureUser('chief_editor'))->post('/structure', [
        'num' => '99',
        'name' => ['ru' => 'Взлом'],
        'desc' => ['ru' => 'Взлом'],
    ])->assertForbidden();
});

it('creates a structure unit', function () {
    actingAs(structureUser('admin'))->post('/structure', [
        'num' => '07',
        'name' => ['ru' => 'Тестовое подразделение', 'tg' => 'Воҳиди санҷишӣ'],
        'desc' => ['ru' => 'Описание тестового подразделения.'],
        'sort' => 7,
    ])->assertRedirect('/structure');

    $unit = StructureUnit::query()->where('num', '07')->first();

    expect($unit)->not->toBeNull()
        ->and($unit->getTranslation('name', 'ru'))->toBe('Тестовое подразделение')
        ->and($unit->getTranslation('name', 'tg'))->toBe('Воҳиди санҷишӣ')
        ->and($unit->sort)->toBe(7)
        ->and(Activity::query()->where('log_name', 'structure_units')->where('subject_id', $unit->id)->exists())->toBeTrue();
});

it('rejects a structure unit without a Russian name, description, or number', function () {
    actingAs(structureUser('admin'))->post('/structure', [
        'num' => '',
        'name' => ['ru' => ''],
        'desc' => ['ru' => ''],
    ])->assertSessionHasErrors(['num', 'name.ru', 'desc.ru']);

    expect(StructureUnit::query()->count())->toBe(0);
});

it('updates a structure unit', function () {
    $unit = StructureUnit::factory()->create(['num' => '08']);

    actingAs(structureUser('admin'))->put("/structure/{$unit->id}", [
        'num' => '08',
        'name' => ['ru' => 'Обновлённое название'],
        'desc' => $unit->getTranslations('desc'),
        'sort' => 3,
    ])->assertRedirect('/structure');

    expect($unit->refresh()->getTranslation('name', 'ru'))->toBe('Обновлённое название')
        ->and($unit->sort)->toBe(3);
});

it('deletes a structure unit', function () {
    $unit = StructureUnit::factory()->create();

    actingAs(structureUser('admin'))->delete("/structure/{$unit->id}")->assertRedirect('/structure');

    expect(StructureUnit::query()->find($unit->id))->toBeNull();
});

it('forbids a view-only role from deleting a structure unit', function () {
    $unit = StructureUnit::factory()->create();

    actingAs(structureUser('chief_editor'))->delete("/structure/{$unit->id}")->assertForbidden();

    expect(StructureUnit::query()->find($unit->id))->not->toBeNull();
});

it('creates a subunit under its parent', function () {
    $directorate = StructureUnit::factory()->create(['num' => '02']);

    actingAs(structureUser('admin'))->post('/structure', [
        'parent_id' => $directorate->id,
        'num' => '02.1',
        'name' => ['ru' => 'Отдел планирования'],
        'desc' => ['ru' => 'Планы гражданской обороны.'],
    ])->assertRedirect('/structure');

    expect(StructureUnit::query()->where('num', '02.1')->sole()->parent_id)->toBe($directorate->id)
        ->and($directorate->children()->pluck('num')->all())->toBe(['02.1']);
});

it('lists units as a tree, each subunit right under its parent', function () {
    $second = StructureUnit::factory()->create(['num' => '02', 'sort' => 2]);
    $first = StructureUnit::factory()->create(['num' => '01', 'sort' => 1]);
    $department = StructureUnit::factory()->childOf($first)->create(['num' => '01.1', 'sort' => 1]);
    StructureUnit::factory()->childOf($department)->create(['num' => '01.1.1', 'sort' => 1]);

    actingAs(structureUser('admin'))->get('/structure')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('structure/index')
            ->where('units', fn ($units): bool => collect($units)
                ->map(fn (array $unit): array => [$unit['num'], $unit['depth'], $unit['children_count']])
                ->all() === [['01', 0, 1], ['01.1', 1, 1], ['01.1.1', 2, 0], ['02', 0, 0]])
            ->where('units.3.id', $second->id));
});

it('refuses to place a unit under itself or under one of its own subunits', function () {
    $directorate = StructureUnit::factory()->create();
    $department = StructureUnit::factory()->childOf($directorate)->create();
    $sector = StructureUnit::factory()->childOf($department)->create();
    $admin = structureUser('admin');
    $payload = fn (int $parentId): array => [
        'parent_id' => $parentId,
        'num' => $directorate->num,
        'name' => $directorate->getTranslations('name'),
        'desc' => $directorate->getTranslations('desc'),
    ];

    actingAs($admin)->put("/structure/{$directorate->id}", $payload($directorate->id))
        ->assertSessionHasErrors('parent_id');
    actingAs($admin)->put("/structure/{$directorate->id}", $payload($sector->id))
        ->assertSessionHasErrors(['parent_id' => 'Подразделение нельзя подчинить его собственному вложенному подразделению.']);

    expect($directorate->refresh()->parent_id)->toBeNull();
});

it('refuses to delete a unit that still has subunits', function () {
    $directorate = StructureUnit::factory()->create();
    StructureUnit::factory()->childOf($directorate)->create();

    actingAs(structureUser('admin'))->delete("/structure/{$directorate->id}")
        ->assertRedirect('/structure')
        ->assertSessionHas('error');

    expect(StructureUnit::query()->find($directorate->id))->not->toBeNull();
});

it('offers every unit except the edited one and its subunits as a parent', function () {
    $directorate = StructureUnit::factory()->create(['num' => '01', 'sort' => 1]);
    StructureUnit::factory()->childOf($directorate)->create(['num' => '01.1']);
    $other = StructureUnit::factory()->create(['num' => '02', 'sort' => 2]);
    $otherDepartment = StructureUnit::factory()->childOf($other)->create(['num' => '02.1']);

    actingAs(structureUser('admin'))->get("/structure/{$directorate->id}/edit")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('parents', fn ($parents): bool => collect($parents)->pluck('value')->all() === [$other->id, $otherDepartment->id])
            ->where('parents.1.label', '— 02.1 '.$otherDepartment->getTranslation('name', 'ru')));
});

it('preselects the parent when a subunit is added from the list', function () {
    $directorate = StructureUnit::factory()->create();

    actingAs(structureUser('admin'))->get("/structure/create?parent={$directorate->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('defaultParentId', $directorate->id));
});
