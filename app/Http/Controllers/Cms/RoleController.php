<?php

namespace App\Http\Controllers\Cms;

use App\Enums\Module;
use App\Enums\PermissionAction;
use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Role\RoleRequest;
use App\Models\Activity;
use App\Models\Role;
use App\Support\PermissionMatrix;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * «Роли и права». Whoever may see the staff list sees what each role can do;
 * the administrator also builds roles and changes their rights. The
 * administrator's own role is fixed: it always has every right. Every change
 * goes to the journal as a critical event — it changes what people can do.
 */
class RoleController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless((bool) $request->user()?->can('users.view'), 403);

        /** @var array<int, int> $counts */
        $counts = Role::query()->withCount('users')->pluck('users_count', 'id')->all();

        return Inertia::render('roles/index', [
            'roles' => Role::ordered()->map(fn (Role $role): array => [
                ...$this->present($role),
                'user_count' => (int) ($counts[$role->id] ?? 0),
            ])->all(),
            ...$this->grid(),
            'can_manage' => $this->isAdministrator($request),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorizeManaging($request);

        return Inertia::render('roles/form', [
            'role' => null,
            'templates' => $this->templates(),
            ...$this->grid(),
        ]);
    }

    public function store(RoleRequest $request): RedirectResponse
    {
        $role = Role::query()->create([
            'name' => $this->uniqueName($request->string('label')->toString()),
            'guard_name' => 'web',
            'label' => $request->string('label')->toString(),
            'description' => $request->input('description'),
        ]);
        $role->syncPermissions($request->permissions());

        $this->log($request, $role, 'created', "Создана роль «{$role->displayName()}»", [
            'права' => $this->describe($request->permissions()),
        ]);

        return redirect('/roles')->with('success', "Роль «{$role->displayName()}» создана.");
    }

    public function edit(Request $request, Role $role): Response|RedirectResponse
    {
        $this->authorizeManaging($request);

        if ($role->isAdministrator()) {
            return $this->administratorIsFixed();
        }

        return Inertia::render('roles/form', [
            'role' => [
                ...$this->present($role),
                'user_count' => $role->users()->count(),
            ],
            'templates' => [],
            ...$this->grid(),
        ]);
    }

    public function update(RoleRequest $request, Role $role): RedirectResponse
    {
        if ($role->isAdministrator()) {
            return $this->administratorIsFixed();
        }

        $before = [
            'label' => $role->displayName(),
            'description' => (string) $role->description,
            'permissions' => $role->permissions->pluck('name')->all(),
        ];

        $role->update([
            'label' => $request->string('label')->toString(),
            'description' => $request->input('description'),
        ]);
        $role->syncPermissions($request->permissions());

        $changes = [];
        $old = [];

        if ($before['label'] !== $role->displayName()) {
            $old['название'] = $before['label'];
            $changes['название'] = $role->displayName();
        }

        if ($before['description'] !== (string) $role->description) {
            $old['описание'] = $before['description'] ?: '—';
            $changes['описание'] = $role->description ?: '—';
        }

        $added = array_values(array_diff($request->permissions(), $before['permissions']));
        $removed = array_values(array_diff($before['permissions'], $request->permissions()));

        if ($added !== []) {
            $changes['добавлены права'] = $this->describe($added);
        }

        if ($removed !== []) {
            $changes['убраны права'] = $this->describe($removed);
        }

        if ($changes !== []) {
            $this->log($request, $role, 'updated', "Изменена роль «{$role->displayName()}»", $changes, $old);
        }

        return redirect('/roles')->with('success', "Роль «{$role->displayName()}» сохранена.");
    }

    public function destroy(Request $request, Role $role): RedirectResponse
    {
        $this->authorizeManaging($request);

        if ($role->isAdministrator()) {
            return $this->administratorIsFixed();
        }

        $people = $role->users()->count();

        if ($people > 0) {
            return back()->with('error', "У роли «{$role->displayName()}» есть сотрудники ({$people}). Сначала назначьте им другую роль.");
        }

        $label = $role->displayName();
        $this->log($request, $role, 'deleted', "Удалена роль «{$label}»", [
            'права' => $this->describe($role->permissions->pluck('name')->all()),
        ]);
        $role->delete();

        return redirect('/roles')->with('success', "Роль «{$label}» удалена.");
    }

    // ---------------------------------------------------------------- helpers

    private function isAdministrator(Request $request): bool
    {
        return (bool) $request->user()?->hasRole(RoleName::Admin->value);
    }

    private function authorizeManaging(Request $request): void
    {
        abort_unless($this->isAdministrator($request), 403);
    }

    private function administratorIsFixed(): RedirectResponse
    {
        return redirect('/roles')->with('error', 'Роль администратора не меняется: у неё всегда все права.');
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Role $role): array
    {
        $permissions = $role->isAdministrator()
            ? PermissionMatrix::all()
            : $role->permissions->pluck('name')->all();

        return [
            'id' => $role->id,
            'value' => $role->name,
            'label' => $role->displayName(),
            'description' => $role->description,
            'is_administrator' => $role->isAdministrator(),
            'is_built_in' => RoleName::tryFrom($role->name) !== null,
            'matrix' => PermissionMatrix::matrixOf($permissions),
            'needs_two_factor' => $role->isAdministrator() || PermissionMatrix::needsTwoFactor($permissions),
        ];
    }

    /**
     * Sections and actions of the rights table, the cells only the
     * administrator may hold, and the rights that bring a sign-in code.
     *
     * @return array{modules: list<array{value: string, label: string, actions: list<string>}>, actions: list<array{value: string, label: string}>, administrator_only: list<string>, two_factor_rights: list<string>}
     */
    private function grid(): array
    {
        return [
            'modules' => array_map(fn (Module $module): array => [
                'value' => $module->value,
                'label' => $module->label(),
                'actions' => array_map(
                    fn (PermissionAction $action): string => $action->value,
                    PermissionMatrix::actionsOf($module),
                ),
            ], Module::cases()),
            'actions' => array_map(
                fn (PermissionAction $action): array => ['value' => $action->value, 'label' => $action->label()],
                PermissionAction::cases(),
            ),
            'administrator_only' => PermissionMatrix::ADMINISTRATOR_ONLY,
            'two_factor_rights' => array_values(array_filter(
                PermissionMatrix::grantable(),
                fn (string $permission): bool => PermissionMatrix::needsTwoFactor([$permission]),
            )),
        ];
    }

    /**
     * Rights of the existing roles, to start a new one from.
     *
     * @return list<array{value: string, label: string, permissions: list<string>}>
     */
    private function templates(): array
    {
        return array_values(Role::ordered()
            ->reject(fn (Role $role): bool => $role->isAdministrator())
            ->map(fn (Role $role): array => [
                'value' => $role->name,
                'label' => $role->displayName(),
                'permissions' => PermissionMatrix::normalize($role->permissions->pluck('name')->all()),
            ])
            ->all());
    }

    /**
     * The key the code knows the role by, made from its name once: renaming
     * the role later doesn't change it.
     */
    private function uniqueName(string $label): string
    {
        $base = Str::slug($label, '_', 'ru') ?: 'role';
        $name = $base;

        for ($suffix = 2; RoleName::tryFrom($name) !== null || Role::query()->where('name', $name)->exists(); $suffix++) {
            $name = "{$base}_{$suffix}";
        }

        return $name;
    }

    /**
     * «Новости и заявления: создание, правка; Документы: просмотр» — rights
     * as the journal shows them.
     *
     * @param  array<int, string>  $permissions
     */
    private function describe(array $permissions): string
    {
        if ($permissions === []) {
            return '—';
        }

        $byModule = [];

        foreach ($permissions as $name) {
            [$module, $action] = array_pad(explode('.', $name, 2), 2, '');
            $moduleLabel = Module::tryFrom($module)?->label() ?? $module;
            $byModule[$moduleLabel][] = mb_strtolower(PermissionAction::tryFrom($action)?->label() ?? $action);
        }

        return implode('; ', array_map(
            fn (string $module, array $actions): string => $module.': '.implode(', ', $actions),
            array_keys($byModule),
            $byModule,
        ));
    }

    /**
     * @param  array<string, string>  $attributes
     * @param  array<string, string>  $old
     */
    private function log(Request $request, Role $role, string $event, string $description, array $attributes, array $old = []): void
    {
        activity('roles')
            ->performedOn($role)
            ->causedBy($request->user())
            ->event($event)
            ->withProperties(['attributes' => $attributes, 'old' => $old])
            ->tap(function (Activity $activity): void {
                $activity->is_critical = true;
            })
            ->log($description);
    }
}
