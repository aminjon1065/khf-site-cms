<?php

use App\Models\Alert;
use App\Models\Document;
use App\Models\Instruction;
use App\Models\News;
use App\Models\Project;
use App\Support\PublicLocale;
use App\Support\PublicSite;

/*
 * A material appears on a language version of the site only when that
 * language has its title and its text (owner decision, 2026-09-23). Alerts and
 * documents need only the title: a threat must never be hidden for want of a
 * translated text, and a document's content is its file.
 */

it('keeps a language version without its text off the site', function () {
    $news = News::factory()->published()->create([
        'title' => ['ru' => 'Учения', 'tg' => 'Машқҳо', 'en' => 'Drills'],
        'body' => ['ru' => '<p>Текст</p>', 'tg' => '<p>Матн</p>', 'en' => ''],
    ]);

    expect($this->getJson('/api/v1/news?locale=en')->assertOk()->json('data'))->toBe([])
        ->and($this->getJson('/api/v1/slugs/news?locale=en')->assertOk()->json('data'))->toBe([]);
    $this->getJson("/api/v1/news/{$news->slug}?locale=en")->assertNotFound();

    $this->getJson("/api/v1/news/{$news->slug}?locale=ru")
        ->assertOk()
        ->assertJsonPath('data.available_locales', ['tg', 'ru']);

    $news->update(['body' => ['ru' => '<p>Текст</p>', 'tg' => '<p>Матн</p>', 'en' => '<p>Text</p>']]);

    $this->getJson("/api/v1/news/{$news->slug}?locale=en")
        ->assertOk()
        ->assertJsonPath('data.available_locales', ['tg', 'ru', 'en']);
});

it('counts the steps of an instruction as its text', function () {
    $instruction = Instruction::factory()->published()->create([
        'name' => ['ru' => 'Землетрясение', 'tg' => 'Заминҷунбӣ', 'en' => 'Earthquake'],
        'body' => ['ru' => '', 'tg' => '', 'en' => ''],
        'sections' => [
            'before' => ['ru' => ['Закрепите мебель'], 'tg' => [], 'en' => []],
            'during' => ['ru' => [], 'tg' => ['Паноҳ гиред'], 'en' => []],
        ],
    ]);

    $this->getJson("/api/v1/instructions/{$instruction->slug}?locale=ru")->assertOk();
    $this->getJson("/api/v1/instructions/{$instruction->slug}?locale=tg")
        ->assertOk()
        ->assertJsonPath('data.available_locales', ['tg', 'ru']);
    $this->getJson("/api/v1/instructions/{$instruction->slug}?locale=en")->assertNotFound();
});

it('counts the summary of a project as its text', function () {
    $project = Project::factory()->published()->create([
        'title' => ['ru' => 'Проект', 'tg' => 'Лоиҳа', 'en' => 'Project'],
        'summary' => ['ru' => 'Кратко', 'tg' => '', 'en' => 'In short'],
        'body' => ['ru' => '', 'tg' => '', 'en' => ''],
    ]);

    $this->getJson("/api/v1/projects/{$project->slug}?locale=en")
        ->assertOk()
        ->assertJsonPath('data.available_locales', ['ru', 'en']);
    $this->getJson("/api/v1/projects/{$project->slug}?locale=tg")->assertNotFound();
});

it('shows alerts and documents by their title alone', function () {
    $alert = Alert::factory()->published()->create([
        'title' => ['ru' => 'Сель', 'tg' => 'Сел', 'en' => 'Mudflow'],
        'summary' => ['ru' => 'Кратко', 'tg' => '', 'en' => ''],
        'body' => ['ru' => '<p>Текст</p>', 'tg' => '', 'en' => ''],
    ]);
    Document::factory()->published()->create([
        'name' => ['ru' => 'Закон', 'tg' => '', 'en' => 'Law'],
    ]);

    $this->getJson("/api/v1/alerts/{$alert->slug}?locale=en")
        ->assertOk()
        ->assertJsonPath('data.available_locales', ['tg', 'ru', 'en']);
    $this->getJson('/api/v1/documents?locale=en')
        ->assertOk()
        ->assertJsonPath('data.0.title', 'Law');
});

it('links the CMS to a language version that appears on the site', function () {
    config(['services.frontend.url' => 'https://khf.tj']);
    $news = News::factory()->published()->create([
        'slug' => 'drills',
        'title' => ['ru' => 'Учения', 'tg' => 'Машқҳо', 'en' => ''],
        'body' => ['ru' => '', 'tg' => '<p>Матн</p>', 'en' => ''],
    ]);

    expect(PublicLocale::firstPublishedLocale($news))->toBe('tg')
        ->and(PublicSite::urlFor($news))->toBe('https://khf.tj/tj/news/drills')
        ->and(PublicSite::urlFor($news, 'ru'))->toBeNull();
});
