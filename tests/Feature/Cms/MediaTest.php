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
