<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

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

it('only tells the operator to run commands that actually exist', function () {
    // Runbook читают в четыре утра, когда что-то уже сломалось. Опечатка или
    // переименованная команда в нём — это не «неточность в документе», а
    // потерянные минуты в инциденте. Поэтому каждая упомянутая команда artisan
    // проверяется по реальному реестру.
    $runbook = file_get_contents(base_path('OPS_RUNBOOK.md'));
    preg_match_all('/php artisan ([a-z0-9:_-]+)/', $runbook, $matches);

    $mentioned = array_values(array_unique($matches[1]));
    $registered = array_keys(Artisan::all());

    expect($mentioned)->not->toBeEmpty()
        ->and(array_values(array_diff($mentioned, $registered)))->toBe([]);
});

it('only points at health endpoints that are actually routed', function () {
    $runbook = file_get_contents(base_path('OPS_RUNBOOK.md'));
    preg_match_all('#https://cms\.khf\.tj(/api/v1/[a-z0-9/_-]+)#', $runbook, $matches);

    $paths = array_values(array_unique($matches[1]));

    expect($paths)->not->toBeEmpty();

    foreach ($paths as $path) {
        expect(Route::has('api.'.trim(str_replace('/api/v1/', '', $path), '/')))
            ->toBeTrue("Runbook ссылается на {$path}, но такого маршрута нет");
    }
});
