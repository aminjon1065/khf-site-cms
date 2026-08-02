<?php

use App\Models\MediaAsset;
use App\Models\News;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;

beforeEach(function () {
    seed(RolePermissionSeeder::class);
    Storage::fake('public');
});

function mediaLifecycleUser(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

function mediaLifecycleAsset(string $fileName = 'library.jpg'): Media
{
    $asset = MediaAsset::factory()->create(['alt' => 'Описание изображения']);

    return $asset
        ->addMedia(UploadedFile::fake()->image($fileName, 1200, 630))
        ->toMediaCollection('asset');
}

it('stores focal point and enforces accessible image metadata', function () {
    $media = mediaLifecycleAsset();

    actingAs(mediaLifecycleUser('editor'))->put("/media/{$media->id}", [
        'name' => 'Спасательные учения',
        'alt' => 'Спасатели работают у реки',
        'caption' => 'Фото КЧС',
        'is_decorative' => false,
        'focal_x' => 0.22,
        'focal_y' => 0.78,
    ])->assertRedirect();

    $asset = MediaAsset::query()->firstOrFail();
    $focalPoint = $media->fresh()->getCustomProperty('focal_point');

    expect($asset->alt)->toBe('Спасатели работают у реки')
        ->and($asset->is_decorative)->toBeFalse()
        ->and($focalPoint)->toMatchArray(['x' => 0.22, 'y' => 0.78]);

    actingAs(mediaLifecycleUser('editor'))->put("/media/{$media->id}", [
        'alt' => '',
        'is_decorative' => false,
        'focal_x' => 0.5,
        'focal_y' => 0.5,
    ])->assertSessionHasErrors('alt');
});

it('allows an explicitly decorative image and clears its alt text', function () {
    $media = mediaLifecycleAsset();

    actingAs(mediaLifecycleUser('editor'))->put("/media/{$media->id}", [
        'alt' => 'Старое описание',
        'is_decorative' => true,
        'focal_x' => 0.5,
        'focal_y' => 0.5,
    ])->assertRedirect();

    $asset = MediaAsset::query()->firstOrFail();

    expect($asset->is_decorative)->toBeTrue()
        ->and($asset->alt)->toBeNull();
});

it('tracks a library image copied into content and blocks deletion while used', function () {
    $source = mediaLifecycleAsset();
    $source
        ->setCustomProperty('focal_point', ['x' => 0.15, 'y' => 0.8])
        ->saveQuietly();

    actingAs(mediaLifecycleUser('editor'))->post('/news', [
        'title' => ['ru' => 'Материал с библиотечной обложкой', 'tg' => '', 'en' => ''],
        'cover_media_id' => $source->id,
        'cover_alt' => 'Спасатели',
        'action' => 'draft',
    ])->assertRedirect('/news');

    $news = News::query()->firstOrFail();
    $copy = $news->getFirstMedia('cover');

    expect($copy)->not->toBeNull()
        ->and($copy->getCustomProperty('source_media_id'))->toBe($source->id)
        ->and($copy->getCustomProperty('focal_point'))->toMatchArray(['x' => 0.15, 'y' => 0.8]);

    actingAs(mediaLifecycleUser('editor'))
        ->getJson("/media/{$source->id}/usages")
        ->assertOk()
        ->assertJsonPath('data.0.type', 'Новость')
        ->assertJsonPath('data.0.id', $news->id);

    actingAs(mediaLifecycleUser('admin'))
        ->delete("/media/{$source->id}")
        ->assertRedirect()
        ->assertSessionHas('error');

    expect(MediaAsset::query()->find($source->model_id))->not->toBeNull();
});

it('finds direct rich-text references to a library image', function () {
    $source = mediaLifecycleAsset();
    $news = News::factory()->create([
        'body' => [
            'ru' => '<p><img src="'.$source->getUrl().'" alt="Фото"></p>',
            'tg' => '',
            'en' => '',
        ],
    ]);

    actingAs(mediaLifecycleUser('editor'))
        ->getJson("/media/{$source->id}/usages")
        ->assertOk()
        ->assertJsonPath('data.0.id', $news->id)
        ->assertJsonPath('data.0.edit_url', "/news/{$news->id}/edit");
});

it('keeps finding a rich-text reference after the image itself is edited', function () {
    // media-library подписывает URL версией (`?v=updated_at`), а тело материала
    // хранит тот URL, что был на момент вставки. Любая правка самого файла —
    // подпись, фокус, alt — меняет версию, и поиск по полному URL перестаёт
    // видеть ссылку: «файл никем не используется», хотя он стоит в статье.
    $source = mediaLifecycleAsset();
    $news = News::factory()->create([
        'body' => [
            'ru' => '<p><img src="'.$source->getUrl().'" alt="Фото"></p>',
            'tg' => '',
            'en' => '',
        ],
    ]);

    $source->setCustomProperty('focal_point', ['x' => 0.4, 'y' => 0.6]);
    $source->updated_at = $source->updated_at->addMinute();
    $source->save();

    actingAs(mediaLifecycleUser('editor'))
        ->getJson("/media/{$source->id}/usages")
        ->assertOk()
        ->assertJsonPath('data.0.id', $news->id);

    // Список «где используется» — это же и есть защита от удаления. Раньше
    // после правки файла она отключалась молча: картинку из опубликованной
    // статьи разрешалось удалить, и в статье оставался битый <img>.
    actingAs(mediaLifecycleUser('admin'))
        ->delete("/media/{$source->id}")
        ->assertSessionHas('error');

    expect(Media::query()->find($source->id))->not->toBeNull();
});

it('moves an unused asset to trash and restores it without touching original or derivatives', function () {
    $media = mediaLifecycleAsset();
    $originalPath = $media->getPathRelativeToRoot();
    $derivativePath = $media->getPathRelativeToRoot('sm');
    Storage::disk('public')->put($derivativePath, 'generated derivative');

    actingAs(mediaLifecycleUser('admin'))
        ->delete("/media/{$media->id}")
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(MediaAsset::query()->find($media->model_id))->toBeNull()
        ->and(MediaAsset::onlyTrashed()->find($media->model_id))->not->toBeNull()
        ->and(Media::query()->find($media->id))->not->toBeNull();
    Storage::disk('public')->assertExists([$originalPath, $derivativePath]);

    actingAs(mediaLifecycleUser('admin'))
        ->get('/media?status=trash')
        ->assertInertia(fn ($page) => $page
            ->component('media/index')
            ->where('filters.status', 'trash')
            ->where('items.0.trashed', true),
        );

    actingAs(mediaLifecycleUser('admin'))
        ->post("/media/{$media->id}/restore")
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(MediaAsset::query()->find($media->model_id))->not->toBeNull();
    Storage::disk('public')->assertExists([$originalPath, $derivativePath]);
});

it('searches library metadata and exposes the media ux controls', function () {
    $source = mediaLifecycleAsset();
    $source->model->update([
        'title' => 'Скрытое название',
        'alt' => 'Уникальный поисковый текст',
        'caption' => 'Подпись',
    ]);

    actingAs(mediaLifecycleUser('editor'))
        ->get('/media?search='.rawurlencode('Уникальный'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('media/index')
            ->has('items', 1)
            ->where('items.0.id', $source->id),
        );

    $sourceCode = file_get_contents(resource_path('js/pages/media/index.tsx'));

    expect($sourceCode)
        ->toContain('Выбрать точку фокуса')
        ->toContain('Декоративное изображение')
        ->toContain('Где используется')
        ->toContain('Восстановить');
});
