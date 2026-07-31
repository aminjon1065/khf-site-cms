<?php

use App\Jobs\PerformMediaConversions;
use App\Models\MediaAsset;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\Conversions\FileManipulator;

use function Pest\Laravel\artisan;

beforeEach(function () {
    Storage::fake('public');
    Queue::fake();
    config(['media-library.queue_conversions_after_database_commit' => false]);
});

it('fails on missing derivatives and can queue their regeneration', function () {
    $asset = MediaAsset::factory()->create();
    $asset
        ->addMedia(UploadedFile::fake()->image('pending.jpg', 800, 600))
        ->toMediaCollection('asset');
    Queue::fake();

    artisan('media:audit')
        ->expectsOutputToContain('missing derivative sm')
        ->expectsOutputToContain('1 checked, 1 broken, 0 queued')
        ->assertFailed();

    artisan('media:audit', ['--regenerate' => true])
        ->expectsOutputToContain('1 checked, 1 broken, 1 queued')
        ->assertSuccessful();

    Queue::assertPushedOn('media', PerformMediaConversions::class);
});

it('reports a clean library after every derivative exists', function () {
    Queue::fake();
    $asset = MediaAsset::factory()->create();
    $media = $asset
        ->addMedia(UploadedFile::fake()->image('ready.jpg', 800, 600))
        ->toMediaCollection('asset');

    mediaConversionJob($media)->handle(app(
        FileManipulator::class,
    ));

    artisan('media:audit')
        ->expectsOutputToContain('1 checked, 0 broken, 0 queued')
        ->assertSuccessful();
});
