<?php

use Illuminate\Support\Facades\Storage;

it('only deletes orphan derivatives and never originals', function () {
    Storage::fake('public');
    config()->set([
        'media-library.disk_name' => 'public',
        'media-pipeline.public_disk' => 'public',
        'media-pipeline.private_disk' => 'public',
    ]);

    Storage::disk('public')->put('orphan/conversions/lost.webp', 'derivative');
    Storage::disk('public')->put('orphan/original.jpg', 'immutable original');

    // Файлы состариваем явно. `--grace-hours 0` ставит отсечку ровно на «сейчас»,
    // а mtime только что записанного файла попадает в ту же секунду — сравнение
    // оказывается на границе, и тест падал примерно в одном прогоне из трёх, не
    // имея к самой команде отношения. Час назад — это уже про поведение
    // (файл старше окна отсрочки), а не про то, чья секунда округлилась первой.
    $stale = now()->subHour()->getTimestamp();
    foreach (['orphan/conversions/lost.webp', 'orphan/original.jpg'] as $path) {
        touch(Storage::disk('public')->path($path), $stale);
    }

    $this->artisan('media:cleanup-orphans', ['--grace-hours' => 0])
        ->expectsOutputToContain('1 derivative(s) reported; originals untouched.')
        ->assertSuccessful();

    expect(Storage::disk('public')->exists('orphan/conversions/lost.webp'))->toBeTrue();

    $this->artisan('media:cleanup-orphans', ['--grace-hours' => 0, '--delete' => true])
        ->expectsOutputToContain('1 derivative(s) deleted; originals untouched.')
        ->assertSuccessful();

    expect(Storage::disk('public')->exists('orphan/conversions/lost.webp'))->toBeFalse()
        ->and(Storage::disk('public')->get('orphan/original.jpg'))->toBe('immutable original');
});
