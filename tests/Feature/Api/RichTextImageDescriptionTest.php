<?php

use App\Models\MediaAsset;
use App\Models\News;
use App\Services\PublicationChecklist;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/*
 * A photo in a text tells readers who can't see it what it shows: the
 * description written in the text, else the one in the media library. The
 * sanitizer used to fill a missing description with the file name, so the
 * site read out «IMG_2034.jpg».
 */

beforeEach(function () {
    Storage::fake('public');
    Queue::fake();
});

/**
 * @param  array<string, mixed>  $asset
 */
function libraryPhoto(string $file, array $asset): Media
{
    return MediaAsset::factory()->create($asset)
        ->addMedia(UploadedFile::fake()->image($file))
        ->toMediaCollection('asset');
}

function inlinePhoto(Media $media, string $alt): string
{
    return '<p><img src="'.$media->getUrl().'" data-media-id="'.$media->id.'" alt="'.$alt.'"></p>';
}

it('gives the site the library description instead of a file name', function () {
    $described = libraryPhoto('IMG_2034.jpg', ['alt' => 'Спасатели на учениях в Хатлоне']);
    $decorative = libraryPhoto('ornament.png', ['is_decorative' => true]);
    $captioned = libraryPhoto('scheme.jpg', []);

    News::factory()->published()->create([
        'slug' => 'photo-descriptions',
        'body' => [
            'ru' => inlinePhoto($described, 'IMG_2034.jpg')
                .inlinePhoto($decorative, 'ornament.png')
                .inlinePhoto($captioned, 'Схема эвакуации из школы'),
            'tg' => '<p>Матн</p>',
            'en' => '',
        ],
    ]);

    $body = $this->getJson('/api/v1/news/photo-descriptions?locale=ru')
        ->assertOk()
        ->json('data.body');

    expect($body)
        ->toContain('alt="Спасатели на учениях в Хатлоне"')
        ->toContain('alt=""')
        ->toContain('alt="Схема эвакуации из школы"')
        ->not->toContain('alt="IMG_2034.jpg"')
        ->not->toContain('alt="ornament.png"');
});

it('reports photos in the text that tell blind readers nothing', function () {
    $bare = libraryPhoto('IMG_1001.jpg', []);
    $decorative = libraryPhoto('line.png', ['is_decorative' => true]);
    $described = libraryPhoto('IMG_1002.jpg', ['alt' => 'Пункт временного размещения']);

    $news = News::factory()->create([
        'body' => [
            'ru' => inlinePhoto($bare, 'IMG_1001.jpg')
                .inlinePhoto($decorative, '')
                .inlinePhoto($described, ''),
            'tg' => inlinePhoto($bare, ''),
            'en' => '',
        ],
    ]);

    $item = collect(app(PublicationChecklist::class)->inspect($news))
        ->firstWhere('key', 'image_descriptions');

    expect($item)->toMatchArray(['ok' => false, 'blocking' => false])
        ->and($item['detail'])->toStartWith('Без описания: 2.');
});

it('takes a photo the editor marked decorative in the text as described', function () {
    $ornament = libraryPhoto('divider.jpg', ['alt' => 'Разделитель']);
    $marked = '<p><img src="'.$ornament->getUrl().'" data-media-id="'.$ornament->id.'" data-decorative="true" alt=""></p>';

    $news = News::factory()->published()->create([
        'slug' => 'decorative-in-text',
        'body' => ['ru' => $marked, 'tg' => '<p>Матн</p>', 'en' => ''],
    ]);

    $item = collect(app(PublicationChecklist::class)->inspect($news))
        ->firstWhere('key', 'image_descriptions');
    $body = $this->getJson('/api/v1/news/decorative-in-text?locale=ru')->json('data.body');

    expect($item)->toMatchArray(['ok' => true])
        // Decorative in this text even though the library describes it.
        ->and($body)->toContain('alt=""')
        ->and($body)->not->toContain('alt="Разделитель"');
});

it('leaves the photo check out of a text without photos', function () {
    $news = News::factory()->create();

    $keys = collect(app(PublicationChecklist::class)->inspect($news))->pluck('key');

    expect($keys)->not->toContain('image_descriptions');
});
