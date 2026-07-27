<?php

use App\Models\Activity;
use App\Models\StructureUnit;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

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
