<?php

use App\Enums\ContentStatus;
use App\Models\News;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;

beforeEach(function () {
    seed(RolePermissionSeeder::class);
});

function newsUser(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

it('lets an editor open the news create form', function () {
    actingAs(newsUser('editor'))->get('/news/create')->assertOk();
});

it('forbids a viewer from opening the create form', function () {
    actingAs(newsUser('viewer'))->get('/news/create')->assertForbidden();
});

it('creates a draft with an auto-generated slug', function () {
    actingAs(newsUser('editor'))->post('/news', [
        'title' => ['ru' => 'Учения в Хатлонской области', 'tg' => '', 'en' => ''],
        'action' => 'draft',
    ])->assertRedirect('/news');

    $news = News::query()->first();

    expect($news)->not->toBeNull()
        ->and($news->status)->toBe(ContentStatus::Draft)
        ->and($news->slug)->not->toBeEmpty();
});

it('generates a unique slug when titles collide', function () {
    $editor = newsUser('editor');
    $payload = fn () => [
        'title' => ['ru' => 'Одинаковый заголовок', 'tg' => '', 'en' => ''],
        'action' => 'draft',
    ];

    actingAs($editor)->post('/news', $payload());
    actingAs($editor)->post('/news', $payload());

    $slugs = News::query()->pluck('slug');

    expect($slugs)->toHaveCount(2)
        ->and($slugs->unique())->toHaveCount(2);
});

it('requires a russian title', function () {
    actingAs(newsUser('editor'))->post('/news', [
        'title' => ['ru' => '', 'tg' => 'Ягон чиз'],
        'action' => 'draft',
    ])->assertSessionHasErrors('title.ru');
});

it('sends news to review when an editor submits for approval', function () {
    actingAs(newsUser('editor'))->post('/news', [
        'title' => ['ru' => 'Материал на согласование', 'tg' => '', 'en' => ''],
        'action' => 'submit',
        'publish_mode' => 'review',
    ])->assertRedirect('/news');

    expect(News::query()->first()->status)->toBe(ContentStatus::Review);
});

it('lets a chief editor publish immediately', function () {
    actingAs(newsUser('chief_editor'))->post('/news', [
        'title' => ['ru' => 'Срочная публикация', 'tg' => '', 'en' => ''],
        'action' => 'submit',
        'publish_mode' => 'now',
    ])->assertRedirect('/news');

    $news = News::query()->first();

    expect($news->status)->toBe(ContentStatus::Published)
        ->and($news->published_at)->not->toBeNull();
});

it('downgrades a publish attempt to review when the user cannot publish', function () {
    // regional_editor may create/edit news but has no news.publish permission.
    actingAs(newsUser('regional_editor'))->post('/news', [
        'title' => ['ru' => 'Попытка публикации', 'tg' => '', 'en' => ''],
        'action' => 'submit',
        'publish_mode' => 'now',
    ])->assertRedirect('/news');

    expect(News::query()->first()->status)->toBe(ContentStatus::Review);
});

it('publishes via the publish endpoint and the item becomes public', function () {
    $news = News::factory()->create([
        'slug' => 'api-visible',
        'title' => ['ru' => 'Виден в публичном API', 'tg' => '', 'en' => ''],
    ]);

    actingAs(newsUser('chief_editor'))->post("/news/{$news->id}/publish")->assertRedirect();

    expect($news->fresh()->status)->toBe(ContentStatus::Published);

    $this->getJson('/api/v1/news/api-visible')
        ->assertOk()
        ->assertJsonPath('data.title', 'Виден в публичном API');
});

it('unpublishes a published item to the archive', function () {
    $news = News::factory()->published()->create();

    actingAs(newsUser('chief_editor'))
        ->post("/news/{$news->id}/unpublish", ['comment' => 'Материал устарел'])
        ->assertRedirect();

    expect($news->fresh()->status)->toBe(ContentStatus::Archived);
});

it('forbids a viewer from deleting news', function () {
    $news = News::factory()->create();

    actingAs(newsUser('viewer'))->delete("/news/{$news->id}")->assertForbidden();
});

it('soft-deletes news for an authorized user', function () {
    $news = News::factory()->create();

    actingAs(newsUser('chief_editor'))->delete("/news/{$news->id}")->assertRedirect();

    expect(News::query()->find($news->id))->toBeNull()
        ->and(News::withTrashed()->find($news->id))->not->toBeNull();
});
