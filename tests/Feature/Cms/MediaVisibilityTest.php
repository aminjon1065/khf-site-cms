<?php

use App\Enums\ContentStatus;
use App\Models\News;
use App\Models\User;
use App\Services\WorkflowService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;

beforeEach(function () {
    seed(RolePermissionSeeder::class);
    Storage::fake('public');
    Storage::fake('content_private');
});

function translatedDraftNews(): News
{
    return News::factory()->create([
        'status' => ContentStatus::Draft,
        'title' => ['tg' => 'Хабар', 'ru' => 'Новость', 'en' => ''],
        'summary' => ['tg' => 'Хулоса', 'ru' => 'Кратко', 'en' => ''],
        'body' => ['tg' => 'Матни хабар', 'ru' => 'Текст новости', 'en' => ''],
    ]);
}

it('stores media attached to a draft on the private disk', function () {
    $news = translatedDraftNews();
    $media = $news->addMedia(UploadedFile::fake()->image('draft.jpg'))->toMediaCollection('cover');

    expect($media->disk)->toBe('content_private')
        ->and($media->conversions_disk)->toBe('content_private');
    Storage::disk('content_private')->assertExists($media->getPathRelativeToRoot());
    Storage::disk('public')->assertMissing($media->getPathRelativeToRoot());
});

it('does not serve a signed private-media URL to an anonymous visitor', function () {
    $news = translatedDraftNews();
    $media = $news->addMedia(UploadedFile::fake()->image('draft.jpg'))->toMediaCollection('cover');

    $this->get($media->getUrl())->assertNotFound();
});

it('serves a signed private preview to an authorized CMS user', function () {
    $editor = User::factory()->create();
    $editor->assignRole('editor');
    $news = translatedDraftNews();
    $media = $news->addMedia(UploadedFile::fake()->image('draft.jpg'))->toMediaCollection('cover');

    actingAs($editor)->get($media->getUrl())
        ->assertOk()
        ->assertHeader('Cache-Control', 'max-age=0, no-store, private');
});

it('moves media to public on publish and back to private on archive', function () {
    $news = translatedDraftNews();
    $media = $news->addMedia(UploadedFile::fake()->image('draft.jpg'))->toMediaCollection('cover');
    $privatePath = $media->getPathRelativeToRoot();

    app(WorkflowService::class)->transition($news, ContentStatus::Published, force: true);

    $publishedMedia = $news->fresh()->getFirstMedia('cover');
    expect($publishedMedia)->not->toBeNull()
        ->and($publishedMedia->disk)->toBe('public');
    Storage::disk('content_private')->assertMissing($privatePath);
    Storage::disk('public')->assertExists($publishedMedia->getPathRelativeToRoot());

    app(WorkflowService::class)->transition($news->fresh(), ContentStatus::Archived, comment: 'Снято с публикации');

    $archivedMedia = $news->fresh()->getFirstMedia('cover');
    expect($archivedMedia)->not->toBeNull()
        ->and($archivedMedia->disk)->toBe('content_private');
    Storage::disk('public')->assertMissing($publishedMedia->getPathRelativeToRoot());
    Storage::disk('content_private')->assertExists($archivedMedia->getPathRelativeToRoot());
});
