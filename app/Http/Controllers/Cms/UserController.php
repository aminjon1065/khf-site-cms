<?php

namespace App\Http\Controllers\Cms;

use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\UserRequest;
use App\Models\Activity;
use App\Models\Region;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', User::class);

        $search = $request->string('search')->toString();
        $role = $request->string('role')->toString();
        $status = $request->string('status')->toString();
        $perPage = max(1, min((int) $request->integer('per_page', 25), 100));

        $query = User::query()->with(['region', 'roles']);

        if ($search !== '') {
            $query->where(function (Builder $q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('department', 'like', "%{$search}%");
            });
        }
        if ($role !== '') {
            $query->whereHas('roles', fn (Builder $q) => $q->where('name', $role));
        }
        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        $users = $query->orderBy('name')->paginate($perPage)->withQueryString();

        return Inertia::render('users/index', [
            'users' => array_map(fn (User $u): array => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'initials' => $u->initials(),
                'position' => $u->position,
                'department' => $u->department,
                'role' => $u->primaryRoleLabel(),
                'role_value' => $u->getRoleNames()->first(),
                'region' => $u->region?->getTranslation('name', 'ru'),
                'limited_to_region' => $u->isLimitedToRegion(),
                'is_active' => $u->is_active,
                'two_factor' => $u->hasTwoFactorEnabled(),
                'last_login_at' => $u->last_login_at?->toIso8601String(),
                'is_self' => $request->user()?->id === $u->id,
            ], $users->items()),
            'meta' => [
                'from' => $users->firstItem(),
                'to' => $users->lastItem(),
                'total' => $users->total(),
                'per_page' => $users->perPage(),
                'prev' => $users->previousPageUrl(),
                'next' => $users->nextPageUrl(),
            ],
            'filters' => ['search' => $search, 'role' => $role, 'status' => $status],
            'options' => ['roles' => $this->roleOptions()],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', User::class);

        return Inertia::render('users/form', [
            'user' => null,
            'reference' => $this->reference($request),
        ]);
    }

    public function edit(Request $request, User $user): Response
    {
        $this->authorize('update', $user);
        $this->guardTarget($request, $user);

        return Inertia::render('users/form', [
            'user' => $this->payload($request, $user),
            'reference' => $this->reference($request),
        ]);
    }

    public function store(UserRequest $request): RedirectResponse
    {
        $this->authorize('create', User::class);

        $role = $this->requestedRole($request);
        $this->guardRole($request, $role);

        $user = new User;
        $this->fill($user, $request, $role);
        $user->password = Hash::make((string) $request->input('password'));
        $user->save();
        $user->syncRoles([$role->name]);
        $this->logRoleChange($request, $user, null, $role);

        return redirect('/users')->with('success', 'Пользователь создан.');
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);
        $this->guardTarget($request, $user);

        $isSelf = $request->user()?->id === $user->id;
        $previousRole = $user->roles->first();

        // You cannot change your own role (prevents self-escalation / lockout).
        $role = $isSelf && $previousRole instanceof Role ? $previousRole : $this->requestedRole($request);
        $this->guardRole($request, $role);

        $this->fill($user, $request, $role);

        // Prevent self-lockout: you cannot deactivate your own account.
        if ($isSelf) {
            $user->is_active = true;
        }

        if (filled($request->input('password'))) {
            $user->password = Hash::make((string) $request->input('password'));
        }

        $user->save();

        if (! $isSelf && ! $role->is($previousRole)) {
            $user->syncRoles([$role->name]);
            $this->logRoleChange($request, $user, $previousRole instanceof Role ? $previousRole : null, $role);
        }

        return redirect('/users')->with('success', 'Пользователь обновлён.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $this->authorize('delete', $user);

        if ($request->user()?->id === $user->id) {
            return back()->with('error', 'Нельзя удалить собственную учётную запись.');
        }

        $this->guardTarget($request, $user);

        if ($user->hasRole(RoleName::Admin->value)
            && User::role(RoleName::Admin->value)->count() <= 1) {
            return back()->with('error', 'Нельзя удалить последнего администратора.');
        }

        $user->delete();

        return redirect('/users')->with('success', 'Пользователь удалён.');
    }

    // ---------------------------------------------------------------- helpers

    private function actorIsAdministrator(Request $request): bool
    {
        return (bool) $request->user()?->hasRole(RoleName::Admin->value);
    }

    private function requestedRole(UserRequest $request): Role
    {
        return Role::query()
            ->where('guard_name', 'web')
            ->where('name', $request->string('role')->toString())
            ->firstOrFail();
    }

    /**
     * Only an administrator makes administrators. Managing accounts is the
     * administrator's right alone (PermissionMatrix::ADMINISTRATOR_ONLY), so
     * this holds anyway — checked here too, in case that ever changes.
     */
    private function guardRole(Request $request, Role $role): void
    {
        if ($role->isAdministrator() && ! $this->actorIsAdministrator($request)) {
            abort(403, 'Назначить администратора может только администратор.');
        }
    }

    /**
     * Who holds which role decides what they can do on the site: every change
     * goes to the journal as a critical event.
     */
    private function logRoleChange(Request $request, User $user, ?Role $from, Role $to): void
    {
        activity('users')
            ->performedOn($user)
            ->causedBy($request->user())
            ->event('role_changed')
            ->withProperties([
                'old' => ['роль' => $from?->displayName() ?? '—'],
                'attributes' => ['роль' => $to->displayName()],
            ])
            ->tap(function (Activity $activity): void {
                $activity->is_critical = true;
            })
            ->log($from === null
                ? "Назначена роль «{$to->displayName()}»"
                : "Роль изменена: «{$from->displayName()}» → «{$to->displayName()}»");
    }

    /**
     * A lost or replaced phone must not require a developer editing the
     * database: an administrator switches the employee's two-factor
     * authentication off, and — where it's mandatory for the role — the
     * employee sets it up again at the next sign-in. Their open sessions end,
     * so a stolen session can't outlive the reset.
     */
    public function resetTwoFactor(Request $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);
        $this->guardTarget($request, $user);

        if ($request->user()?->is($user)) {
            return back()->with('error', 'Свою двухфакторную аутентификацию меняйте в профиле: «Безопасность».');
        }

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        DB::table('sessions')->where('user_id', $user->id)->delete();

        activity('users')
            ->performedOn($user)
            ->causedBy($request->user())
            ->event('two_factor_reset')
            ->tap(function (Activity $activity): void {
                $activity->is_critical = true;
            })
            ->log('Двухфакторная аутентификация сброшена администратором');

        return back()->with('success', "Двухфакторная аутентификация для {$user->name} сброшена. При следующем входе сотрудник настроит её заново.");
    }

    /**
     * Only an administrator manages an administrator's account.
     */
    private function guardTarget(Request $request, User $target): void
    {
        if ($target->hasRole(RoleName::Admin->value) && ! $this->actorIsAdministrator($request)) {
            abort(403, 'Учётной записью администратора управляет только администратор.');
        }
    }

    private function fill(User $user, UserRequest $request, Role $role): void
    {
        $regionId = $request->input('region_id');

        $user->fill([
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'position' => $request->input('position'),
            'department' => $request->input('department'),
            'region_id' => $regionId,
            // The administrator works with everything.
            'limited_to_region' => ! $role->isAdministrator() && $regionId !== null && $request->boolean('limited_to_region'),
            'interface_locale' => $request->input('interface_locale') ?: 'ru',
            'is_active' => $request->boolean('is_active'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Request $request, User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->getRoleNames()->first(),
            'region_id' => $user->region_id,
            'limited_to_region' => $user->isLimitedToRegion(),
            'position' => $user->position,
            'department' => $user->department,
            'is_active' => $user->is_active,
            'is_self' => $request->user()?->id === $user->id,
            'two_factor_enabled' => $user->hasTwoFactorEnabled(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function reference(Request $request): array
    {
        return [
            'roles' => array_values(array_filter(
                $this->roleOptions(),
                fn (array $option): bool => $option['value'] !== RoleName::Admin->value || $this->actorIsAdministrator($request),
            )),
            'regions' => Region::query()->orderBy('sort')->get()
                ->map(fn (Region $r): array => ['value' => $r->id, 'label' => $r->getTranslation('name', 'ru')])->all(),
        ];
    }

    /**
     * @return list<array{value: string, label: string, description: string|null}>
     */
    private function roleOptions(): array
    {
        return array_values(Role::ordered()->map(fn (Role $role): array => [
            'value' => $role->name,
            'label' => $role->displayName(),
            'description' => $role->description,
        ])->all());
    }
}
