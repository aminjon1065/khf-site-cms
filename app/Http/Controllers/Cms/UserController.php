<?php

namespace App\Http\Controllers\Cms;

use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\UserRequest;
use App\Models\Region;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        $query = User::query()->with('region');

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
            'options' => ['roles' => RoleName::options()],
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

        $role = (string) $request->input('role');
        $this->guardRole($request, $role);

        $user = new User;
        $this->fill($user, $request);
        $user->password = Hash::make((string) $request->input('password'));
        $user->save();
        $user->syncRoles([$role]);

        return redirect('/users')->with('success', 'Пользователь создан.');
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);
        $this->guardTarget($request, $user);

        $role = (string) $request->input('role');
        $this->guardRole($request, $role);

        $isSelf = $request->user()?->id === $user->id;

        $this->fill($user, $request);

        // Prevent self-lockout: you cannot deactivate your own account.
        if ($isSelf) {
            $user->is_active = true;
        }

        if (filled($request->input('password'))) {
            $user->password = Hash::make((string) $request->input('password'));
        }

        $user->save();

        // You cannot change your own role (prevents self-escalation / lockout).
        if (! $isSelf) {
            $user->syncRoles([$role]);
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

        if ($user->hasRole(RoleName::Superadmin->value)
            && User::role(RoleName::Superadmin->value)->count() <= 1) {
            return back()->with('error', 'Нельзя удалить последнего суперадминистратора.');
        }

        $user->delete();

        return redirect('/users')->with('success', 'Пользователь удалён.');
    }

    // ---------------------------------------------------------------- helpers

    private function actorIsSuperadmin(Request $request): bool
    {
        return (bool) $request->user()?->hasRole(RoleName::Superadmin->value);
    }

    /**
     * Only a superadmin may grant the superadmin role.
     */
    private function guardRole(Request $request, string $role): void
    {
        if ($role === RoleName::Superadmin->value && ! $this->actorIsSuperadmin($request)) {
            abort(403, 'Только суперадминистратор может назначать роль суперадминистратора.');
        }
    }

    /**
     * Only a superadmin may modify or remove another superadmin.
     */
    private function guardTarget(Request $request, User $target): void
    {
        if ($target->hasRole(RoleName::Superadmin->value) && ! $this->actorIsSuperadmin($request)) {
            abort(403, 'Только суперадминистратор может управлять суперадминистратором.');
        }
    }

    private function fill(User $user, UserRequest $request): void
    {
        $user->fill([
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'position' => $request->input('position'),
            'department' => $request->input('department'),
            'region_id' => $request->input('region_id'),
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
            'position' => $user->position,
            'department' => $user->department,
            'interface_locale' => $user->interface_locale,
            'is_active' => $user->is_active,
            'is_self' => $request->user()?->id === $user->id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function reference(Request $request): array
    {
        // Non-superadmins cannot assign the superadmin role.
        $roles = array_values(array_filter(
            RoleName::options(),
            fn (array $r): bool => $r['value'] !== RoleName::Superadmin->value || $this->actorIsSuperadmin($request),
        ));

        return [
            'roles' => $roles,
            'regions' => Region::query()->orderBy('sort')->get()
                ->map(fn (Region $r): array => ['value' => $r->id, 'label' => $r->getTranslation('name', 'ru')])->all(),
            'locales' => [
                ['value' => 'ru', 'label' => 'Русский'],
                ['value' => 'tg', 'label' => 'Тоҷикӣ'],
                ['value' => 'en', 'label' => 'English'],
            ],
        ];
    }
}
