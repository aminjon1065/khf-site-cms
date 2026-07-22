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

    $response = $this->getJson('/api/v1/documents?locale=ru');

    $response->assertOk()->assertJsonCount(2, 'data');
    expect($response->json('data.0.title'))->toBe('Новый');
});

it('filters by document type', function () {
    Document::factory()->published()->create(['doc_type' => DocType::Law]);
    Document::factory()->published()->create(['doc_type' => DocType::Report]);

    $this->getJson('/api/v1/documents?locale=ru&type=law')->assertOk()->assertJsonCount(1, 'data');
});

it('exposes the requested localized name and formatted date', function () {
    Document::factory()->published()->create([
        'doc_type' => DocType::Law,
        'name' => ['ru' => 'Русское имя', 'tg' => 'Номи тоҷикӣ', 'en' => 'English name'],
        'doc_date' => '2026-03-02',
    ]);

    $tg = $this->getJson('/api/v1/documents?locale=tg')->assertOk()->json('data.0');
    $en = $this->getJson('/api/v1/documents?locale=en')->assertOk()->json('data.0');
    $ru = $this->getJson('/api/v1/documents?locale=ru')->assertOk()->json('data.0');

    expect($tg['title'])->toBe('Номи тоҷикӣ')
        ->and($tg['type'])->toBe('Қонун')
        ->and($tg['type_value'])->toBe('law')
        ->and($en['title'])->toBe('English name')
        ->and($en['type'])->toBe('Law')
        ->and($ru['date'])->toBe('02.03.2026')
        ->and($ru['date_iso'])->toBe('2026-03-02');
});

it('exposes attached files and picks the locale-preferred primary', function () {
    Storage::fake('public');
    $document = Document::factory()->published()->create(['name' => ['ru' => 'С файлами', 'tg' => 'Бо файлҳо', 'en' => '']]);
    $document->addMediaFromString('%PDF-1.4 ru')->usingFileName('ru.pdf')->toMediaCollection('file_ru');
    $document->addMediaFromString('%PDF-1.4 tg')->usingFileName('tg.pdf')->toMediaCollection('file_tg');

    $ru = $this->getJson('/api/v1/documents?locale=ru')->json('data.0');
    expect($ru['files'])->toHaveCount(2)
        ->and($ru['lang'])->toBe('ТҶ / РУ')
        ->and($ru['href'])->toContain('ru.pdf')
        ->and($ru['files'][0]['size_bytes'])->toBeInt();

    $tg = $this->getJson('/api/v1/documents?locale=tg')->json('data.0');
    expect($tg['href'])->toContain('tg.pdf');
});

it('does not expose a document through an untranslated locale', function () {
    Document::factory()->published()->create([
        'name' => ['ru' => 'Только по-русски', 'tg' => 'Танҳо тоҷикӣ', 'en' => ''],
    ]);

    $this->getJson('/api/v1/documents?locale=en')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('returns null file fields when a document has no attached files', function () {
    Document::factory()->published()->create();

    $item = $this->getJson('/api/v1/documents?locale=ru')->json('data.0');

    expect($item['href'])->toBeNull()
        ->and($item['size'])->toBeNull()
        ->and($item['files'])->toBe([]);
});
