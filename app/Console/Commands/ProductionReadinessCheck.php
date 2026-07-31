<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

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

        return $failures;
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

        return $failures;
    }
}
