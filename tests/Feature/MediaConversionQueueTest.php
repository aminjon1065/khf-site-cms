<?php

use App\Jobs\PerformMediaConversions;
use App\Models\MediaAsset;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\Conversions\ConversionCollection;
use Spatie\MediaLibrary\Conversions\FileManipulator;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\mock;
use function Pest\Laravel\seed;

beforeEach(function () {
    seed(RolePermissionSeeder::class);
    Storage::fake('public');
});

function mediaConversionJob(Media $media): PerformMediaConversions
{
    return new PerformMediaConversions(
        ConversionCollection::createForMedia($media),
        $media,
    );
}

it('queues conversions without changing the original and creates derivatives in the job', function () {
    Queue::fake();
    config(['media-library.queue_conversions_after_database_commit' => false]);
    $asset = MediaAsset::factory()->create();
    $media = $asset
        ->addMedia(UploadedFile::fake()->image('original.jpg', 1800, 1200))
        ->toMediaCollection('asset');

    $originalChecksum = hash_file('sha256', $media->getPath());

    Queue::assertPushedOn('media', PerformMediaConversions::class);
    Storage::disk('public')->assertMissing($media->getPathRelativeToRoot('sm'));

    mediaConversionJob($media)->handle(app(FileManipulator::class));

    $media->refresh();
    expect(hash_file('sha256', $media->getPath()))->toBe($originalChecksum)
        ->and($media->getCustomProperty('conversion_status'))->toBe('ready');

    foreach (['sm', 'md', 'lg'] as $conversion) {
        Storage::disk('public')->assertExists(
            $media->getPathRelativeToRoot($conversion),
        );
    }

    $fileCount = count(Storage::disk('public')->allFiles());
    mediaConversionJob($media)->handle(app(FileManipulator::class));

    expect(Storage::disk('public')->allFiles())->toHaveCount($fileCount)
        ->and(hash_file('sha256', $media->getPath()))->toBe($originalChecksum);
});

it('records a bounded error when conversion processing fails', function () {
    Queue::fake();
    config(['media-library.queue_conversions_after_database_commit' => false]);
    $asset = MediaAsset::factory()->create();
    $media = $asset
        ->addMedia(UploadedFile::fake()->image('broken.jpg'))
        ->toMediaCollection('asset');
    $fileManipulator = mock(FileManipulator::class);
    $fileManipulator
        ->shouldReceive('performConversions')
        ->once()
        ->andThrow(new RuntimeException(str_repeat('failure ', 100)));
    $job = mediaConversionJob($media);

    expect(fn () => $job->handle($fileManipulator))
        ->toThrow(RuntimeException::class);

    $media->refresh();
    expect($media->getCustomProperty('conversion_status'))->toBe('failed')
        ->and(mb_strlen((string) $media->getCustomProperty('conversion_error')))
        ->toBeLessThanOrEqual(500);
});

it('lets an authorized editor retry a failed image conversion', function () {
    Queue::fake();
    config(['media-library.queue_conversions_after_database_commit' => false]);
    $editor = User::factory()->create();
    $editor->assignRole('editor');
    $asset = MediaAsset::factory()->create();
    $media = $asset
        ->addMedia(UploadedFile::fake()->image('retry.jpg'))
        ->toMediaCollection('asset');
    $media->setCustomProperty('conversion_status', 'failed')->saveQuietly();
    Queue::fake();

    actingAs($editor)
        ->post(route('media.conversions.retry', $media))
        ->assertRedirect()
        ->assertSessionHas('success');

    Queue::assertPushedOn('media', PerformMediaConversions::class);
    expect($media->fresh()->getCustomProperty('conversion_status'))->toBe('pending');
});

it('forbids retrying a conversion without media create permission', function () {
    Queue::fake();
    config(['media-library.queue_conversions_after_database_commit' => false]);
    $user = User::factory()->create();
    $asset = MediaAsset::factory()->create();
    $media = $asset
        ->addMedia(UploadedFile::fake()->image('forbidden-retry.jpg'))
        ->toMediaCollection('asset');
    Queue::fake();

    actingAs($user)
        ->post(route('media.conversions.retry', $media))
        ->assertForbidden();

    Queue::assertNothingPushed();
});

it('keeps queue retry timing above the media job timeout', function () {
    $jobTimeout = (new ReflectionClass(PerformMediaConversions::class))
        ->getDefaultProperties()['timeout'];

    expect(config('queue.connections.database.retry_after'))
        ->toBeGreaterThan($jobTimeout)
        ->and(config('queue.connections.redis.retry_after'))
        ->toBeGreaterThan($jobTimeout);
});

it('dispatches media conversions only after database commit by default', function () {
    expect(config('media-library.queue_conversions_after_database_commit'))->toBeTrue();
});
