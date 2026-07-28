<?php

namespace App\Providers;

use App\Enums\RoleName;
use App\Listeners\CaptureImageDerivativeMetadata;
use App\Listeners\CaptureOriginalImageMetadata;
use App\Models\Alert;
use App\Models\Announcement;
use App\Models\Category;
use App\Models\District;
use App\Models\Document;
use App\Models\HomeBlock;
use App\Models\Instruction;
use App\Models\MenuItem;
use App\Models\News;
use App\Models\Page;
use App\Models\Project;
use App\Models\Region;
use App\Models\Setting;
use App\Models\User;
use App\Observers\CaptureEditorialRevision;
use App\Observers\InvalidatePublicReadModels;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Login;
use Illuminate\Cache\Events\CacheFailedOver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\QueueBusy;
use Illuminate\Queue\Events\QueueFailedOver;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Spatie\MediaLibrary\Conversions\Events\ConversionHasBeenCompletedEvent;
use Spatie\MediaLibrary\MediaCollections\Events\MediaHasBeenAddedEvent;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

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
        $this->configureMediaEvents();
        $this->configureInfrastructureEvents();
        $this->configureRateLimiting();
        $this->configurePublicReadModelCache();
        $this->configureEditorialRevisions();
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

    protected function configureMediaEvents(): void
    {
        Event::listen(MediaHasBeenAddedEvent::class, CaptureOriginalImageMetadata::class);
        Event::listen(ConversionHasBeenCompletedEvent::class, CaptureImageDerivativeMetadata::class);
    }

    protected function configureInfrastructureEvents(): void
    {
        Event::listen(CacheFailedOver::class, fn (CacheFailedOver $event) => Log::warning(
            'Cache store failed over.',
            [
                'store' => $event->storeName,
                'error' => $event->exception->getMessage(),
            ],
        ));
        Event::listen(QueueFailedOver::class, fn (QueueFailedOver $event) => Log::critical(
            'Queue connection failed over.',
            [
                'connection' => $event->connectionName,
                'job' => is_object($event->command) ? $event->command::class : (string) $event->command,
                'error' => $event->exception->getMessage(),
            ],
        ));
        Event::listen(QueueBusy::class, fn (QueueBusy $event) => Log::critical(
            'Queue backlog threshold exceeded.',
            [
                'connection' => $event->connectionName,
                'queue' => $event->queue,
                'size' => $event->size,
            ],
        ));
        Event::listen(JobFailed::class, fn (JobFailed $event) => Log::critical(
            'Queued job failed permanently.',
            [
                'connection' => $event->connectionName,
                'queue' => $event->job->getQueue(),
                'job' => $event->job->resolveName(),
                'error' => $event->exception->getMessage(),
            ],
        ));
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
     *
     * `rum` ограничивает анонимную отправку Core Web Vitals с фронта.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for(
            'api-public',
            fn (Request $request): Limit => Limit::perMinute(600)->by($request->ip()),
        );

        RateLimiter::for(
            'rum',
            fn (Request $request): Limit => Limit::perMinute(600)->by($request->ip()),
        );
    }

    protected function configurePublicReadModelCache(): void
    {
        foreach ([
            Alert::class,
            Announcement::class,
            Category::class,
            District::class,
            Document::class,
            HomeBlock::class,
            Instruction::class,
            Media::class,
            MenuItem::class,
            News::class,
            Project::class,
            Region::class,
            Setting::class,
        ] as $model) {
            $model::observe(InvalidatePublicReadModels::class);
        }
    }

    protected function configureEditorialRevisions(): void
    {
        foreach ([
            Announcement::class,
            Document::class,
            Instruction::class,
            News::class,
            Page::class,
            Project::class,
        ] as $model) {
            $model::observe(CaptureEditorialRevision::class);
        }
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
}
