<?php

it('accepts a safe production deployment contract', function () {
    config()->set([
        'app.debug' => false,
        'cache.default' => 'failover',
        'queue.default' => 'failover',
        'filesystems.disks.public.url' => 'https://media.khf.tj/storage',
        'production.php_fpm_max_children' => 8,
    ]);

    $this->artisan('ops:production-check --skip-runtime')
        ->expectsOutputToContain('Production configuration and runtime checks passed.')
        ->assertSuccessful();
});

it('reports every unsafe production deployment setting', function () {
    config()->set([
        'app.debug' => true,
        'cache.default' => 'file',
        'queue.default' => 'sync',
        'filesystems.disks.public.url' => 'http://cms.test/storage',
        'production.php_fpm_max_children' => 0,
    ]);

    $this->artisan('ops:production-check --skip-runtime')
        ->expectsOutputToContain('APP_DEBUG must be false.')
        ->expectsOutputToContain('CACHE_STORE must use the failover production topology.')
        ->expectsOutputToContain('QUEUE_CONNECTION must use the failover production topology.')
        ->expectsOutputToContain('MEDIA_PUBLIC_URL must use HTTPS (origin or CDN).')
        ->expectsOutputToContain('PHP_FPM_MAX_CHILDREN must be set from the measured worker memory budget.')
        ->assertFailed();
});
