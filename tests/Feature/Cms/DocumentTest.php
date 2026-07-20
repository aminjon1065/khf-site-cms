<?php

use App\Enums\ContentStatus;
use App\Models\Document;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;

beforeEach(function () {
    seed(RolePermissionSeeder::class);
});

function docUser(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

it('lets an editor open the document create form', function () {
    actingAs(docUser('editor'))->get('/documents/create')->assertOk();
});

it('forbids a viewer from opening the create form', function () {
    actingAs(docUser('viewer'))->get('/documents/create')->assertForbidden();
});

it('creates a draft document with its metadata', function () {
    actingAs(docUser('editor'))->post('/documents', [
        'name' => ['ru' => 'Приказ № 1', 'tg' => '', 'en' => ''],
        'doc_type' => 'order',
        'number' => '№ 1',
        'doc_date' => '2026-07-01',
        'section' => 'Приказы',
        'action' => 'draft',
    ])->assertRedirect('/documents');

    $document = Document::query()->first();

    expect($document->status)->toBe(ContentStatus::Draft)
        ->and($document->doc_type->value)->toBe('order')
        ->and($document->number)->toBe('№ 1')
        ->and($document->section)->toBe('Приказы');
});

it('requires a russian name and a document type', function () {
    actingAs(docUser('editor'))->post('/documents', [
        'name' => ['ru' => ''],
        'action' => 'draft',
    ])->assertSessionHasErrors(['name.ru', 'doc_type']);
});

it('uploads a per-language file to the right collection', function () {
    Storage::fake('public');

    actingAs(docUser('editor'))->post('/documents', [
        'name' => ['ru' => 'Документ с файлом', 'tg' => '', 'en' => ''],
        'doc_type' => 'law',
        'file_ru' => UploadedFile::fake()->create('law.pdf', 120, 'application/pdf'),
        'action' => 'draft',
    ])->assertRedirect('/documents');

    $document = Document::query()->first();

    expect($document->hasMedia('file_ru'))->toBeTrue()
        ->and($document->hasMedia('file_tg'))->toBeFalse();
});

it('rejects a disallowed file type', function () {
    Storage::fake('public');

    actingAs(docUser('editor'))->post('/documents', [
        'name' => ['ru' => 'Опасный', 'tg' => '', 'en' => ''],
        'doc_type' => 'law',
        'file_ru' => UploadedFile::fake()->create('malware.exe', 10, 'application/octet-stream'),
        'action' => 'draft',
    ])->assertSessionHasErrors('file_ru');
});

it('publishes a document, stamps published_at, and it becomes public', function () {
    $document = Document::factory()->create(['name' => ['ru' => 'Публикуемый', 'tg' => '', 'en' => '']]);

    actingAs(docUser('chief_editor'))->post("/documents/{$document->id}/publish")->assertRedirect();

    $document->refresh();

    expect($document->status)->toBe(ContentStatus::Published)
        ->and($document->published_at)->not->toBeNull();

    $this->getJson('/api/v1/documents')
        ->assertOk()
        ->assertJsonPath('data.0.title', 'Публикуемый');
});

it('forbids a viewer from deleting a document', function () {
    $document = Document::factory()->create();

    actingAs(docUser('viewer'))->delete("/documents/{$document->id}")->assertForbidden();
});

it('soft-deletes a document for an authorized user', function () {
    $document = Document::factory()->create();

    actingAs(docUser('chief_editor'))->delete("/documents/{$document->id}")->assertRedirect();

    expect(Document::query()->find($document->id))->toBeNull()
        ->and(Document::withTrashed()->find($document->id))->not->toBeNull();
});
