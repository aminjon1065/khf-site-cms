<?php

use App\Jobs\QueueHeartbeat;
use Illuminate\Support\Facades\Cache;

use function Pest\Laravel\artisan;

it('reports a live database connection', function () {
    $this->getJson('/api/v1/health')
        ->assertOk()
        ->assertJsonPath('status', 'ok')
        ->assertJsonPath('checks.database', true);
});

it('requires a recent scheduler heartbeat for readiness', function () {
    $this->getJson('/api/v1/ready')
        ->assertServiceUnavailable()
        ->assertJsonPath('status', 'not_ready')
        ->assertJsonPath('checks.scheduler', false);

    artisan('content:process-scheduled')->assertSuccessful();

    $this->getJson('/api/v1/ready')
        ->assertOk()
        ->assertJsonPath('status', 'ready')
        ->assertJsonPath('checks.database', true)
        ->assertJsonPath('checks.storage', true)
        ->assertJsonPath('checks.scheduler', true)
        ->assertJsonPath('checks.queue_worker', true)
        ->assertJsonPath('checks.queue_failed_jobs', 0);
});

it('requires a recent worker heartbeat for an asynchronous queue', function () {
    config(['queue.default' => 'failover']);
    Cache::put('health.scheduler.last_run', now()->toIso8601String(), 60);

    $this->getJson('/api/v1/ready')
        ->assertServiceUnavailable()
        ->assertJsonPath('checks.queue_worker', false);

    (new QueueHeartbeat)->handle();

    $this->getJson('/api/v1/ready')
        ->assertOk()
        ->assertJsonPath('checks.queue_worker', true)
        ->assertJsonPath('checks.queue_connection', 'failover');
});
