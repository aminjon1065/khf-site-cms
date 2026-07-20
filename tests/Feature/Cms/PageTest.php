<?php

use App\Enums\ContentStatus;
use App\Models\Page;
use App\Models\User;
use Database\Seeders\PageSeeder;
use Database\Seeders\RolePermissionSeeder;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;

beforeEach(function () {
    seed(RolePermissionSeeder::class);
});

function pageUser(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

it('lets an admin open the pages list', function () {
    actingAs(pageUser('admin'))->get('/pages')->assertOk();
});

it('forbids a user without any role from the pages list', function () {
    actingAs(User::factory()->create())->get('/pages')->assertForbidden();
});

it('forbids a viewer from creating a page', function () {
    actingAs(pageUser('viewer'))->post('/pages', [
        'title' => ['ru' => 'Тест'],
    ])->assertForbidden();
});

it('creates a draft page and auto-generates a slug', function () {
    actingAs(pageUser('editor'))->post('/pages', [
        'title' => ['ru' => 'О Комитете', 'tg' => 'Дар бораи Кумита'],
        'body' => ['ru' => 'Текст страницы.'],
        'action' => 'draft',
    ])->assertRedirect('/pages');

    $page = Page::query()->latest('id')->first();

    expect($page)->not->toBeNull()
        ->and($page->status)->toBe(ContentStatus::Draft)
        ->and($page->slug)->toMatch('/^[a-z0-9-]+$/')
        ->and($page->published_at)->toBeNull();
});

it('respects an explicit slug and rejects a duplicate', function () {
    Page::factory()->create(['slug' => 'about']);

    actingAs(pageUser('admin'))->post('/pages', [
        'title' => ['ru' => 'Другая'],
        'slug' => 'about',
    ])->assertSessionHasErrors('slug');
});

it('publishes immediately for an author with publish rights', function () {
    actingAs(pageUser('admin'))->post('/pages', [
        'title' => ['ru' => 'Публичная страница'],
        'body' => ['ru' => 'Содержание.'],
        'action' => 'submit',
        'publish_mode' => 'now',
    ])->assertRedirect('/pages');

    $page = Page::query()->latest('id')->first();

    expect($page->status)->toBe(ContentStatus::Published)
        ->and($page->published_at)->not->toBeNull();
});

it('routes a submit to review when the author cannot publish', function () {
    // regional_editor has pages create/edit but not publish.
    actingAs(pageUser('regional_editor'))->post('/pages', [
        'title' => ['ru' => 'На согласование'],
        'action' => 'submit',
        'publish_mode' => 'now',
    ])->assertRedirect('/pages');

    expect(Page::query()->latest('id')->first()->status)->toBe(ContentStatus::Review);
});

it('keeps the slug stable on update when the slug field is left blank', function () {
    $page = Page::factory()->create(['slug' => 'stable-slug', 'title' => ['ru' => 'Старое']]);

    actingAs(pageUser('admin'))->put("/pages/{$page->id}", [
        'title' => ['ru' => 'Новое название'],
        'slug' => '',
    ])->assertRedirect('/pages');

    expect($page->refresh()->slug)->toBe('stable-slug')
        ->and($page->getTranslation('title', 'ru'))->toBe('Новое название');
});

it('unpublishes a page into the archive', function () {
    $page = Page::factory()->published()->create();

    actingAs(pageUser('admin'))->post("/pages/{$page->id}/unpublish", [
        'comment' => 'Устаревшая информация.',
    ])->assertRedirect();

    expect($page->refresh()->status)->toBe(ContentStatus::Archived);
});

it('soft-deletes a page', function () {
    $page = Page::factory()->create();

    actingAs(pageUser('admin'))->delete("/pages/{$page->id}")->assertRedirect();

    expect(Page::query()->find($page->id))->toBeNull()
        ->and(Page::withTrashed()->find($page->id))->not->toBeNull();
});

it('rejects a page without a Russian title', function () {
    actingAs(pageUser('admin'))->post('/pages', [
        'title' => ['tg' => 'Танҳо тоҷикӣ'],
    ])->assertSessionHasErrors('title.ru');
});

it('re-seeds pages idempotently even after one was soft-deleted', function () {
    seed(PageSeeder::class);
    Page::query()->where('slug', 'about')->firstOrFail()->delete();

    // Must not throw a duplicate-slug error; the trashed row is restored.
    seed(PageSeeder::class);

    $about = Page::query()->where('slug', 'about')->first();

    expect($about)->not->toBeNull()
        ->and($about->status)->toBe(ContentStatus::Published);
});
