<?php

use App\Enums\ContentStatus;
use App\Models\Announcement;
use App\Models\Document;
use App\Models\Leader;
use App\Models\MenuItem;
use App\Models\Setting;

// C-3: aggregate translation-gap report. Every content factory already
// defaults to `en => ''` (a deliberate existing fixture, not something these
// tests invented), so most gaps here come for free from plain factory
// defaults rather than hand-built incomplete data.
//
// Note: expectsOutputToContain() matches are consumed in order against a
// queue of captured writeln() calls — two expectations that both target
// substrings of the *same* table row will only let the first one match, so
// each test asserts on one precise, unambiguous substring per row rather
// than the row's label and its numbers separately.

it('counts only published records, not drafts, for workflow models', function () {
    Announcement::factory()->published()->create();
    Announcement::factory()->create(['status' => ContentStatus::Draft]);

    $this->artisan('content:translation-report')
        ->assertSuccessful()
        ->expectsOutputToContain('1 из 1');
});

it('flags a document missing a language file even when its name is fully translated', function () {
    $document = Document::factory()->published()->create();
    $document->setTranslations('name', ['ru' => 'Документ', 'tg' => 'Ҳуҷҷат', 'en' => 'Document']);
    $document->save();
    // No file uploaded for any language — fileLanguages() is all-false, so
    // this must still show as a gap despite the fully-translated name.

    $this->artisan('content:translation-report')
        ->assertSuccessful()
        ->expectsOutputToContain('1 из 1');
});

it('reports reference models without a workflow status and flags an incomplete menu as launch-critical', function () {
    MenuItem::create([
        'label' => ['ru' => 'Тест', 'tg' => 'Тест', 'en' => ''],
        'location' => 'main',
        'sort' => 1,
        'enabled' => true,
    ]);
    MenuItem::create([
        'label' => ['ru' => 'Скрытый', 'tg' => '', 'en' => ''],
        'location' => 'main',
        'sort' => 2,
        'enabled' => false, // disabled — must not count
    ]);

    $this->artisan('content:translation-report')
        ->assertSuccessful()
        ->expectsOutputToContain('1 из 1')
        ->expectsOutputToContain('Обязательное к запуску не переведено полностью');
});

it('reports a leader missing a locale via the shared TracksTranslationCompleteness trait', function () {
    Leader::factory()->create(['role' => ['ru' => 'Заместитель', 'tg' => '', 'en' => '']]);

    $this->artisan('content:translation-report')
        ->assertSuccessful()
        ->expectsOutputToContain('1 из 1');
});

it('groups Setting rows by locale-suffixed base name and flags org as launch-critical', function () {
    Setting::create(['group' => 'org', 'key' => 'motto_ru', 'value' => 'Девиз']);
    Setting::create(['group' => 'org', 'key' => 'motto_tg', 'value' => 'Шиор']);
    // no motto_en, and a non-suffixed key that must be ignored entirely.
    Setting::create(['group' => 'org', 'key' => 'trust_phone', 'value' => '+992...']);

    $this->artisan('content:translation-report')
        ->assertSuccessful()
        ->expectsOutputToContain('org.motto')
        ->expectsOutputToContain('Обязательное к запуску не переведено полностью');
});

it('reports everything satisfied when the database has no translatable content', function () {
    $this->artisan('content:translation-report')
        ->assertSuccessful()
        ->expectsOutputToContain('переведено полностью');
});
