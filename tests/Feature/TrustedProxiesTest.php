<?php

use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;

/**
 * @param  array<string, string>  $headers
 */
function proxyRequest(string $remoteAddr, array $headers = []): Request
{
    $server = ['REMOTE_ADDR' => $remoteAddr];

    foreach ($headers as $name => $value) {
        $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
    }

    return Request::create('/api/v1/alerts', 'GET', server: $server);
}

it('resolves client IP and scheme through a trusted proxy', function () {
    config(['trustedproxy.proxies' => ['REMOTE_ADDR']]);

    $request = proxyRequest('10.0.0.5', [
        'X-Forwarded-For' => '203.0.113.10',
        'X-Forwarded-Proto' => 'https',
    ]);

    (new TrustProxies)->handle($request, fn ($r) => $r);

    expect($request->ip())->toBe('203.0.113.10')
        ->and($request->secure())->toBeTrue();
});

it('ignores forwarded headers from an untrusted peer', function () {
    config(['trustedproxy.proxies' => ['10.0.0.5']]);

    $request = proxyRequest('198.51.100.7', [
        'X-Forwarded-For' => '203.0.113.10',
    ]);

    (new TrustProxies)->handle($request, fn ($r) => $r);

    expect($request->ip())->toBe('198.51.100.7');
});

it('picks the last untrusted hop, not a spoofed client', function () {
    // Клиент подделал цепочку; доверенный nginx дописал свой hop последним.
    config(['trustedproxy.proxies' => ['REMOTE_ADDR']]);

    $request = proxyRequest('10.0.0.5', [
        'X-Forwarded-For' => '203.0.113.10, 198.51.100.7',
    ]);

    (new TrustProxies)->handle($request, fn ($r) => $r);

    expect($request->ip())->toBe('198.51.100.7');
});

it('trusts nothing and keeps REMOTE_ADDR when config is empty', function () {
    config(['trustedproxy.proxies' => null]);

    $request = proxyRequest('198.51.100.7', [
        'X-Forwarded-For' => '203.0.113.10',
    ]);

    (new TrustProxies)->handle($request, fn ($r) => $r);

    expect($request->ip())->toBe('198.51.100.7');
});
