<?php

use App\Models\News;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;

beforeEach(function () {
    seed(RolePermissionSeeder::class);
});

// Загрузка и удаление вложений через форму редактора. Удаление приходит
// списком идентификаторов, чтобы правка одного файла не требовала
// перезагружать остальные.

function attachmentsEditor(): User
{
    return User::factory()->create()->assignRole('chief_editor');
}

function newsFormPayload(News $news, array $overrides = []): array
{
    return array_merge([
        '_method' => 'put',
        '_editorial_version' => $news->updated_at?->toIso8601String(),
        'title' => $news->getTranslations('title'),
        'summary' => $news->getTranslations('summary'),
        'body' => $news->getTranslations('body'),
        'slug' => $news->slug,
    ], $overrides);
}

it('uploads attachments through the editor form', function () {
    $news = News::factory()->create();

    actingAs(attachmentsEditor())
        ->post("/news/{$news->id}", newsFormPayload($news, [
            'attachments' => [
                UploadedFile::fake()->create('pamyatka.pdf', 40, 'application/pdf'),
            ],
        ]))
        ->assertRedirect();

    expect($news->fresh()->getMedia('attachments'))->toHaveCount(1)
        // Имя берётся из имени файла без расширения: это то, что увидит читатель.
        ->and($news->fresh()->getFirstMedia('attachments')->name)->toBe('pamyatka');
});

it('rejects a file type that is not a document', function () {
    // Исполняемые файлы на портале ведомства недопустимы.
    $news = News::factory()->create();

    actingAs(attachmentsEditor())
        ->post("/news/{$news->id}", newsFormPayload($news, [
            'attachments' => [UploadedFile::fake()->create('script.exe', 10)],
        ]))
        ->assertSessionHasErrors('attachments.0');

    expect($news->fresh()->getMedia('attachments'))->toHaveCount(0);
});

it('removes only the attachments listed for removal', function () {
    $news = News::factory()->create();
    $keep = $news->addMedia(UploadedFile::fake()->create('keep.pdf', 10, 'application/pdf'))
        ->toMediaCollection('attachments');
    $drop = $news->addMedia(UploadedFile::fake()->create('drop.pdf', 10, 'application/pdf'))
        ->toMediaCollection('attachments');

    actingAs(attachmentsEditor())
        ->post("/news/{$news->id}", newsFormPayload($news->fresh(), [
            'attachments_remove' => [$drop->getKey()],
        ]))
        ->assertRedirect();

    $remaining = $news->fresh()->getMedia('attachments');

    expect($remaining)->toHaveCount(1)
        ->and($remaining->first()->getKey())->toBe($keep->getKey());
});
