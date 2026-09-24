<?php

use App\Models\Role;
use App\Models\User;
use App\Support\PermissionMatrix;
use Spatie\Permission\Models\Permission;

/*
 * An installation from before the three roles (2026-09-24): nine fixed
 * roles. The migration merges the superadministrator into the administrator,
 * keeps former roles that still have people in them — with their rights —
 * and drops the empty ones.
 */

function consolidateRoles(): void
{
    (require database_path('migrations/2026_09_24_111048_consolidate_roles.php'))->up();
}

/**
 * @param  list<string>  $permissions
 */
function formerRole(string $name, array $permissions): Role
{
    $role = Role::query()->create(['name' => $name, 'guard_name' => 'web']);
    $role->syncPermissions($permissions);

    return $role;
}

beforeEach(function () {
    foreach (PermissionMatrix::all() as $name) {
        Permission::findOrCreate($name, 'web');
    }

    formerRole('superadmin', PermissionMatrix::all());
    formerRole('admin', PermissionMatrix::all());
    formerRole('chief_editor', ['news.view', 'news.publish', 'documents.view', 'documents.publish']);
    formerRole('editor', ['news.view', 'news.create', 'news.edit', 'news.publish']);
    formerRole('translator', ['news.view', 'news.edit']);
    formerRole('viewer', ['news.view']);
});

it('merges the superadministrator into the administrator', function () {
    $owner = User::factory()->create();
    $owner->assignRole('superadmin');

    consolidateRoles();

    expect(Role::query()->where('name', 'superadmin')->exists())->toBeFalse()
        ->and($owner->fresh()->hasRole('admin'))->toBeTrue()
        ->and(Role::query()->where('name', 'admin')->sole()->label)->toBe('Администратор');
});

it('keeps a former role that has people in it, with the same rights', function () {
    $translator = User::factory()->create();
    $translator->assignRole('translator');

    consolidateRoles();

    $role = Role::query()->where('name', 'translator')->sole();

    expect($role->label)->toBe('Переводчик')
        ->and($role->permissions->pluck('name')->sort()->values()->all())->toBe(['news.edit', 'news.view'])
        ->and($translator->fresh()->hasRole('translator'))->toBeTrue()
        ->and($translator->fresh()->can('news.publish'))->toBeFalse();
});

it('drops former roles nobody holds', function () {
    consolidateRoles();

    expect(Role::query()->orderBy('name')->pluck('name')->all())->toBe(['admin', 'chief_editor', 'editor']);
});

it('lets the chief editor approve documents and move alerts and instructions to the trash', function () {
    consolidateRoles();

    $chief = Role::query()->where('name', 'chief_editor')->sole();

    expect($chief->hasPermissionTo('documents.approve'))->toBeTrue()
        ->and($chief->hasPermissionTo('alerts.delete'))->toBeTrue()
        ->and($chief->hasPermissionTo('instructions.delete'))->toBeTrue()
        // What the chief editor had stays.
        ->and($chief->hasPermissionTo('news.publish'))->toBeTrue()
        ->and($chief->label)->toBe('Главный редактор');
});

it('leaves a new installation to the seeder', function () {
    Role::query()->each(fn (Role $role) => $role->delete());

    consolidateRoles();

    expect(Role::query()->count())->toBe(0);
});
