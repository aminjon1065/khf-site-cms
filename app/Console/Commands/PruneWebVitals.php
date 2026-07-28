<?php

namespace App\Console\Commands;

use App\Models\WebVitalSample;
use Illuminate\Console\Command;

class PruneWebVitals extends Command
{
    protected $signature = 'rum:prune {--days=35 : Retention period in days}';

    protected $description = 'Delete expired anonymous Web Vitals samples';

    public function handle(): int
    {
        $days = max(1, min(365, (int) $this->option('days')));
        $deleted = WebVitalSample::query()
            ->where('created_at', '<', now()->subDays($days))
            ->delete();

        $this->info("Deleted {$deleted} Web Vitals sample(s) older than {$days} days.");

        return self::SUCCESS;
    }
}
