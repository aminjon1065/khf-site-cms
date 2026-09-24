<?php

use App\Models\Role;
use App\Support\PermissionMatrix;
use Database\Seeders\RolePermissionSeeder;
use Spatie\Permission\Models\Permission;

use function Pest\Laravel\seed;

it('installs the three roles with their names and default rights', function () {
    seed(RolePermissionSeeder::class);

    $roles = Role::query()->orderBy('id')->get();

    expect($roles->pluck('name')->all())->toBe(['admin', 'chief_editor', 'editor'])
        ->and($roles->pluck('label')->all())->toBe(['Администратор', 'Главный редактор', 'Редактор'])
        ->and($roles[0]->permissions)->toHaveCount(count(PermissionMatrix::all()))
        ->and($roles[1]->hasPermissionTo('documents.approve'))->toBeTrue()
        ->and($roles[2]->hasPermissionTo('news.publish'))->toBeTrue()
        ->and($roles[2]->hasPermissionTo('alerts.publish'))->toBeFalse();
});

it('leaves the rights the administrator changed alone when run again', function () {
    seed(RolePermissionSeeder::class);
    Role::query()->where('name', 'editor')->sole()->revokePermissionTo('news.publish');

    seed(RolePermissionSeeder::class);

    expect(Role::query()->where('name', 'editor')->sole()->hasPermissionTo('news.publish'))->toBeFalse();
});

it('does not bring back a role the administrator removed', function () {
    seed(RolePermissionSeeder::class);
    Role::query()->where('name', 'chief_editor')->sole()->delete();

    seed(RolePermissionSeeder::class);

    expect(Role::query()->where('name', 'chief_editor')->exists())->toBeFalse();
});

it('gives the administrator every right, new ones included', function () {
    seed(RolePermissionSeeder::class);
    $admin = Role::query()->where('name', 'admin')->sole();
    $admin->revokePermissionTo('settings.edit');
    Permission::query()->where('name', 'home.edit')->delete();

    seed(RolePermissionSeeder::class);

    expect($admin->fresh()->hasPermissionTo('settings.edit'))->toBeTrue()
        ->and($admin->fresh()->hasPermissionTo('home.edit'))->toBeTrue();
});
