<?php

use Illuminate\Support\Facades\Cache;

// D-1: throttle:api-public (AppServiceProvider + routes/api.php). The
// `array` cache driver (phpunit.xml) is process-lifetime, not per-test, so
// each test clears it first to avoid budget bleeding between tests that
// share the same client IP.
beforeEach(function (): void {
    Cache::flush();
});

it('returns X-RateLimit headers on a normal request', function () {
    $this->getJson('/api/v1/settings')
        ->assertOk()
        ->assertHeader('X-RateLimit-Limit', '600');
});

it('returns 429 with Retry-After once the general public API limit is exceeded', function () {
    for ($i = 0; $i < 600; $i++) {
        $this->getJson('/api/v1/settings')->assertOk();
    }

    $this->getJson('/api/v1/settings')
        ->assertStatus(429)
        ->assertHeader('Retry-After');
});

it('does not throttle health checks so infra probes are never rejected', function () {
    for ($i = 0; $i < 605; $i++) {
        $this->getJson('/api/v1/health')->assertOk();
    }
});
