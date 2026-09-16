<?php

use App\Models\MediaAsset;
use App\Models\News;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
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

it('moves a library-owned asset to the recoverable trash', function () {
    actingAs(mediaUser('admin'))->post('/media', [
        'file' => UploadedFile::fake()->image('photo.jpg'),
    ]);

    $media = Media::query()->latest('id')->firstOrFail();

    actingAs(mediaUser('admin'))->delete("/media/{$media->id}")->assertRedirect();

    expect(Media::query()->find($media->id))->not->toBeNull()
        ->and(MediaAsset::query()->count())->toBe(0)
        ->and(MediaAsset::onlyTrashed()->count())->toBe(1);
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

it('pages through the picker instead of cutting the library off at the first screen', function () {
    // Конверсии тут ни при чём: тест про выдачу эндпоинта, а их обработка
    // требует внешнего кодировщика.
    Queue::fake();
    $editor = mediaUser('editor');

    foreach (range(1, 27) as $index) {
        MediaAsset::factory()
            ->create(['title' => "Файл {$index}"])
            ->addMedia(UploadedFile::fake()->image("asset-{$index}.jpg"))
            ->toMediaCollection('asset');
    }

    $first = actingAs($editor)->getJson('/media/library')
        ->assertOk()
        ->assertJsonPath('meta.total', 27)
        ->assertJsonPath('meta.current_page', 1)
        ->assertJsonPath('meta.per_page', 24)
        ->assertJsonPath('meta.last_page', 2)
        ->assertJsonCount(24, 'data');

    $second = actingAs($editor)->getJson('/media/library?page=2')
        ->assertOk()
        ->assertJsonPath('meta.current_page', 2)
        ->assertJsonCount(3, 'data');

    // Вторая страница должна догружать остаток, а не повторять первую.
    expect(array_intersect(
        array_column($first->json('data'), 'id'),
        array_column($second->json('data'), 'id'),
    ))->toBeEmpty();
});

it('finds a picker image by its description, not only by file name', function () {
    Queue::fake();
    $editor = mediaUser('editor');

    $described = MediaAsset::factory()->create([
        'title' => 'Учения в Хатлоне',
        'alt' => 'Спасатели на берегу реки',
        'caption' => 'Совместная тренировка подразделений',
    ]);
    $described->addMedia(UploadedFile::fake()->image('a1b2c3d4e5.jpg'))->toMediaCollection('asset');

    $other = MediaAsset::factory()->create(['title' => 'Другое', 'alt' => null]);
    $other->addMedia(UploadedFile::fake()->image('f6g7h8.jpg'))->toMediaCollection('asset');

    foreach (['Хатлоне', 'Спасатели', 'тренировка', 'a1b2c3'] as $term) {
        actingAs($editor)->getJson('/media/library?search='.urlencode($term))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $described->getFirstMedia('asset')?->id);
    }
});

it('orders picker images by the requested sort', function () {
    Queue::fake();
    $editor = mediaUser('editor');

    $first = MediaAsset::factory()->create(['title' => 'Первый']);
    $first->addMedia(UploadedFile::fake()->image('b-second-by-name.jpg'))->toMediaCollection('asset');

    $second = MediaAsset::factory()->create(['title' => 'Второй']);
    $second->addMedia(UploadedFile::fake()->image('a-first-by-name.jpg'))->toMediaCollection('asset');

    $oldestId = $first->getFirstMedia('asset')?->id;
    $newestId = $second->getFirstMedia('asset')?->id;

    actingAs($editor)->getJson('/media/library')
        ->assertOk()
        ->assertJsonPath('data.0.id', $newestId);

    actingAs($editor)->getJson('/media/library?sort=oldest')
        ->assertOk()
        ->assertJsonPath('data.0.id', $oldestId);

    actingAs($editor)->getJson('/media/library?sort=name')
        ->assertOk()
        ->assertJsonPath('data.0.file_name', 'a-first-by-name.jpg');

    // Незнакомое значение не должно оставлять сетку в произвольном порядке.
    actingAs($editor)->getJson('/media/library?sort=whatever')
        ->assertOk()
        ->assertJsonPath('data.0.id', $newestId);
});
