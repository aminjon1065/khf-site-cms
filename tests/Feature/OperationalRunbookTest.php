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
        // Каждый сценарий хаоса должен быть привязан к тесту, который его
        // закрывает: иначе список остаётся благим намерением, а связь
        // «сценарий → чем проверен» живёт только в голове автора.
        'QueueTopologyTest',
        'RevalidateFrontendTest',
        'MediaConversionQueueTest',
        'HealthApiTest',
        'OperationalBackupTest',
        'MediaOrphanCleanupTest',
        'npm run load:probe',
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
        // Аудит производных и очистка корзины — тоже часть жизненного цикла
        // медиа, и оба должны выполняться сами: битая конверсия иначе живёт
        // до тех пор, пока её не заметят на сайте, а удалённый файл — вечно.
        ->toContain('media:audit')
        ->toContain('media:purge-trashed')
        ->and($backup)->not->toBeNull()
        ->and($backup->withoutOverlapping)->toBeTrue()
        ->and($restore)->not->toBeNull()
        ->and($restore->withoutOverlapping)->toBeTrue();
});
