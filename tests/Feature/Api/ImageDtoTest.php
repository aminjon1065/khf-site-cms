<?php

use App\Jobs\PerformMediaConversions;
use App\Models\News;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\Conversions\ConversionCollection;
use Spatie\MediaLibrary\Conversions\FileManipulator;

beforeEach(function () {
    Storage::fake('public');
    Queue::fake();
    config(['media-library.queue_conversions_after_database_commit' => false]);
});

it('publishes structured derivative metadata without exposing the original as a source', function () {
    $news = News::factory()->published()->create([
        'title' => ['ru' => 'Тестовое изображение', 'tg' => '', 'en' => ''],
        'cover_alt' => 'Доступное описание',
    ]);
    $media = $news
        ->addMedia(UploadedFile::fake()->image('original.jpg', 1800, 1200))
        ->toMediaCollection('cover');
    $originalPath = $media->getPath();
    $originalChecksum = hash_file('sha256', $originalPath);

    (new PerformMediaConversions(
        ConversionCollection::createForMedia($media),
        $media,
    ))->handle(app(FileManipulator::class));

    $payload = $this->getJson('/api/v1/news?locale=ru')
        ->assertOk()
        ->assertJsonPath('data.0.image_data.version', 2)
        ->assertJsonPath('data.0.image_data.alt', 'Доступное описание')
        ->assertJsonPath('data.0.image_data.width', 1800)
        ->assertJsonPath('data.0.image_data.height', 1200)
        ->assertJsonPath('data.0.image_data.aspect_ratio', 1.5)
        ->assertJsonPath('data.0.image_data.focal_point.x', 0.5)
        ->assertJsonPath('data.0.image_data.focal_point.y', 0.5)
        ->assertJsonPath('data.0.image_data.status', 'ready')
        ->json('data.0');

    $imageData = $payload['image_data'];
    $sourceUrls = collect($imageData['sources'])->flatten(1)->pluck('url')->all();

    expect($payload)->toHaveKeys(['image', 'image_srcset', 'image_data'])
        ->and($imageData['checksum'])->toBe($originalChecksum)
        ->and($imageData['bytes'])->toBeGreaterThan(0)
        ->and($imageData['mime_type'])->toBe('image/jpeg')
        ->and($imageData['placeholder']['data_url'])->toStartWith('data:image/webp;base64,')
        ->and($imageData['placeholder']['color'])->toMatch('/^#[0-9a-f]{6}$/')
        ->and($imageData['sources']['fallback'])->toHaveCount(3)
        ->and(collect($imageData['sources']['fallback'])->pluck('width')->all())
        ->toBe([480, 960, 1600])
        ->and($sourceUrls)->not->toContain($payload['image'])
        ->and(hash_file('sha256', $originalPath))->toBe($originalChecksum);
});

it('returns a nullable structured image during the compatibility window', function () {
    News::factory()->published()->create();

    $this->getJson('/api/v1/news?locale=ru')
        ->assertOk()
        ->assertJsonPath('data.0.image', null)
        ->assertJsonPath('data.0.image_srcset', null)
        ->assertJsonPath('data.0.image_data', null);
});
