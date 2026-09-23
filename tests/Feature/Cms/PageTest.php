<?php

use App\Enums\ContentStatus;
use App\Models\Page;
use App\Models\Region;
use App\Models\User;
use Database\Seeders\PageSeeder;
use Database\Seeders\RegionSeeder;
use Database\Seeders\RolePermissionSeeder;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;

beforeEach(function () {
    seed([RolePermissionSeeder::class, RegionSeeder::class]);
});

function pageUser(string $role): User
{
    $user = User::factory()->create([
        'region_id' => $role === 'regional_editor' ? Region::query()->value('id') : null,
    ]);
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
        'title' => ['ru' => 'Публичная страница', 'tg' => 'Саҳифаи оммавӣ'],
        'body' => ['ru' => '<p>Содержание.</p>', 'tg' => '<p>Мундариҷа.</p>'],
        'seo_title' => ['ru' => 'Публичная страница', 'tg' => 'Саҳифаи оммавӣ'],
        'seo_description' => ['ru' => 'Описание страницы.', 'tg' => 'Тавсифи саҳифа.'],
        'action' => 'submit',
        'publish_mode' => 'now',
    ])->assertRedirect('/pages');

    $page = Page::query()->latest('id')->first();

    expect($page->status)->toBe(ContentStatus::Published)
        ->and($page->published_at)->not->toBeNull();
});

