<?php

use App\Models\MediaAsset;
use App\Models\News;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

use function Pest\Laravel\artisan;

beforeEach(function () {
    Storage::fake('public');
    Queue::fake();
});

function trashedAssetFrom(string $fileName, int $daysAgo): MediaAsset
{
    $asset = MediaAsset::factory()->create();
    $asset->addMedia(UploadedFile::fake()->image($fileName, 64, 64))->toMediaCollection('asset');
    $asset->delete();
    $asset->forceFill(['deleted_at' => now()->subDays($daysAgo)])->saveQuietly();

    return $asset->fresh() ?? $asset;
}

it('reports without deleting until asked', function () {
    // По умолчанию — только отчёт, как и у чистки осиротевших производных:
    // команда, которая по умолчанию удаляет файлы, рано или поздно удалит их
    // не тогда, когда надо.
    $asset = trashedAssetFrom('old.jpg', 40);

    artisan('media:purge-trashed', ['--grace-days' => 30])
        ->expectsOutputToContain('1 asset(s) reported')
        ->assertSuccessful();

    expect(MediaAsset::onlyTrashed()->find($asset->getKey()))->not->toBeNull();
});

it('permanently removes trashed assets past the grace window, with their files', function () {
    $asset = trashedAssetFrom('old.jpg', 40);
    $media = $asset->getFirstMedia('asset');
    $path = $media->getPathRelativeToRoot();

    Storage::disk('public')->assertExists($path);

    artisan('media:purge-trashed', ['--delete' => true, '--grace-days' => 30])
        ->expectsOutputToContain('1 asset(s) deleted')
        ->assertSuccessful();

    expect(MediaAsset::withTrashed()->find($asset->getKey()))->toBeNull()
        ->and(Media::query()->find($media->getKey()))->toBeNull();
    Storage::disk('public')->assertMissing($path);
});

it('leaves everything still inside the grace window alone', function () {
    // Окно отсрочки — это и есть «удалили по ошибке»; без него корзина
    // бессмысленна.
    $asset = trashedAssetFrom('recent.jpg', 3);

    artisan('media:purge-trashed', ['--delete' => true, '--grace-days' => 30])
        ->expectsOutputToContain('0 asset(s) deleted')
        ->assertSuccessful();

    expect(MediaAsset::onlyTrashed()->find($asset->getKey()))->not->toBeNull();
});

it('never deletes a file a material still points at', function () {
    // Ссылка могла появиться уже после удаления — например, редактор
    // восстановил старую ревизию материала. Поэтому использование
    // проверяется в момент окончательного удаления, а не только тогда, когда
    // нажали «удалить».
    $asset = trashedAssetFrom('referenced.jpg', 60);
    $media = $asset->getFirstMedia('asset');

    News::factory()->create([
        'body' => [
            'ru' => '<p><img src="'.$media->getUrl().'" alt="Фото"></p>',
            'tg' => '',
            'en' => '',
        ],
    ]);

    artisan('media:purge-trashed', ['--delete' => true, '--grace-days' => 30])
        ->expectsOutputToContain('файл используется в материалах')
        ->expectsOutputToContain('0 asset(s) deleted, 1 kept')
        ->assertSuccessful();

    expect(MediaAsset::onlyTrashed()->find($asset->getKey()))->not->toBeNull();
    Storage::disk('public')->assertExists($media->getPathRelativeToRoot());
});
