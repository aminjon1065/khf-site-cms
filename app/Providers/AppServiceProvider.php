<?php

namespace App\Providers;

use App\Enums\RoleName;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Login;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureAuthorization();
        $this->configureAuthEvents();
        $this->configureRateLimiting();
    }

    /**
     * Superadmins bypass every gate; everything else falls through to policies.
     */
    protected function configureAuthorization(): void
    {
        Gate::before(function (User $user, string $ability): ?bool {
            return $user->hasRole(RoleName::Superadmin->value) ? true : null;
        });
    }

    /**
     * Track the last successful login timestamp.
     */
    protected function configureAuthEvents(): void
    {
        Event::listen(Login::class, function (Login $event): void {
            if ($event->user instanceof User) {
                $event->user->forceFill(['last_login_at' => now()])->saveQuietly();
                session(['locale' => $event->user->interface_locale]);
            }
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);
        CarbonImmutable::setLocale('ru');
        Carbon::setLocale('ru');

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    /**
     * General ceiling for the public read-only API (routes/api.php). `search`
     * and `submissions` keep their own stricter per-route `throttle:N,1` on
     * top of this — whichever limit a request hits first wins, so this is a
     * baseline for everything else (home/settings/menu/news/pages/...), not
     * a replacement for those.
     *
     * 600, not the plan's example value of 120: a full `next build` (D-3
     * journal — discovered by actually running one, not assumed) issues a
     * burst of requests from the single build-host IP while statically
     * generating every content-detail page — with the current demo dataset
     * that's already enough to trip 120/min before the build finishes,
     * which would silently bake pages with the fallback/empty content from
     * B-6's graceful degradation instead of real data. 600 comfortably
     * covers that burst (and headroom for the dataset growing) while still
     * being a meaningful ceiling against real abuse.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('api-public', function (Request $request): Limit {
            return Limit::perMinute(600)->by($request->ip());
        });
    }
}
