<?php

use App\Jobs\PerformMediaConversions;
use App\Models\News;
use App\Models\User;
use Database\Seeders\RegionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\Conversions\ConversionCollection;
use Spatie\MediaLibrary\Conversions\FileManipulator;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;

beforeEach(function () {
    Storage::fake('public');
    Queue::fake();
    seed([RolePermissionSeeder::class, RegionSeeder::class]);
});

function newsWithGallery(): News
{
    config(['media-library.queue_conversions_after_database_commit' => false]);

    $news = News::factory()->published()->create([
        'slug' => 'gallery-item',
        'title' => ['ru' => 'Событие с галереей', 'tg' => '', 'en' => ''],
    ]);

    foreach (['Спасатели на склоне', 'Вертолёт МЧС'] as $name) {
        $media = $news
            ->addMedia(UploadedFile::fake()->image('shot.jpg', 1600, 1000))
            ->usingName($name)
            ->toMediaCollection('gallery');

        (new PerformMediaConversions(
            ConversionCollection::createForMedia($media),
            $media,
        ))->handle(app(FileManipulator::class));
    }

    return $news->refresh();
}

it('exposes the gallery on the detail view only, in editor order', function () {
    $news = newsWithGallery();

    $detail = $this->getJson('/api/v1/news/gallery-item?locale=ru')->assertOk();

    expect($detail->json('data.gallery_data'))->toHaveCount(2)
        ->and($detail->json('data.gallery_data.0.alt'))->toBe('Спасатели на склоне')
        ->and($detail->json('data.gallery_data.0.sources.fallback'))->not->toBeEmpty()
        ->and($detail->json('data.gallery_data.1.alt'))->toBe('Вертолёт МЧС');

    // Список галерею не тащит: карусель живёт только на странице материала.
    $list = $this->getJson('/api/v1/news?locale=ru')->assertOk();

    expect($list->json('data.0.gallery_data'))->toBeNull();
});

it('keeps gallery empty as an empty list, not null', function () {
    News::factory()->published()->create([
        'slug' => 'no-gallery',
        'title' => ['ru' => 'Без галереи', 'tg' => '', 'en' => ''],
    ]);

    $detail = $this->getJson('/api/v1/news/no-gallery?locale=ru')->assertOk();

    expect($detail->json('data.gallery_data'))->toBe([]);
});

it('lets an editor attach gallery shots on create and remove one on update', function () {
    $user = User::factory()->create();
    $user->assignRole('editor');

    actingAs($user)->post('/news', [
        'title' => ['ru' => 'Событие с фото', 'tg' => '', 'en' => ''],
        'action' => 'draft',
        'gallery' => [
            UploadedFile::fake()->image('one.jpg', 1200, 800),
            UploadedFile::fake()->image('two.jpg', 1200, 800),
        ],
    ])->assertRedirect('/news');

    $news = News::query()->first();
    expect($news->getMedia('gallery'))->toHaveCount(2);

    $firstId = $news->getMedia('gallery')->first()->getKey();

    actingAs($user)->put("/news/{$news->id}", [
        '_method' => 'put',
        'title' => ['ru' => 'Событие с фото', 'tg' => '', 'en' => ''],
        'action' => 'draft',
        'gallery_remove' => [$firstId],
    ])->assertRedirect();

    expect($news->refresh()->getMedia('gallery'))->toHaveCount(1);
});

it('keeps the inline gallery marker in the sanitized body', function () {
    actingAs(newsUserEditor())->post('/news', [
        'title' => ['ru' => 'Галерея внутри текста', 'tg' => '', 'en' => ''],
        'body' => [
            'ru' => '<p>До маркера.</p><figure class="cms-gallery"><span class="cms-gallery-chip">Фотогалерея</span></figure><p>После маркера.</p>',
            'tg' => '',
            'en' => '',
        ],
        'action' => 'draft',
    ])->assertRedirect('/news');

    $body = News::query()->latest('id')->first()->getTranslation('body', 'ru');

    expect($body)->toContain('<figure class="cms-gallery">')
        ->and($body)->toContain('<p>До маркера.</p>')
        ->and($body)->toContain('<p>После маркера.</p>');
});

function newsUserEditor(): User
{
    $user = User::factory()->create();
    $user->assignRole('editor');

    return $user;
}
