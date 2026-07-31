<?php

use App\Jobs\QueueHeartbeat;
use App\Jobs\RevalidateFrontend;
use App\Models\News;
use App\Notifications\WorkflowNotification;
use Illuminate\Cache\Events\CacheFailedOver;
use Illuminate\Foundation\DevCommands;
use Illuminate\Queue\Events\QueueBusy;
use Illuminate\Queue\Events\QueueFailedOver;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\artisan;

it('separates latency-sensitive work from media conversions', function () {
    $revalidation = new RevalidateFrontend(
        type: 'news',
        id: 1,
        slug: 'queue-topology',
        locales: ['ru'],
        event: 'published',
    );
    $notification = new WorkflowNotification(
        News::factory()->make(),
        'Published',
        'The material was published.',
    );

    expect(config('cache.stores.failover.stores'))
        ->toBe(['redis', 'database'])
        ->and(config('queue.connections.failover.connections'))
        ->toBe(['redis', 'database'])
        ->and(config('queue.connections.redis.after_commit'))->toBeTrue()
        ->and(config('queue.connections.database.after_commit'))->toBeTrue()
        ->and(config('queue.connections.redis.retry_after'))->toBeGreaterThan(150)
        ->and(config('queue.connections.database.retry_after'))->toBeGreaterThan(150)
        ->and((new QueueHeartbeat)->queue)->toBe('critical')
        ->and($revalidation->queue)->toBe('revalidation')
        ->and($notification->queue)->toBe('notifications')
        ->and(config('media-library.queue_name'))->toBe('media');
});

it('falls back to durable stores when redis is unavailable', function () {
    config([
        'database.redis.outage' => [
            'host' => '127.0.0.1',
            'port' => 1,
            'database' => 15,
            'timeout' => 0.1,
            'read_timeout' => 0.1,
            'max_retries' => 0,
        ],
        'cache.stores.redis_outage' => [
            'driver' => 'redis',
            'connection' => 'outage',
        ],
        'cache.stores.failover_test' => [
            'driver' => 'failover',
            'stores' => ['redis_outage', 'database'],
        ],
        'queue.connections.redis_outage' => [
            'driver' => 'redis',
            'connection' => 'outage',
            'queue' => 'default',
            'retry_after' => 180,
            'block_for' => 0,
            'after_commit' => false,
        ],
        'queue.connections.database_test' => [
            ...config('queue.connections.database'),
            'after_commit' => false,
        ],
        'queue.connections.failover_test' => [
            'driver' => 'failover',
            'connections' => ['redis_outage', 'database_test'],
        ],
    ]);
    Event::fake([CacheFailedOver::class, QueueFailedOver::class]);

    Cache::store('failover_test')->put('redis-outage-proof', 'durable', 60);
    Queue::connection('failover_test')->push(new QueueHeartbeat, '', 'critical');

    expect(Cache::store('database')->get('redis-outage-proof'))->toBe('durable');
    $this->assertDatabaseHas('jobs', ['queue' => 'critical']);
    Event::assertDispatched(CacheFailedOver::class);
    Event::assertDispatched(QueueFailedOver::class);
});

it('registers worker heartbeat and backlog monitoring schedules', function () {
    artisan('schedule:list')
        ->expectsOutputToContain('QueueHeartbeat')
        ->expectsOutputToContain('queue:monitor redis:critical')
        ->expectsOutputToContain('queue:monitor database:critical')
        ->assertSuccessful();
});

it('emits an operational alert when a queue exceeds its backlog budget', function () {
    Log::spy();

    event(new QueueBusy('redis', 'media', 101));

    Log::shouldHaveReceived('critical')
        ->once()
        ->with(
            'queue_backlog_exceeded',
            [
                'event' => 'queue_backlog_exceeded',
                'connection' => 'redis',
                'queue' => 'media',
                'size' => 101,
            ],
        );
});

it('makes the local dev worker listen to every configured queue', function () {
    // Дефолтный процесс `php artisan dev` поднимает `queue:listen` без
    // `--queue` и слушает только `default`. Тогда heartbeat (очередь
    // `critical`) не обрабатывается, `/api/v1/ready` навсегда остаётся
    // `not_ready`, а джобы ревалидации копятся в `revalidation` — сайт молча
    // перестаёт обновляться. AppServiceProvider переопределяет процесс;
    // этот тест ловит момент, когда в config('queue.names') добавят очередь,
    // а переопределение забудут обновить.
    $queueProcess = collect(DevCommands::commands())
        ->firstWhere('name', 'queue');

    expect($queueProcess)->not->toBeNull();

    $command = (string) $queueProcess['command'];

    expect($command)->toContain('--queue=');

    foreach (config('queue.names') as $queue) {
        expect($command)->toContain($queue);
    }
});

it('runs the scheduler among the local dev processes', function () {
    // Без планировщика heartbeat расписания устаревает и `/api/v1/ready`
    // возвращает `not_ready`, даже когда всё остальное работает.
    $names = collect(DevCommands::commands())->pluck('name');

    expect($names)->toContain('schedule');

    $schedule = collect(DevCommands::commands())->firstWhere('name', 'schedule');

    expect((string) $schedule['command'])->toContain('schedule:work');
});
