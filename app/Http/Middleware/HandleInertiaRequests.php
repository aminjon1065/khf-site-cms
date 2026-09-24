<?php

namespace App\Http\Middleware;

use App\Enums\RoleName;
use App\Models\User;
use App\Support\NavBadges;
use App\Support\PublicSite;
use App\Support\UploadLimits;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Inertia;
use Inertia\Middleware;
use Symfony\Component\HttpFoundation\Response;

class HandleInertiaRequests extends Middleware
{
    /**
     * Session flag that makes the next page re-read the sidebar badges.
     */
    private const REFRESH_NAV_BADGES = 'refresh_nav_badges';

    /**
     * The root template that's loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Handle the incoming request.
     *
     * The client remembers `nav_badges` and reports it as already loaded, so
     * the redirect after approving, returning or publishing a material would
     * keep the old counts until the TTL runs out. Any change made by the user
     * flags the next page to re-read them; plain navigation still reuses the
     * remembered value.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethodSafe() && $request->user() !== null) {
            $request->session()->flash(self::REFRESH_NAV_BADGES, true);
        }

        return parent::handle($request, $next);
    }

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
            'auth' => $user
                ? Inertia::once(fn (): array => ['user' => $this->userPayload($user)])
                    ->until(now()->addMinutes(5))
                : ['user' => null],
            'locale' => 'ru',
            'public_site_url' => PublicSite::baseUrl(),
            // Formats and sizes every upload is checked against, so pickers
            // and hints say the same as the server (UploadLimits).
            'uploads' => Inertia::once(fn (): array => UploadLimits::forClient()),
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                'warning' => $request->session()->get('warning'),
                'info' => $request->session()->get('info'),
            ],
            'nav_badges' => $user
                ? Inertia::once(fn (): array => NavBadges::for($user))
                    ->until(now()->addSeconds(30))
                    ->fresh($request->session()->has(self::REFRESH_NAV_BADGES))
                : [],
            'notification_unread' => fn (): int => $this->unreadNotifications($user),
            'notifications' => Inertia::optional(fn (): array => $this->notifications($user)),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'initials' => $user->initials(),
            'position' => $user->position,
            'department' => $user->department,
            'region_id' => $user->region_id,
            'limited_to_region' => $user->isLimitedToRegion(),
            'role' => $user->getRoleNames()->first(),
            'role_label' => $user->primaryRoleLabel(),
            'permissions' => $user->getAllPermissions()->pluck('name')->all(),
            'is_admin' => $user->hasRole(RoleName::Admin->value),
            'two_factor_enabled' => $user->hasTwoFactorEnabled(),
        ];
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
            'unread' => $this->unreadNotifications($user),
            'items' => $items,
        ];
    }

    protected function unreadNotifications(?User $user): int
    {
        return $user?->unreadNotifications()->count() ?? 0;
    }
}
