<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Three built-in roles instead of nine fixed ones; any others are built by
 * the administrator on the «Роли и права» screen (owner decision, 2026-09-24).
 *
 * - «Суперадминистратор» merges into «Администратор»: one administrator role
 *   with every right.
 * - A former fixed role that still has people in it stays, with the same
 *   rights, as a role the administrator may change or remove — nobody's
 *   access grows or shrinks by itself. Empty ones go away.
 * - «Главный редактор» takes over what only the removed roles could do:
 *   approve documents, move alerts and instructions to the trash.
 *
 * A fresh installation has no roles yet: RolePermissionSeeder creates them.
 */
return new class extends Migration
{
    private const GUARD = 'web';

    /**
     * @var array<string, array{0: string, 1: string}>
     */
    private const BUILT_IN = [
        'admin' => ['Администратор', 'Всё, включая пользователей, роли и настройки системы'],
        'chief_editor' => ['Главный редактор', 'Все материалы сайта: публикует, согласует чужие и удаляет'],
        'editor' => ['Редактор', 'Готовит материалы. Новости, проекты, объявления и страницы публикует сам, предупреждения, инструкции и документы — через согласование'],
    ];

    /**
     * @var array<string, array{0: string, 1: string}>
     */
    private const FORMER = [
        'alert_operator' => ['Оператор предупреждений', 'Оперативная публикация предупреждений, включая критические'],
        'translator' => ['Переводчик', 'Перевод материалов на три языка'],
        'regional_editor' => ['Региональный редактор', 'Материалы своего региона; публикация — через согласование'],
        'approver' => ['Согласующий', 'Согласование и публикация подготовленных материалов'],
        'viewer' => ['Наблюдатель', 'Только просмотр без права редактирования'],
    ];

    private const CHIEF_EDITOR_GAINS = ['alerts.delete', 'instructions.delete', 'documents.approve'];

    public function up(): void
    {
        if (DB::table($this->roles())->doesntExist()) {
            return;
        }

        $admin = $this->mergeSuperadminInto('admin');
        $this->attach($admin, DB::table($this->permissions())->where('guard_name', self::GUARD)->pluck('id'));
        $this->label();
        $this->dropEmptyFormerRoles();

        $chiefEditor = $this->roleId('chief_editor');
        if ($chiefEditor !== null) {
            $this->grant($chiefEditor, self::CHIEF_EDITOR_GAINS);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // The merge is one-way: which administrators were «super» isn't kept.
    }

    private function mergeSuperadminInto(string $name): int
    {
        $target = $this->roleId($name) ?? DB::table($this->roles())->insertGetId([
            'name' => $name,
            'guard_name' => self::GUARD,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $superadmin = $this->roleId('superadmin');

        if ($superadmin === null) {
            return $target;
        }

        $roleKey = $this->roleKey();

        foreach (DB::table($this->modelHasRoles())->where($roleKey, $superadmin)->get() as $assignment) {
            $row = (array) $assignment;
            $row[$roleKey] = $target;

            DB::table($this->modelHasRoles())->insertOrIgnore($row);
        }

        $this->deleteRole($superadmin);

        return $target;
    }

    private function label(): void
    {
        foreach ([...self::BUILT_IN, ...self::FORMER] as $name => [$label, $description]) {
            DB::table($this->roles())
                ->where('name', $name)
                ->where('guard_name', self::GUARD)
                ->whereNull('label')
                ->update(['label' => $label, 'description' => $description]);
        }
    }

    private function dropEmptyFormerRoles(): void
    {
        foreach (array_keys(self::FORMER) as $name) {
            $role = $this->roleId($name);

            if ($role !== null && DB::table($this->modelHasRoles())->where($this->roleKey(), $role)->doesntExist()) {
                $this->deleteRole($role);
            }
        }
    }

    /**
     * @param  list<string>  $permissionNames
     */
    private function grant(int $role, array $permissionNames): void
    {
        $this->attach($role, DB::table($this->permissions())
            ->where('guard_name', self::GUARD)
            ->whereIn('name', $permissionNames)
            ->pluck('id'));
    }

    /**
     * @param  Collection<int, mixed>  $permissionIds
     */
    private function attach(int $role, Collection $permissionIds): void
    {
        $permissionKey = (string) (config('permission.column_names.permission_pivot_key') ?? 'permission_id');

        foreach ($permissionIds as $permissionId) {
            DB::table($this->roleHasPermissions())->insertOrIgnore([
                $permissionKey => $permissionId,
                $this->roleKey() => $role,
            ]);
        }
    }

    private function deleteRole(int $role): void
    {
        DB::table($this->modelHasRoles())->where($this->roleKey(), $role)->delete();
        DB::table($this->roleHasPermissions())->where($this->roleKey(), $role)->delete();
        DB::table($this->roles())->where('id', $role)->delete();
    }

    private function roleId(string $name): ?int
    {
        $id = DB::table($this->roles())->where('name', $name)->where('guard_name', self::GUARD)->value('id');

        return $id === null ? null : (int) $id;
    }

    private function roleKey(): string
    {
        return (string) (config('permission.column_names.role_pivot_key') ?? 'role_id');
    }

    private function roles(): string
    {
        return (string) config('permission.table_names.roles', 'roles');
    }

    private function permissions(): string
    {
        return (string) config('permission.table_names.permissions', 'permissions');
    }

    private function modelHasRoles(): string
    {
        return (string) config('permission.table_names.model_has_roles', 'model_has_roles');
    }

    private function roleHasPermissions(): string
    {
        return (string) config('permission.table_names.role_has_permissions', 'role_has_permissions');
    }
};
