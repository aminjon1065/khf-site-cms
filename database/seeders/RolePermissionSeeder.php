<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Models\Role;
use App\Support\PermissionMatrix;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Permissions, and the three built-in roles of a new installation. Safe to
 * run again: the administrator's role gets every right (new ones included),
 * but the rights of the other roles are the administrator's — once the
 * roles exist, this never touches them. A new right for existing roles is
 * granted by a migration.
 */
class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (PermissionMatrix::all() as $name) {
            Permission::findOrCreate($name, 'web');
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $newInstallation = Role::query()->doesntExist();

        foreach (RoleName::cases() as $builtIn) {
            if (! $newInstallation && $builtIn !== RoleName::Admin) {
                continue;
            }

            $role = Role::query()->firstOrCreate(
                ['name' => $builtIn->value, 'guard_name' => 'web'],
                ['label' => $builtIn->label(), 'description' => $builtIn->description()],
            );

            if ($role->label === null) {
                $role->update(['label' => $builtIn->label(), 'description' => $builtIn->description()]);
            }

            $role->syncPermissions(PermissionMatrix::defaultsFor($builtIn));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