it('sanitizes rich page content before storage', function () {
    actingAs(pageUser('editor'))->post('/pages', [
        'title' => ['ru' => 'Безопасная страница'],
        'body' => ['ru' => '<p>Разрешённый текст</p><script>alert(1)</script>'],
        'action' => 'draft',
    ])->assertRedirect('/pages');

    $body = Page::query()->latest('id')->firstOrFail()->getTranslation('body', 'ru', false);

    expect($body)->toContain('<p>Разрешённый текст</p>')
        ->and($body)->not->toContain('<script');
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

it('unpublishes a page back into drafts', function () {
    $page = Page::factory()->published()->create();

    actingAs(pageUser('admin'))->post("/pages/{$page->id}/unpublish", [
        'comment' => 'Устаревшая информация.',
    ])->assertRedirect();

    expect($page->refresh()->status)->toBe(ContentStatus::Draft);
});

it('soft-deletes a page', function () {
    $page = Page::factory()->create();

    actingAs(pageUser('admin'))->delete("/pages/{$page->id}")->assertRedirect();

    expect(Page::query()->find($page->id))->toBeNull()
        ->and(Page::withTrashed()->find($page->id))->not->toBeNull();
});

it('rejects a page without a title in any language', function () {
    actingAs(pageUser('admin'))->post('/pages', [
        'title' => ['ru' => '', 'tg' => ''],
    ])->assertSessionHasErrors('title');
});

it('accepts a page titled only in Tajik', function () {
    actingAs(pageUser('admin'))->post('/pages', [
        'title' => ['tg' => 'Танҳо тоҷикӣ'],
    ])->assertSessionDoesntHaveErrors('title');
});

it('rejects a parent that would create a page tree cycle', function () {
    $parent = Page::factory()->create();
    $child = Page::factory()->create(['parent_id' => $parent->id]);

    actingAs(pageUser('admin'))->put("/pages/{$parent->id}", [
        'title' => ['ru' => 'Родитель'],
        'parent_id' => $child->id,
        'action' => 'draft',
    ])->assertSessionHasErrors('parent_id');

    expect($parent->fresh()->parent_id)->toBeNull();
});

it('nulls out a child\'s parent_id when its parent page is force-deleted', function () {
    // D-6: parent_id had no DB-level FK at all before this — a soft
    // delete() never reaches it (it's an UPDATE, not a DELETE), so this
    // specifically exercises the new `nullOnDelete()` constraint itself
    // via forceDelete(), not application code.
    $parent = Page::factory()->create();
    $child = Page::factory()->create(['parent_id' => $parent->id]);

    $parent->forceDelete();

    expect($child->fresh()->parent_id)->toBeNull();
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

it('keeps the address of a page the public site depends on', function () {
    $page = Page::factory()->published()->create(['slug' => 'leadership']);

    actingAs(pageUser('admin'))->put("/pages/{$page->id}", [
        'title' => ['ru' => 'Руководство Комитета'],
        'body' => ['ru' => '<p>Вводный текст раздела.</p>'],
        'slug' => 'rukovodstvo',
        'action' => 'draft',
    ])->assertSessionHasErrors('slug');

    expect($page->refresh()->slug)->toBe('leadership');
});

it('saves a site-section page when its address is left unchanged', function () {
    $page = Page::factory()->published()->create(['slug' => 'structure']);

    actingAs(pageUser('admin'))->put("/pages/{$page->id}", [
        'title' => ['ru' => 'Структура Комитета'],
        'body' => ['ru' => '<p>Новый вводный текст.</p>'],
        'slug' => 'structure',
        'action' => 'draft',
    ])->assertSessionHasNoErrors();

    expect($page->refresh()->getTranslation('body', 'ru'))->toBe('<p>Новый вводный текст.</p>');
});

it('refuses to delete a page the public site depends on', function (string $slug) {
    $page = Page::factory()->published()->create(['slug' => $slug]);

    actingAs(pageUser('admin'))->delete("/pages/{$page->id}")
        ->assertRedirect()
        ->assertSessionHas('error');

    expect($page->refresh()->trashed())->toBeFalse();
})->with(['about', 'symbols', 'privacy']);

it('links pages to their real address on the public site', function () {
    config(['services.frontend.url' => 'https://staging.khf.tj']);
    Page::factory()->published()->create([
        'slug' => 'symbols',
        'title' => ['ru' => 'Государственные символы Республики Таджикистан'],
    ]);
    Page::factory()->published()->create([
        'slug' => 'history',
        'title' => ['ru' => 'История'],
    ]);
    Page::factory()->create([
        'slug' => 'draft-page',
        'title' => ['ru' => 'Черновик'],
        'status' => ContentStatus::Draft,
    ]);

    actingAs(pageUser('admin'))->get('/pages')
        ->assertInertia(fn ($inertia) => $inertia
            ->where('public_site_url', 'https://staging.khf.tj')
            ->where('pages', function ($pages): bool {
                $bySlug = collect($pages)->keyBy('slug');

                return $bySlug['symbols']['public_url'] === 'https://staging.khf.tj/ru/symbols'
                    && $bySlug['symbols']['is_system'] === true
                    && $bySlug['history']['public_url'] === 'https://staging.khf.tj/ru/pages/history'
                    && $bySlug['history']['is_system'] === false
                    && $bySlug['draft-page']['public_url'] === null;
            }));
});

it('aligns untouched seeded texts of linked pages and keeps edited ones', function () {
    $page = Page::factory()->published()->create([
        'slug' => 'structure',
        'body' => [
            'ru' => '<p>В структуру Комитета входят центральный аппарат, центр управления в кризисных ситуациях, спасательные подразделения, гражданская оборона, подразделения предупреждения ЧС, учебный центр и региональные управления.</p>',
            'tg' => '<p>Матни таҳриршудаи муҳаррир.</p>',
        ],
    ]);

    (require database_path('migrations/2026_09_23_164743_align_linked_page_texts_with_site_sections.php'))->up();

    $page->refresh();

    expect($page->getTranslation('body', 'ru'))
        ->toBe('<p>Центральный аппарат, специализированные службы и региональные управления образуют единую государственную систему предупреждения и ликвидации чрезвычайных ситуаций.</p>')
        ->and($page->getTranslation('body', 'tg'))->toBe('<p>Матни таҳриршудаи муҳаррир.</p>');
});
