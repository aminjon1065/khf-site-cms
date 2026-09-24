<?php

use App\Models\News;
use App\Models\User;
use App\Support\UploadLimits;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;

/*
 * One set of formats and sizes for every upload (UploadLimits): photos
 * JPG, PNG or WebP up to 10 MB, documents PDF, DOC(X), XLS(X) or PPT(X) up
 * to 20 MB. Messages name the limit in megabytes.
 */

beforeEach(function () {
    seed(RolePermissionSeeder::class);
    Storage::fake('public');
    Storage::fake('private');
    Queue::fake();
});

function uploadRulesEditor(): User
{
    $user = User::factory()->withTwoFactor()->create();
    $user->assignRole('editor');

    return $user;
}

/**
 * @param  array<string, mixed>  $files
 */
function saveNewsDraftWith(array $files): TestResponse
{
    return actingAs(uploadRulesEditor())->post('/news', [
        'title' => ['ru' => 'Новость с файлами', 'tg' => '', 'en' => ''],
        'action' => 'draft',
        ...$files,
    ]);
}

it('takes a phone photo of 6 MB as a cover', function () {
    saveNewsDraftWith(['cover' => UploadedFile::fake()->image('phone.jpg')->size(6 * 1024)])
        ->assertSessionHasNoErrors();

    expect(News::query()->firstOrFail()->hasMedia('cover'))->toBeTrue();
});

it('names the limit when a photo is too large', function () {
    saveNewsDraftWith(['cover' => UploadedFile::fake()->image('camera.jpg')->size(11 * 1024)])
        ->assertSessionHasErrors(['cover' => 'Обложка больше 10 МБ — уменьшите изображение.']);
});

it('takes a presentation as an attachment and turns a program away', function () {
    saveNewsDraftWith([
        'attachments' => [UploadedFile::fake()->create(
            'briefing.pptx',
            300,
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        )],
    ])->assertSessionHasNoErrors();

    saveNewsDraftWith([
        'attachments' => [UploadedFile::fake()->create('setup.exe', 300, 'application/x-msdownload')],
    ])->assertSessionHasErrors([
        'attachments.0' => 'Вложение: подходят файлы PDF, DOC(X), XLS(X) или PPT(X).',
    ]);
});

it('keeps separate limits for images and documents in the media library', function () {
    $editor = uploadRulesEditor();

    actingAs($editor)
        ->post('/media', ['file' => UploadedFile::fake()->create('report.pdf', 18 * 1024, 'application/pdf')])
        ->assertSessionHasNoErrors();

    actingAs($editor)
        ->post('/media', ['file' => UploadedFile::fake()->image('poster.png')->size(11 * 1024)])
        ->assertSessionHasErrors(['file' => 'Изображение больше 10 МБ — уменьшите его.']);
});

it('takes only images from the text editor', function () {
    actingAs(uploadRulesEditor())
        ->postJson('/media/library', ['file' => UploadedFile::fake()->create('report.pdf', 100, 'application/pdf')])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'file' => 'В текст можно вставить изображение JPG, PNG, WebP или GIF.',
        ]);
});

it('describes formats the way people read them', function () {
    expect(UploadLimits::describe(UploadLimits::IMAGE_FORMATS))->toBe('JPG, PNG или WebP')
        ->and(UploadLimits::describe(UploadLimits::LIBRARY_IMAGE_FORMATS))->toBe('JPG, PNG, WebP или GIF')
        ->and(UploadLimits::describe(UploadLimits::FILE_FORMATS))->toBe('PDF, DOC(X), XLS(X) или PPT(X)')
        ->and(UploadLimits::describe(['pdf']))->toBe('PDF');
});
