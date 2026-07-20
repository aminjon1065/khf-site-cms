<?php

namespace App\Http\Controllers\Cms;

use App\Enums\Module;
use App\Enums\PermissionAction;
use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Support\PermissionMatrix;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

/**
 * Read-only viewer for the role × permission matrix. Roles and their grants are
 * defined in code ({@see PermissionMatrix}) and seeded, so this screen never
 * mutates them — it documents the RBAC so backend and UI cannot drift.
 */
class RoleController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless((bool) $request->user()?->can('users.view'), 403);

        /** @var array<string, int> $counts */
        $counts = Role::query()->withCount('users')->pluck('users_count', 'name')->all();

        $roles = array_map(fn (RoleName $role): array => [
            'value' => $role->value,
            'label' => $role->label(),
            'description' => $role->description(),
            'region_scoped' => $role->isRegionScoped(),
            'user_count' => (int) ($counts[$role->value] ?? 0),
            'matrix' => PermissionMatrix::matrixFor($role),
        ], RoleName::cases());

        return Inertia::render('roles/index', [
            'roles' => $roles,
            'modules' => array_map(
                fn (Module $m): array => ['value' => $m->value, 'label' => $m->label()],
                Module::cases(),
            ),
            'actions' => array_map(
                fn (PermissionAction $a): array => ['value' => $a->value, 'label' => $a->label()],
                PermissionAction::cases(),
            ),
        ]);
    }
}
