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
use App\Services\OperationalTelemetry;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Login;
use Illuminate\Cache\Events\CacheFailedOver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\DevCommands;
use Illuminate\Http\Request;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
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
        $this->app->singleton(OperationalTelemetry::class);
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
        $this->configureSlowQueryLogging();
        $this->configureRateLimiting();
        $this->configurePublicReadModelCache();
        $this->configureEditorialRevisions();
        $this->configureDevCommands();
    }

    /**
     * Локальный `php artisan dev` по умолчанию поднимает `queue:listen` БЕЗ
     * `--queue`, то есть слушает только очередь `default`. С введением
     * очередей (`critical`, `notifications`, `revalidation`, `media`) это
     * приводило к тихим отказам: heartbeat очереди уходит в `critical`, и
     * `/api/v1/ready` навсегда оставался `not_ready`, а джобы ревалидации
     * копились в `revalidation` — контент публиковался, но публичный сайт
     * не обновлялся. Переопределяем процесс так, чтобы он слушал ВСЕ
     * настроенные очереди в порядке приоритета.
     */
    protected function configureDevCommands(): void
    {
        /** @var array<string, string> $names */
        $names = config('queue.names');
        $queues = implode(',', array_values($names));

        DevCommands::artisan(
            "queue:listen --queue={$queues} --tries=1 --timeout=0",
            'queue',
        );

        // Локально планировщик не запускается ничем: cron есть только на
        // сервере. Без него heartbeat расписания устаревает через 15 минут и
        // `/api/v1/ready` снова уходит в `not_ready`, а отложенные публикации
        // и автозавершение предупреждений просто не срабатывают.
        DevCommands::artisan('schedule:work', 'schedule');
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
        Event::listen(JobProcessing::class, function (JobProcessing $event): void {
            app(OperationalTelemetry::class)->startQueueJob($this->queueJobId($event->job));
        });
        Event::listen(JobProcessed::class, function (JobProcessed $event): void {
            app(OperationalTelemetry::class)->finishQueueJob(
                $this->queueJobId($event->job),
                $event->job->getQueue(),
            );
        });
        Event::listen(JobExceptionOccurred::class, function (JobExceptionOccurred $event): void {
            app(OperationalTelemetry::class)->finishQueueJob(
                $this->queueJobId($event->job),
                $event->job->getQueue(),
                true,
            );
        });
        Event::listen(CacheFailedOver::class, fn (CacheFailedOver $event) => Log::warning(
            'cache_failed_over',
            [
                'event' => 'cache_failed_over',
                'store' => $event->storeName,
                'error' => $event->exception->getMessage(),
            ],
        ));
        Event::listen(QueueFailedOver::class, fn (QueueFailedOver $event) => Log::critical(
            'queue_failed_over',
            [
                'event' => 'queue_failed_over',
                'connection' => $event->connectionName,
                'job' => is_object($event->command) ? $event->command::class : (string) $event->command,
                'error' => $event->exception->getMessage(),
            ],
        ));
        Event::listen(QueueBusy::class, fn (QueueBusy $event) => Log::critical(
            'queue_backlog_exceeded',
            [
                'event' => 'queue_backlog_exceeded',
                'connection' => $event->connectionName,
                'queue' => $event->queue,
                'size' => $event->size,
            ],
        ));
        Event::listen(JobFailed::class, fn (JobFailed $event) => Log::critical(
            'queue_job_failed',
            [
                'event' => 'queue_job_failed',
                'connection' => $event->connectionName,
                'queue' => $event->job->getQueue(),
                'job' => $event->job->resolveName(),
                'error' => $event->exception->getMessage(),
            ],
        ));
    }

    private function queueJobId(object $job): string
    {
        if (method_exists($job, 'uuid') && is_string($job->uuid())) {
            return $job->uuid();
        }

        return (string) spl_object_id($job);
    }

    protected function configureSlowQueryLogging(): void
    {
        DB::listen(function (QueryExecuted $query): void {
            $threshold = (float) config('observability.database.slow_query_ms', 250);

            if ($query->time < $threshold) {
                return;
            }

            Log::warning('slow_database_query', [
                'event' => 'slow_database_query',
                'connection' => $query->connectionName,
                'duration_ms' => round($query->time, 1),
                'sql' => $query->sql,
            ]);
        });
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
            // Pages contribute no denormalised home/menu data, but their slugs
            // are served from cache (SlugController), so edits must invalidate.
            Page::class,
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
