<?php

use Illuminate\Console\Scheduling\Schedule;

it('keeps recovery commands and non destructive rollback rules in the runbook', function () {
    $runbook = file_get_contents(base_path('OPS_RUNBOOK.md'));

    expect($runbook)->toContain(
        'php artisan ops:production-check',
        'php artisan ops:backup',
        'php artisan ops:restore-drill',
        'php artisan schedule:interrupt',
        'php artisan queue:restart',
        'php artisan media:audit --regenerate',
        'Do not automatically roll',
        'back a database migration',
        'Never run destructive chaos against production',
    );
});

it('schedules monitoring backups restore drills and derivative cleanup without overlap', function () {
    $events = collect(app(Schedule::class)->events());
    $commands = $events->map(fn ($event): string => (string) $event->command)->implode("\n");
    $backup = $events->first(fn ($event): bool => str_contains((string) $event->command, 'ops:backup'));
    $restore = $events->first(fn ($event): bool => str_contains((string) $event->command, 'ops:restore-drill'));

    expect($commands)
        ->toContain('queue:monitor')
        ->toContain('ops:backup')
        ->toContain('ops:restore-drill')
        ->toContain('media:cleanup-orphans')
        ->and($backup)->not->toBeNull()
        ->and($backup->withoutOverlapping)->toBeTrue()
        ->and($restore)->not->toBeNull()
        ->and($restore->withoutOverlapping)->toBeTrue();
});
