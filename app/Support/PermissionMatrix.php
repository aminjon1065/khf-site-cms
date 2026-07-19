<?php

namespace App\Support;

use App\Enums\Module;
use App\Enums\PermissionAction;
use App\Enums\RoleName;

/**
 * Single source of truth for the module × action permission matrix and the
 * per-role grants. Consumed by the roles seeder, policies and the
 * "Роли и права" screen so backend and UI never drift.
 */
class PermissionMatrix
{
    /**
     * Every permission name in `module.action` form.
     *
     * @return array<int, string>
     */
    public static function all(): array
    {
        $permissions = [];

        foreach (Module::cases() as $module) {
            foreach (PermissionAction::cases() as $action) {
                $permissions[] = self::name($module, $action);
            }
        }

        return $permissions;
    }

    public static function name(Module $module, PermissionAction $action): string
    {
        return $module->value.'.'.$action->value;
    }

    /**
     * Grants per role as [module => [action => bool]].
     * `superadmin` is intentionally omitted — it is granted every permission
     * and additionally short-circuits every gate via Gate::before().
     *
     * @return array<string, array<string, array<string, bool>>>
     */
    public static function grants(): array
    {
        // action shortcuts
        $v = PermissionAction::View->value;
        $c = PermissionAction::Create->value;
        $e = PermissionAction::Edit->value;
        $d = PermissionAction::Delete->value;
        $p = PermissionAction::Publish->value;
        $a = PermissionAction::Approve->value;

        $none = [];
        $viewOnly = [$v => true];

        return [
            RoleName::Admin->value => self::fill(true),

            RoleName::ChiefEditor->value => [
                Module::Alerts->value => [$v => true, $c => true, $e => true, $p => true, $a => true],
                Module::News->value => [$v => true, $c => true, $e => true, $d => true, $p => true, $a => true],
                Module::Instructions->value => [$v => true, $c => true, $e => true, $p => true, $a => true],
                Module::Documents->value => [$v => true, $c => true, $e => true, $d => true, $p => true],
                Module::Media->value => [$v => true, $c => true, $e => true, $d => true],
                Module::Home->value => [$v => true, $e => true, $p => true, $a => true],
                Module::Users->value => $viewOnly,
                Module::Settings->value => $none,
            ],

            RoleName::Editor->value => [
                Module::Alerts->value => [$v => true, $c => true, $e => true],
                Module::News->value => [$v => true, $c => true, $e => true, $p => true],
                Module::Instructions->value => [$v => true, $c => true, $e => true],
                Module::Documents->value => [$v => true, $c => true, $e => true],
                Module::Media->value => [$v => true, $c => true, $e => true],
                Module::Home->value => $viewOnly,
                Module::Users->value => $none,
                Module::Settings->value => $none,
            ],

            RoleName::AlertOperator->value => [
                Module::Alerts->value => [$v => true, $c => true, $e => true, $d => true, $p => true, $a => true],
                Module::News->value => $viewOnly,
                Module::Instructions->value => [$v => true, $e => true],
                Module::Documents->value => $viewOnly,
                Module::Media->value => [$v => true, $c => true],
                Module::Home->value => $viewOnly,
                Module::Users->value => $none,
                Module::Settings->value => $none,
            ],

            RoleName::Translator->value => [
                Module::Alerts->value => [$v => true, $e => true],
                Module::News->value => [$v => true, $e => true],
                Module::Instructions->value => [$v => true, $e => true],
                Module::Documents->value => $viewOnly,
                Module::Media->value => $viewOnly,
                Module::Home->value => $none,
                Module::Users->value => $none,
                Module::Settings->value => $none,
            ],

            RoleName::RegionalEditor->value => [
                Module::Alerts->value => [$v => true, $c => true, $e => true],
                Module::News->value => [$v => true, $c => true, $e => true],
                Module::Instructions->value => $viewOnly,
                Module::Documents->value => [$v => true, $c => true],
                Module::Media->value => [$v => true, $c => true],
                Module::Home->value => $none,
                Module::Users->value => $none,
                Module::Settings->value => $none,
            ],

            RoleName::Approver->value => [
                Module::Alerts->value => [$v => true, $p => true, $a => true],
                Module::News->value => [$v => true, $p => true, $a => true],
                Module::Instructions->value => [$v => true, $p => true, $a => true],
                Module::Documents->value => [$v => true, $a => true],
                Module::Media->value => $viewOnly,
                Module::Home->value => [$v => true, $a => true],
                Module::Users->value => $none,
                Module::Settings->value => $none,
            ],

            RoleName::Viewer->value => [
                Module::Alerts->value => $viewOnly,
                Module::News->value => $viewOnly,
                Module::Instructions->value => $viewOnly,
                Module::Documents->value => $viewOnly,
                Module::Media->value => $viewOnly,
                Module::Home->value => $viewOnly,
                Module::Users->value => $none,
                Module::Settings->value => $none,
            ],
        ];
    }

    /**
     * The permission names granted to a role.
     *
     * @return array<int, string>
     */
    public static function permissionsFor(RoleName $role): array
    {
        if ($role === RoleName::Superadmin) {
            return self::all();
        }

        $grants = self::grants()[$role->value] ?? [];
        $names = [];

        foreach ($grants as $module => $actions) {
            foreach ($actions as $action => $allowed) {
                if ($allowed) {
                    $names[] = $module.'.'.$action;
                }
            }
        }

        return $names;
    }

    /**
     * A rectangular [module => [action => bool]] map for a role, with every
     * cell present (defaults false). Used to render the UI matrix.
     *
     * @return array<string, array<string, bool>>
     */
    public static function matrixFor(RoleName $role): array
    {
        $granted = self::grants()[$role->value] ?? ($role === RoleName::Superadmin ? null : []);
        $matrix = [];

        foreach (Module::cases() as $module) {
            foreach (PermissionAction::cases() as $action) {
                $matrix[$module->value][$action->value] = $role === RoleName::Superadmin
                    ? true
                    : (bool) ($granted[$module->value][$action->value] ?? false);
            }
        }

        return $matrix;
    }

    /**
     * Build a full grant map with every module/action set to a fixed value.
     *
     * @return array<string, array<string, bool>>
     */
    private static function fill(bool $value): array
    {
        $map = [];

        foreach (Module::cases() as $module) {
            foreach (PermissionAction::cases() as $action) {
                $map[$module->value][$action->value] = $value;
            }
        }

        return $map;
    }
}
