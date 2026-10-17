<?php

it('accepts a safe production deployment contract', function () {
    config()->set([
        'app.debug' => false,
        'cache.default' => 'failover',
        'queue.default' => 'failover',
        'filesystems.disks.public.url' => 'https://media.khf.tj/storage',
        'production.php_fpm_max_children' => 8,
        'app.key' => 'base64:'.base64_encode(random_bytes(32)),
        'app.url' => 'https://cms.khf.tj',
        'session.secure' => true,
        'cors.allowed_origins' => ['https://khf.tj'],
        'trustedproxy.proxies' => ['172.30.0.0/24'],
        'operations.backups.enabled' => true,
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
        // 33 байта вместо 32 — такой ключ ломал вход в CMS (500 на /login),
        // а проверка его пропускала.
        'app.key' => 'base64:'.base64_encode(str_repeat('k', 33)),
        'app.url' => 'http://cms.test',
        'session.secure' => false,
        'cors.allowed_origins' => ['*'],
        'trustedproxy.proxies' => null,
        'operations.backups.enabled' => false,
    ]);

    $this->artisan('ops:production-check --skip-runtime')
        ->expectsOutputToContain('APP_DEBUG must be false.')
        ->expectsOutputToContain('CACHE_STORE must use the failover production topology.')
        ->expectsOutputToContain('QUEUE_CONNECTION must use the failover production topology.')
        ->expectsOutputToContain('MEDIA_PUBLIC_URL must use HTTPS (origin or CDN).')
        ->expectsOutputToContain('PHP_FPM_MAX_CHILDREN must be set from the measured worker memory budget.')
        ->expectsOutputToContain('APP_KEY is missing or has the wrong length')
        ->expectsOutputToContain('APP_URL must use HTTPS.')
        ->expectsOutputToContain('SESSION_SECURE_COOKIE must be true.')
        ->expectsOutputToContain('CORS_ALLOWED_ORIGINS must list the public site origins, not *.')
        ->expectsOutputToContain('TRUSTED_PROXIES must name the reverse proxy in front of PHP.')
        ->expectsOutputToContain('BACKUP_ENABLED must be true')
        ->assertFailed();
});

it('warns about settings that silently switch part of the site off', function () {
    config()->set([
        'app.debug' => false,
        'cache.default' => 'failover',
        'queue.default' => 'failover',
        'filesystems.disks.public.url' => 'https://media.khf.tj/storage',
        'production.php_fpm_max_children' => 8,
        'app.key' => 'base64:'.base64_encode(random_bytes(32)),
        'app.url' => 'https://cms.khf.tj',
        'session.secure' => true,
        'cors.allowed_origins' => ['https://khf.tj'],
        'trustedproxy.proxies' => ['172.30.0.0/24'],
        'operations.backups.enabled' => true,
        'mail.default' => 'log',
        'services.frontend.revalidation_secret' => null,
        'services.frontend.rum_secret' => '',
        'services.frontend.site_api_key' => null,
        'services.telegram_alerts.bot_token' => null,
    ]);

    // Предупреждения не останавливают деплой: почту и Telegram настраивают
    // позже, но дежурный должен видеть, чего пока нет.
    $this->artisan('ops:production-check --skip-runtime')
        ->expectsOutputToContain('MAIL_MAILER=log')
        ->expectsOutputToContain('FRONTEND_REVALIDATION_SECRET is empty')
        ->expectsOutputToContain('RUM_INGEST_SECRET is empty')
        ->expectsOutputToContain('SITE_API_KEY is empty')
        ->expectsOutputToContain('TELEGRAM_ALERTS_* is not set')
        ->expectsOutputToContain('Production configuration and runtime checks passed.')
        ->assertSuccessful();
});
