<?php

use App\Enums\ContentStatus;
use App\Models\News;
use App\Models\Page;
use App\Models\Project;

// A-2 of the site contract: the date a published material's text last really
// changed — NewsArticle.dateModified and the page's modified time. Anything
// that isn't an edit of the text must leave it alone, or the site tells
// search engines the article is fresher than it is.

function publishedNews(): News
{
    return News::factory()->published()->create([
        'published_at' => now()->subDays(3),
        'title' => ['ru' => 'Учения', 'tg' => 'Машқҳо', 'en' => ''],
    ]);
}

it('dates a real edit of a published news item and serves it', function () {
    $news = publishedNews();
    $this->travel(1)->hours();

    $news->setTranslation('title', 'ru', 'Учения завершены');
    $news->save();

    expect($news->fresh()->content_updated_at?->toIso8601String())->toBe(now()->toIso8601String());

    $this->getJson("/api/v1/news/{$news->slug}?locale=ru")
        ->assertOk()
        ->assertJsonPath('data.updated_at', now()->toIso8601String());
});

it('does not count what is not an edit of the text', function () {
    $news = publishedNews();

    // The same translations with another key order, the view counter, a
    // status change — none of it is an edit.
    $news->forceFill(['title' => ['tg' => 'Машқҳо', 'ru' => 'Учения', 'en' => '']])->save();
    $news->increment('views_count');
    $news->forceFill(['is_pinned' => true])->save();
    $news->forceFill(['status' => ContentStatus::Archived])->save();

    expect($news->fresh()->content_updated_at)->toBeNull();

    $this->travel(1)->minutes();
    $news->forceFill(['status' => ContentStatus::Published])->save();
    $this->getJson("/api/v1/news/{$news->slug}?locale=ru")->assertJsonPath('data.updated_at', null);
});

it('does not date edits of a draft or the save that publishes it', function () {
    $news = News::factory()->create(['status' => ContentStatus::Draft, 'published_at' => null]);

    $news->setTranslation('body', 'ru', '<p>Черновик.</p>');
    $news->save();
    $news->forceFill([
        'status' => ContentStatus::Published,
        'published_at' => now(),
        'body' => ['ru' => '<p>Опубликованный текст.</p>'],
    ])->save();

    expect($news->fresh()->content_updated_at)->toBeNull();
});

it('gives a page the date of its content, never a mere touch of the row', function () {
    $page = Page::factory()->published()->create(['published_at' => now()->subWeek()->startOfSecond()]);

    $page->touch();
    $this->getJson("/api/v1/pages/{$page->slug}?locale=ru")
        ->assertJsonPath('data.updated_at', $page->published_at->toIso8601String());

    $this->travel(2)->hours();
    $page->setTranslation('body', 'ru', '<p>Новый текст страницы.</p>');
    $page->save();

    $this->getJson("/api/v1/pages/{$page->slug}?locale=ru")
        ->assertJsonPath('data.updated_at', now()->toIso8601String());
});

it('dates an edit of a project field that is not translatable', function () {
    $project = Project::factory()->published()->create(['published_at' => now()->subMonth()]);

    $project->forceFill(['budget' => '2 млн долларов'])->save();

    expect($project->fresh()->content_updated_at)->not->toBeNull();
});
