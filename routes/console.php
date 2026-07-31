<?php

use App\Console\Commands\CleanupOrphanedMedia;
use App\Console\Commands\CreateOperationalBackup;
use App\Console\Commands\ProcessScheduledContent;
use App\Console\Commands\PruneWebVitals;
use App\Console\Commands\RestoreBackupDrill;
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
Schedule::command(CreateOperationalBackup::class)
    ->dailyAt('02:15')
    ->when(fn (): bool => (bool) config('operations.backups.enabled'))
    ->withoutOverlapping(180);
Schedule::command(RestoreBackupDrill::class)
    ->weeklyOn(0, '03:15')
    ->when(fn (): bool => (bool) config('operations.backups.enabled'))
    ->withoutOverlapping(180);
Schedule::command(CleanupOrphanedMedia::class, ['--delete', '--grace-hours=24'])
    ->weeklyOn(1, '04:15')
    ->withoutOverlapping(180);

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
