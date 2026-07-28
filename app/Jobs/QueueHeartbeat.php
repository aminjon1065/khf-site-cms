<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

class QueueHeartbeat implements ShouldQueue
{
    use Queueable;

    public function __construct()
    {
        $this->onQueue((string) config('queue.names.critical'));
    }

    public function handle(): void
    {
        Cache::put(
            'health.queue.last_run',
            now()->toIso8601String(),
            now()->addHour(),
        );
    }
}
