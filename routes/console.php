<?php

use App\Console\Commands\ProcessScheduledContent;
use App\Console\Commands\PruneWebVitals;
use App\Jobs\QueueHeartbeat;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Publish scheduled material, auto-complete expired alerts and send expiry notices.
Schedule::command(ProcessScheduledContent::class)->everyFiveMinutes()->withoutOverlapping();
Schedule::job(new QueueHeartbeat)->everyMinute();
Schedule::command(PruneWebVitals::class)->dailyAt('03:15')->withoutOverlapping();

$queueNames = array_values(config('queue.names'));
$redisQueues = implode(',', array_map(fn (string $queue): string => "redis:{$queue}", $queueNames));
$databaseQueues = implode(',', array_map(fn (string $queue): string => "database:{$queue}", $queueNames));
$monitorMax = (int) config('queue.monitor_max');

Schedule::command("queue:monitor {$redisQueues} --max={$monitorMax}")
    ->everyMinute()
    ->withoutOverlapping();
Schedule::command("queue:monitor {$databaseQueues} --max={$monitorMax}")
    ->everyMinute()
    ->withoutOverlapping();
