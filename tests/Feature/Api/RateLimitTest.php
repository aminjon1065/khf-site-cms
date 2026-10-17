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

it('does not count the site server against the per-IP limit', function () {
    // Рендер сайта и `next build` идут с одного адреса: под общим лимитом
    // обход архива роботом приводил к 429 и заглушкам в кэше сайта.
    config()->set('services.frontend.site_api_key', 'site-server-key');

    for ($i = 0; $i < 600; $i++) {
        $this->getJson('/api/v1/settings', ['X-Site-Key' => 'site-server-key'])->assertOk();
    }

    $this->getJson('/api/v1/settings', ['X-Site-Key' => 'site-server-key'])->assertOk();
});

it('keeps the per-IP limit for a wrong or missing site key', function () {
    config()->set('services.frontend.site_api_key', 'site-server-key');

    for ($i = 0; $i < 600; $i++) {
        $this->getJson('/api/v1/settings', ['X-Site-Key' => 'guess'])->assertOk();
    }

    $this->getJson('/api/v1/settings', ['X-Site-Key' => 'guess'])->assertStatus(429);
});

it('ignores the site key header while no key is configured', function () {
    config()->set('services.frontend.site_api_key', null);

    for ($i = 0; $i < 600; $i++) {
        $this->getJson('/api/v1/settings', ['X-Site-Key' => ''])->assertOk();
    }

    $this->getJson('/api/v1/settings', ['X-Site-Key' => ''])->assertStatus(429);
});
