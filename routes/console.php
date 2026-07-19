<?php

use App\Console\Commands\ProcessScheduledContent;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Publish scheduled material, auto-complete expired alerts and send expiry notices.
Schedule::command(ProcessScheduledContent::class)->everyFiveMinutes()->withoutOverlapping();
