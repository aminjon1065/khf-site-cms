<?php

namespace App\Console\Commands;

use App\Support\PendingMigrations;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Encryption\Encrypter;

#[Signature('ops:production-check {--skip-runtime : Validate the deploy contract without inspecting this CLI runtime}')]
#[Description('Fail when Laravel, PHP or media delivery is not configured for production')]
class ProductionReadinessCheck extends Command
{
    public function handle(): int
    {
        $failures = $this->configurationFailures();

        if (! (bool) $this->option('skip-runtime')) {
            $failures = [...$failures, ...$this->runtimeFailures()];
        }

        // Не мешают запуску, но без них часть сайта молча не работает: о
        // таком дежурный должен узнать при деплое, а не от гражданина.
        foreach ($this->configurationWarnings() as $warning) {
            $this->components->warn($warning);
        }

        if ($failures !== []) {
            foreach ($failures as $failure) {
                $this->components->error($failure);
            }

            return self::FAILURE;
        }

        $this->components->info('Production configuration and runtime checks passed.');

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function configurationFailures(): array
    {
        $failures = [];
        $mediaUrl = (string) config('filesystems.disks.public.url');

        if ((bool) config('app.debug')) {
            $failures[] = 'APP_DEBUG must be false.';
        }

        if (config('cache.default') !== 'failover') {
            $failures[] = 'CACHE_STORE must use the failover production topology.';
        }

        if (config('queue.default') !== 'failover') {
            $failures[] = 'QUEUE_CONNECTION must use the failover production topology.';
        }

        if (! str_starts_with($mediaUrl, 'https://')) {
            $failures[] = 'MEDIA_PUBLIC_URL must use HTTPS (origin or CDN).';
        }

        if ((int) config('production.php_fpm_max_children') < 1) {
            $failures[] = 'PHP_FPM_MAX_CHILDREN must be set from the measured worker memory budget.';
        }

        if (! $this->hasValidAppKey()) {
            $failures[] = 'APP_KEY is missing or has the wrong length; generate one with php artisan key:generate --show.';
        }

        if (! str_starts_with((string) config('app.url'), 'https://')) {
            $failures[] = 'APP_URL must use HTTPS.';
        }

        if (config('session.secure') !== true) {
            $failures[] = 'SESSION_SECURE_COOKIE must be true.';
        }

        $origins = (array) config('cors.allowed_origins');
        if ($origins === [] || in_array('*', $origins, true)) {
            $failures[] = 'CORS_ALLOWED_ORIGINS must list the public site origins, not *.';
        }

        // Без доверенных прокси все лимиты по IP (обращения 10 в минуту,
        // публичный API) считаются по адресу nginx — на всех сразу (audit I-1).
        if (config('trustedproxy.proxies') === null) {
            $failures[] = 'TRUSTED_PROXIES must name the reverse proxy in front of PHP.';
        }

        if (config('operations.backups.enabled') !== true) {
            $failures[] = 'BACKUP_ENABLED must be true: the scheduler makes the daily backup and the weekly restore drill.';
        }

        return $failures;
    }

    /**
     * @return list<string>
     */
    private function configurationWarnings(): array
    {
        $warnings = [];

        if (in_array(config('mail.default'), ['log', 'array'], true)) {
            $warnings[] = 'MAIL_MAILER='.config('mail.default').': staff password resets and citizen status emails are not delivered.';
        }

        if (blank(config('services.frontend.revalidation_secret'))) {
            $warnings[] = 'FRONTEND_REVALIDATION_SECRET is empty: the site shows edits only after its 60-second cache window.';
        }

        if (blank(config('services.frontend.rum_secret'))) {
            $warnings[] = 'RUM_INGEST_SECRET is empty: page-view statistics and Web Vitals stay empty.';
        }

        if (blank(config('services.frontend.site_api_key'))) {
            $warnings[] = 'SITE_API_KEY is empty: the site server shares the per-IP public API limit with every visitor.';
        }

        if (blank(config('services.telegram_alerts.bot_token')) || blank(config('services.telegram_alerts.chat_id'))) {
            $warnings[] = 'TELEGRAM_ALERTS_* is not set: critical errors reach nobody on duty.';
        }

        return $warnings;
    }

    private function hasValidAppKey(): bool
    {
        $key = (string) config('app.key');

        if (str_starts_with($key, 'base64:')) {
            $key = base64_decode(substr($key, 7), true);
        }

        return is_string($key) && Encrypter::supported($key, (string) config('app.cipher'));
    }

    /**
     * @return list<string>
     */
    private function runtimeFailures(): array
    {
        $failures = [];
        $expectedMemory = (int) config('production.opcache.memory_consumption');
        $expectedFiles = (int) config('production.opcache.max_accelerated_files');

        if (! app()->isProduction()) {
            $failures[] = 'APP_ENV must be production.';
        }

        if (! app()->configurationIsCached()) {
            $failures[] = 'Laravel configuration is not cached; run php artisan optimize.';
        }

        if (filter_var(ini_get('opcache.enable'), FILTER_VALIDATE_BOOL) !== true) {
            $failures[] = 'OPcache is disabled.';
        }

        if ((int) ini_get('opcache.memory_consumption') < $expectedMemory) {
            $failures[] = "opcache.memory_consumption must be at least {$expectedMemory} MB.";
        }

        if ((int) ini_get('opcache.max_accelerated_files') < $expectedFiles) {
            $failures[] = "opcache.max_accelerated_files must be at least {$expectedFiles}.";
        }

        $pendingMigrations = app(PendingMigrations::class)->names();
        if ($pendingMigrations !== []) {
            $failures[] = 'Database schema is behind the codebase; run php artisan migrate --force (pending: '.implode(', ', $pendingMigrations).').';
        }

        return $failures;
    }
}
