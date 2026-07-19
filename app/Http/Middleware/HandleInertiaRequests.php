<?php

namespace App\Http\Middleware;

use App\Enums\RoleName;
use App\Models\User;
use App\Support\NavBadges;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        /** @var User|null $user */
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user ? $this->userPayload($user) : null,
            ],
            'locale' => $this->resolveLocale($request),
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                'warning' => $request->session()->get('warning'),
                'info' => $request->session()->get('info'),
            ],
            'nav_badges' => fn (): array => NavBadges::for($user),
            'notifications' => fn (): array => $this->notifications($user),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function userPayload(User $user): array
    {
        $roleName = $user->getRoleNames()->first();

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'initials' => $user->initials(),
            'position' => $user->position,
            'department' => $user->department,
            'region_id' => $user->region_id,
            'role' => $roleName,
            'role_label' => $roleName ? RoleName::tryFrom($roleName)?->label() : null,
            'permissions' => $user->getAllPermissions()->pluck('name')->all(),
            'is_super' => $user->hasRole(RoleName::Superadmin->value),
            'two_factor_enabled' => $user->hasTwoFactorEnabled(),
            'interface_locale' => $user->interface_locale,
        ];
    }

    protected function resolveLocale(Request $request): string
    {
        /** @var string $locale */
        $locale = $request->session()->get('locale', config('app.locale', 'ru'));

        return in_array($locale, ['ru', 'tg'], true) ? $locale : 'ru';
    }

    /**
     * @return array{unread: int, items: array<int, array<string, mixed>>}
     */
    protected function notifications(?User $user): array
    {
        if (! $user) {
            return ['unread' => 0, 'items' => []];
        }

        $items = $user->notifications()->latest()->limit(8)->get()->map(function (DatabaseNotification $n): array {
            /** @var array<string, mixed> $data */
            $data = $n->data;

            return [
                'id' => $n->id,
                'title' => $data['title'] ?? '',
                'message' => $data['message'] ?? '',
                'tone' => $data['tone'] ?? 'info',
                'read_at' => $n->read_at?->toIso8601String(),
                'created_at' => $n->created_at?->toIso8601String(),
                'created_diff' => $n->created_at?->diffForHumans() ?? '',
                'subject_type' => $data['subject_type'] ?? null,
                'subject_id' => $data['subject_id'] ?? null,
                'url' => $data['url'] ?? null,
            ];
        })->all();

        return [
            'unread' => $user->unreadNotifications()->count(),
            'items' => $items,
        ];
    }
}
