<?php

use Illuminate\Support\Facades\Log;

it('does not log every successful public API request', function () {
    config()->set('observability.api.slow_request_ms', 60_000);
    config()->set('observability.api.sample_rate', 0);
    Log::spy();

    $this->getJson('/api/v1/health')->assertOk();

    Log::shouldNotHaveReceived('log');
});

it('logs slow requests with a low cardinality route and request id', function () {
    config()->set('observability.api.slow_request_ms', 0);
    config()->set('observability.api.sample_rate', 0);
    Log::spy();

    $this->withHeader('X-Request-ID', 'observability-test')
        ->getJson('/api/v1/health')
        ->assertOk()
        ->assertHeader('X-Request-ID', 'observability-test');

    Log::shouldHaveReceived('log')
        ->once()
        ->withArgs(fn (string $level, string $message, array $context): bool => $level === 'warning'
            && $message === 'public_api_response'
            && $context['event'] === 'public_api_response'
            && $context['request_id'] === 'observability-test'
            && $context['route'] === 'api.health'
            && $context['status'] === 200
            && $context['slow'] === true
            && ! array_key_exists('path', $context));
});

it('logs failed requests even when sampling is disabled', function () {
    config()->set('observability.api.slow_request_ms', 60_000);
    config()->set('observability.api.sample_rate', 0);
    Log::spy();

    $this->getJson('/api/v1/news/missing')->assertNotFound();

    Log::shouldHaveReceived('log')
        ->once()
        ->withArgs(fn (string $level, string $message, array $context): bool => $level === 'warning'
            && $message === 'public_api_response'
            && $context['status'] === 404
            && $context['route'] === 'api.news.show');
});
