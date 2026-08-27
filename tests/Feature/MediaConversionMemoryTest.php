<?php

use App\Models\MediaAsset;
use Illuminate\Http\UploadedFile;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Spatie\MediaLibrary\Conversions\FileManipulator;

// Длинная очередь конверсий на GD упиралась в memory_limit и падала фатальной
// ошибкой. Фатальная ошибка не проходит через `failed()`: записи в failed_jobs
// нет, в логе воркера нет, статус media навсегда остаётся `pending`. Задача
// поднимает предел сама, чтобы это не зависело от того, как запущен воркер.

it('raises the process memory limit for the duration of a conversion', function () {
    $original = ini_get('memory_limit');
    ini_set('memory_limit', '128M');

    config(['media-library.conversion_memory_limit' => '512M']);

    $asset = MediaAsset::factory()->create();
    $media = $asset
        ->addMedia(UploadedFile::fake()->image('probe.jpg', 40, 30))
        ->toMediaCollection('asset');

    mediaConversionJob($media)->handle(app(FileManipulator::class));

    expect(ini_get('memory_limit'))->toBe('512M');

    ini_set('memory_limit', $original);
});

it('never lowers a limit the worker was deliberately started with', function () {
    $original = ini_get('memory_limit');
    ini_set('memory_limit', '1G');

    config(['media-library.conversion_memory_limit' => '512M']);

    $asset = MediaAsset::factory()->create();
    $media = $asset
        ->addMedia(UploadedFile::fake()->image('probe.jpg', 40, 30))
        ->toMediaCollection('asset');

    mediaConversionJob($media)->handle(app(FileManipulator::class));

    expect(ini_get('memory_limit'))->toBe('1G');

    ini_set('memory_limit', $original);
});

it('leaves an unlimited process untouched', function () {
    $original = ini_get('memory_limit');
    ini_set('memory_limit', '-1');

    config(['media-library.conversion_memory_limit' => '512M']);

    $asset = MediaAsset::factory()->create();
    $media = $asset
        ->addMedia(UploadedFile::fake()->image('probe.jpg', 40, 30))
        ->toMediaCollection('asset');

    mediaConversionJob($media)->handle(app(FileManipulator::class));

    expect(ini_get('memory_limit'))->toBe('-1');

    ini_set('memory_limit', $original);
});

it('is protected against overlap and can be retried after a release', function () {
    // Задача защищена WithoutOverlapping: пока прошлая конверсия того же медиа
    // держит блокировку — например, её воркер умер от фатальной ошибки и
    // блокировка живёт до expireAfter, — попытка возвращается в очередь.
    //
    // Значит одной попытки мало: при tries=1 такой возврат сразу превращается
    // в MaxAttemptsExceededException, и конверсия теряется навсегда, а статус
    // media остаётся `pending`. Ровно это и наблюдалось, когда воркер
    // запускали вручную с --tries=1.
    //
    // Проверяем именно это: наличие middleware с конечным сроком блокировки и
    // запас попыток. Прежняя версия теста утверждала это через backoff(), но
    // backoff применяется к падениям, а не к возврату из middleware, — то есть
    // проверяла не тот механизм.
    $asset = MediaAsset::factory()->create();
    $media = $asset
        ->addMedia(UploadedFile::fake()->image('probe.jpg', 40, 30))
        ->toMediaCollection('asset');

    $job = mediaConversionJob($media);
    $overlapping = collect($job->middleware())
        ->first(fn (object $m): bool => $m instanceof WithoutOverlapping);

    expect($job->tries)->toBeGreaterThan(1)
        ->and($overlapping)->not->toBeNull()
        // Блокировка обязана истекать сама: иначе смерть воркера заперла бы
        // конверсии этого медиа навсегда.
        ->and($overlapping->expiresAfter)->not->toBeNull()
        ->and($overlapping->expiresAfter)->toBeGreaterThan(0);
});

it('keeps GD as the driver so transparent PNGs survive conversion', function () {
    // Переключение на Imagick «чинило» падения по памяти, но теряло
    // альфа-канал: см. MediaConversionQueueTest, где sm-фолбэк прозрачного
    // PNG обязан сохранять alpha 127.
    expect(config('media-library.image_driver'))->toBe('gd');
});
