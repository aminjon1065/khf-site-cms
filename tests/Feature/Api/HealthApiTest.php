<?php

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
        ->assertJsonPath('checks.scheduler', true);
});
