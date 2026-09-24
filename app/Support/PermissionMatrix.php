<?php

namespace App\Support;

use App\Enums\Module;
use App\Enums\PermissionAction;
use App\Enums\RoleName;

/**
 * The module × action permissions: which exist, which mean something for a
 * module, which only the administrator may hold, and what the built-in roles
 * start with. Consumed by the roles seeder, the «Роли и права» screen and
 * its editor, so the backend and the UI never drift.
 */
class PermissionMatrix
{
    /**
     * Rights over people's access and over the system stay with the
     * administrator: whoever could edit accounts could make themselves one.
     */
    public const ADMINISTRATOR_ONLY = [
        'users.create',
        'users.edit',
        'users.delete',
        'settings.view',
        'settings.edit',
    ];

    /**
     * Rights over other people's access and the system itself, on top of
     * publishing and approving: whoever holds one signs in with a code.
     */
    private const TWO_FACTOR_ACCOUNT_RIGHTS = ['users.create', 'users.edit', 'users.delete', 'settings.edit'];

    /**
     * Whoever can put a material on the site — publish it or approve it (an
     * editor publishes official news: owner decision, 2026-09-23) — or
     * manage accounts or settings signs in with a code. Decided by rights,
     * so a role the administrator builds is covered the moment it gains one.
     *
     * @param  iterable<string>  $permissionNames
     */
    public static function needsTwoFactor(iterable $permissionNames): bool
    {
        foreach ($permissionNames as $name) {
            if (str_ends_with($name, '.publish')
                || str_ends_with($name, '.approve')
                || in_array($name, self::TWO_FACTOR_ACCOUNT_RIGHTS, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Every permission name in `module.action` form.
     *
     * @return list<string>
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
     * The actions the code checks for a module — the cells of the role
     * editor. Nothing ever asks, say, to publish a tag.
     *
     * @return list<PermissionAction>
     */
    public static function actionsOf(Module $module): array
    {
        return match ($module) {
            Module::Alerts, Module::News, Module::Instructions, Module::Documents,
            Module::Projects, Module::Announcements, Module::Pages => PermissionAction::cases(),
            Module::Media, Module::Taxonomy, Module::Regions, Module::Leadership,
            Module::Structure, Module::Users => [
                PermissionAction::View,
                PermissionAction::Create,
                PermissionAction::Edit,
                PermissionAction::Delete,
            ],
            Module::Submissions => [PermissionAction::View, PermissionAction::Edit, PermissionAction::Delete],
            Module::Home, Module::Settings => [PermissionAction::View, PermissionAction::Edit],
        };
    }

    /**
     * What the administrator may put into a role.
     *
     * @return list<string>
     */
    public static function grantable(): array
    {
        $permissions = [];

        foreach (Module::cases() as $module) {
            foreach (self::actionsOf($module) as $action) {
                $name = self::name($module, $action);

                if (! in_array($name, self::ADMINISTRATOR_ONLY, true)) {
                    $permissions[] = $name;
                }
            }
        }

        return $permissions;
    }

    /**
     * A set picked in the role editor, made consistent: only what may be
     * granted, and seeing a section comes with any other right in it.
     *
     * @param  array<int, string>  $permissions
     * @return list<string>
     */
    public static function normalize(array $permissions): array
    {
        $picked = array_values(array_intersect(self::grantable(), $permissions));

        foreach ($picked as $name) {
            $view = explode('.', $name)[0].'.'.PermissionAction::View->value;

            if (! in_array($view, $picked, true)) {
                $picked[] = $view;
            }
        }

        return array_values(array_intersect(self::grantable(), $picked));
    }

    /**
     * The rights a built-in role starts with. The administrator's role always
     * has every right; the other two are the administrator's to change.
     *
     * @return list<string>
     */
    public static function defaultsFor(RoleName $role): array
    {
        if ($role === RoleName::Admin) {
            return self::all();
        }

        $v = PermissionAction::View;
        $c = PermissionAction::Create;
        $e = PermissionAction::Edit;
        $d = PermissionAction::Delete;
        $p = PermissionAction::Publish;

        $grants = match ($role) {
            RoleName::ChiefEditor => [
                ...array_fill_keys(self::materials(), PermissionAction::cases()),
                Module::Media->value => [$v, $c, $e, $d],
                Module::Taxonomy->value => [$v, $c, $e, $d],
                Module::Home->value => [$v, $e],
                Module::Regions->value => [$v],
                Module::Leadership->value => [$v],
                Module::Structure->value => [$v],
                Module::Submissions->value => [$v, $e, $d],
                Module::Users->value => [$v],
            ],
            RoleName::Editor => [
                Module::News->value => [$v, $c, $e, $p],
                Module::Projects->value => [$v, $c, $e, $p],
                Module::Announcements->value => [$v, $c, $e, $p],
                Module::Pages->value => [$v, $c, $e, $p],
                Module::Alerts->value => [$v, $c, $e],
                Module::Instructions->value => [$v, $c, $e],
                Module::Documents->value => [$v, $c, $e],
                Module::Media->value => [$v, $c, $e],
                Module::Taxonomy->value => [$v, $c, $e],
                Module::Home->value => [$v],
            ],
        };

        $names = [];

        foreach ($grants as $module => $actions) {
            foreach ($actions as $action) {
                $names[] = $module.'.'.$action->value;
            }
        }

        return $names;
    }

    /**
     * [module => [action => bool]] over the meaningful cells, for a set of
     * permission names.
     *
     * @param  iterable<string>  $permissionNames
     * @return array<string, array<string, bool>>
     */
    public static function matrixOf(iterable $permissionNames): array
    {
        $granted = [];
        foreach ($permissionNames as $name) {
            $granted[$name] = true;
        }

        $matrix = [];

        foreach (Module::cases() as $module) {
            foreach (self::actionsOf($module) as $action) {
                $matrix[$module->value][$action->value] = isset($granted[self::name($module, $action)]);
            }
        }

        return $matrix;
    }

    /**
     * Modules whose materials go through drafts, approval and publication.
     *
     * @return list<string>
     */
    private static function materials(): array
    {
        return array_map(fn (Module $module): string => $module->value, [
            Module::Alerts,
            Module::News,
            Module::Instructions,
            Module::Documents,
            Module::Projects,
            Module::Announcements,
            Module::Pages,
        ]);
    }
}
