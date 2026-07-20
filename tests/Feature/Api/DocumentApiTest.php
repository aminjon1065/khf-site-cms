<?php

use App\Enums\ContentStatus;
use App\Enums\DocType;
use App\Models\Document;
use Illuminate\Support\Facades\Storage;

it('returns only publicly visible documents, newest first', function () {
    Document::factory()->published()->create([
        'doc_date' => '2026-01-01', 'name' => ['ru' => 'Старый', 'tg' => '', 'en' => ''],
    ]);
    Document::factory()->published()->create([
        'doc_date' => '2026-05-01', 'name' => ['ru' => 'Новый', 'tg' => '', 'en' => ''],
    ]);
    Document::factory()->create(['status' => ContentStatus::Draft]);
    Document::factory()->create(['status' => ContentStatus::Review]);

    $response = $this->getJson('/api/v1/documents');

    $response->assertOk()->assertJsonCount(2, 'data');
    expect($response->json('data.0.title'))->toBe('Новый');
});

it('filters by document type', function () {
    Document::factory()->published()->create(['doc_type' => DocType::Law]);
    Document::factory()->published()->create(['doc_type' => DocType::Report]);

    $this->getJson('/api/v1/documents?type=law')->assertOk()->assertJsonCount(1, 'data');
});

it('exposes a localized name (ru fallback) and formatted date', function () {
    Document::factory()->published()->create([
        'name' => ['ru' => 'Русское имя', 'tg' => 'Номи тоҷикӣ', 'en' => ''],
        'doc_date' => '2026-03-02',
    ]);

    expect($this->getJson('/api/v1/documents?locale=tg')->json('data.0.title'))->toBe('Номи тоҷикӣ')
        ->and($this->getJson('/api/v1/documents?locale=en')->json('data.0.title'))->toBe('Русское имя')
        ->and($this->getJson('/api/v1/documents')->json('data.0.date'))->toBe('02.03.2026');
});

it('exposes attached files and picks the locale-preferred primary', function () {
    Storage::fake('public');
    $document = Document::factory()->published()->create(['name' => ['ru' => 'С файлами', 'tg' => '', 'en' => '']]);
    $document->addMediaFromString('%PDF-1.4 ru')->usingFileName('ru.pdf')->toMediaCollection('file_ru');
    $document->addMediaFromString('%PDF-1.4 tg')->usingFileName('tg.pdf')->toMediaCollection('file_tg');

    $ru = $this->getJson('/api/v1/documents?locale=ru')->json('data.0');
    expect($ru['files'])->toHaveCount(2)
        ->and($ru['lang'])->toBe('ТҶ / РУ')
        ->and($ru['href'])->toContain('ru.pdf');

    $tg = $this->getJson('/api/v1/documents?locale=tg')->json('data.0');
    expect($tg['href'])->toContain('tg.pdf');
});

it('returns null file fields when a document has no attached files', function () {
    Document::factory()->published()->create();

    $item = $this->getJson('/api/v1/documents')->json('data.0');

    expect($item['href'])->toBeNull()
        ->and($item['size'])->toBeNull()
        ->and($item['files'])->toBe([]);
});
