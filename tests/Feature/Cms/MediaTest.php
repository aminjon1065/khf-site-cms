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

function mediaUser(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

it('lets an authorized user open the media library', function () {
    actingAs(mediaUser('editor'))->get('/media')->assertOk();
});

it('forbids a user without any role', function () {
    actingAs(User::factory()->create())->get('/media')->assertForbidden();
});

it('uploads a reusable asset into the library', function () {
    actingAs(mediaUser('editor'))->post('/media', [
        'file' => UploadedFile::fake()->image('photo.jpg'),
        'title' => 'Фото учений',
    ])->assertRedirect();

    $asset = MediaAsset::query()->first();

    expect($asset)->not->toBeNull()
        ->and($asset->title)->toBe('Фото учений')
        ->and($asset->getFirstMedia('asset'))->not->toBeNull();
});

it('forbids a viewer from uploading', function () {
    actingAs(mediaUser('viewer'))->post('/media', [
        'file' => UploadedFile::fake()->image('photo.jpg'),
    ])->assertForbidden();

    expect(MediaAsset::query()->count())->toBe(0);
});

it('rejects a disallowed file type', function () {
    actingAs(mediaUser('editor'))->post('/media', [
        'file' => UploadedFile::fake()->create('malware.exe', 10, 'application/octet-stream'),
    ])->assertSessionHasErrors('file');

    expect(MediaAsset::query()->count())->toBe(0);
});

it('rejects an SVG upload (stored-XSS vector)', function () {
    actingAs(mediaUser('editor'))->post('/media', [
        'file' => UploadedFile::fake()->create('logo.svg', 5, 'image/svg+xml'),
    ])->assertSessionHasErrors('file');

    expect(MediaAsset::query()->count())->toBe(0);
});

it('deletes a library-owned asset', function () {
    actingAs(mediaUser('admin'))->post('/media', [
        'file' => UploadedFile::fake()->image('photo.jpg'),
    ]);

    $media = Media::query()->latest('id')->firstOrFail();

    actingAs(mediaUser('admin'))->delete("/media/{$media->id}")->assertRedirect();

    expect(Media::query()->find($media->id))->toBeNull()
        ->and(MediaAsset::query()->count())->toBe(0);
});

it('refuses to delete content media from the library', function () {
    $news = News::factory()->create();
    $news->addMedia(UploadedFile::fake()->image('cover.jpg'))->toMediaCollection('cover');
    $media = $news->getFirstMedia('cover');

    actingAs(mediaUser('admin'))->delete("/media/{$media->id}")->assertRedirect();

    expect(Media::query()->find($media->id))->not->toBeNull();
});

it('clamps an excessive per_page to a safe bound', function () {
    actingAs(mediaUser('admin'))->get('/media?per_page=100000')
        ->assertInertia(fn ($page) => $page
            ->component('media/index')
            ->where('meta.per_page', 100),
        );
});

it('lists media with a usage label', function () {
    $news = News::factory()->create();
    $news->addMedia(UploadedFile::fake()->image('cover.jpg'))->toMediaCollection('cover');

    actingAs(mediaUser('admin'))->get('/media')
        ->assertInertia(fn ($page) => $page
            ->component('media/index')
            ->has('items', 1)
            ->where('items.0.usage', 'Новость')
            ->where('items.0.owned', false),
        );
});

it('lists only images as json for the in-editor picker', function () {
    actingAs(mediaUser('editor'))->post('/media', [
        'file' => UploadedFile::fake()->image('scene.jpg'),
        'title' => 'Сцена',
    ]);
    // A non-image asset must not appear in the picker.
    actingAs(mediaUser('editor'))->post('/media', [
        'file' => UploadedFile::fake()->create('plan.pdf', 20, 'application/pdf'),
    ]);

    actingAs(mediaUser('editor'))->getJson('/media/library')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [['id', 'url', 'name', 'file_name', 'ext', 'size', 'kind']],
            'meta' => ['current_page', 'last_page', 'total'],
        ])
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.kind', 'image');
});

it('uploads an image through the picker and returns it as json', function () {
    actingAs(mediaUser('editor'))->post('/media/library', [
        'file' => UploadedFile::fake()->image('insert.png'),
        'title' => 'Вставка',
    ], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('data.kind', 'image');

    expect(MediaAsset::query()->count())->toBe(1);
});

it('rejects a document upload through the image picker', function () {
    actingAs(mediaUser('editor'))->post('/media/library', [
        'file' => UploadedFile::fake()->create('plan.pdf', 20, 'application/pdf'),
    ], ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('file');

    expect(MediaAsset::query()->count())->toBe(0);
});

it('does not expose media attached to content in the reusable picker', function () {
    $editor = mediaUser('editor');
    actingAs($editor)->post('/media', [
        'file' => UploadedFile::fake()->image('library.jpg'),
    ]);

    $news = News::factory()->create();
    $news->addMedia(UploadedFile::fake()->image('private-draft.jpg'))->toMediaCollection('cover');

    actingAs($editor)->getJson('/media/library')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.usage', 'Библиотека');
});

it('requires media edit permission to change metadata', function () {
    $asset = MediaAsset::factory()->create();
    $media = $asset->addMedia(UploadedFile::fake()->image('p.jpg'))->toMediaCollection('asset');

    actingAs(mediaUser('alert_operator'))->put("/media/{$media->id}", [
        'name' => 'Недопустимое изменение',
    ])->assertForbidden();

    expect($asset->fresh()->title)->not->toBe('Недопустимое изменение');
});

it('forbids a user without media permission from browsing the picker', function () {
    actingAs(User::factory()->create())->getJson('/media/library')->assertForbidden();
});

it('updates alt, caption and name of a library asset', function () {
    $editor = mediaUser('editor');
    actingAs($editor)->post('/media', ['file' => UploadedFile::fake()->image('p.jpg')]);
    $media = Media::query()->latest('id')->firstOrFail();

    actingAs($editor)->put("/media/{$media->id}", [
        'name' => 'Учения',
        'alt' => 'Спасатели на учениях',
        'caption' => 'Фото: пресс-служба КЧС',
    ])->assertRedirect();

    $asset = MediaAsset::query()->firstOrFail();
    expect($asset->alt)->toBe('Спасатели на учениях')
        ->and($asset->caption)->toBe('Фото: пресс-служба КЧС')
        ->and($asset->title)->toBe('Учения')
        ->and($media->fresh()->name)->toBe('Учения');
});

it('refuses to edit metadata of content (non-library) media', function () {
    $news = News::factory()->create();
    $news->addMedia(UploadedFile::fake()->image('cover.jpg'))->toMediaCollection('cover');
    $media = $news->getFirstMedia('cover');

    actingAs(mediaUser('editor'))->put("/media/{$media->id}", ['alt' => 'x'])
        ->assertRedirect();

    expect(MediaAsset::query()->count())->toBe(0);
});

it('exposes alt and caption in the picker JSON', function () {
    $editor = mediaUser('editor');
    actingAs($editor)->post('/media', ['file' => UploadedFile::fake()->image('p.jpg')]);
    $media = Media::query()->latest('id')->firstOrFail();
    actingAs($editor)->put("/media/{$media->id}", ['alt' => 'Описание', 'caption' => 'Подпись']);

    actingAs($editor)->getJson('/media/library')
        ->assertOk()
        ->assertJsonPath('data.0.alt', 'Описание')
        ->assertJsonPath('data.0.caption', 'Подпись');
});
