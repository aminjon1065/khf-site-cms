<?php

use App\Enums\ContentStatus;
use App\Models\Instruction;
use App\Models\News;
use App\Models\Page;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;

beforeEach(function () {
    seed(RolePermissionSeeder::class);
});

function editorialTrashUser(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

it('lists soft-deleted editorial types in one policy-aware trash screen', function () {
    $viewer = editorialTrashUser('viewer');
    $news = News::factory()->published()->create([
        'title' => ['ru' => 'Удалённая новость', 'tg' => '', 'en' => ''],
    ]);
    $page = Page::factory()->create([
        'title' => ['ru' => 'Удалённая страница', 'tg' => '', 'en' => ''],
    ]);
    $news->delete();
    $page->delete();

    actingAs($viewer)
        ->get('/editorial/trash')
        ->assertOk()
        ->assertInertia(fn ($inertia) => $inertia
            ->component('editorial/trash')
            ->has('items', 2)
            ->where('items.0.can_restore', false)
            ->where('meta.total', 2),
        );
});

it('filters the trash by editorial type', function () {
    $admin = editorialTrashUser('admin');
    News::factory()->create()->delete();
    Page::factory()->create()->delete();

    actingAs($admin)
        ->get('/editorial/trash?type=pages')
        ->assertOk()
        ->assertInertia(fn ($inertia) => $inertia
            ->has('items', 1)
            ->where('items.0.type', 'pages')
            ->where('filters.type', 'pages'),
        );
});

it('restores a material without changing its workflow status', function () {
    $admin = editorialTrashUser('admin');
    $news = News::factory()->published()->create();
    $news->delete();

    actingAs($admin)
        ->post("/editorial/trash/news/{$news->id}/restore")
        ->assertRedirect("/news/{$news->id}/edit")
        ->assertSessionHas('success');

    $restored = News::query()->findOrFail($news->id);

    expect($restored->status)->toBe(ContentStatus::Published)
        ->and($restored->deleted_at)->toBeNull();
});

it('forbids restore without delete permission', function () {
    $chiefEditor = editorialTrashUser('chief_editor');
    $instruction = Instruction::factory()->create();
    $instruction->delete();

    actingAs($chiefEditor)
        ->post("/editorial/trash/instructions/{$instruction->id}/restore")
        ->assertForbidden();

    expect(Instruction::onlyTrashed()->find($instruction->id))->not->toBeNull();
});

it('limits regional editors to their own trashed content', function () {
    $regionalEditor = editorialTrashUser('regional_editor');
    $otherEditor = editorialTrashUser('editor');
    $ownNews = News::factory()->create(['author_id' => $regionalEditor->id]);
    $otherNews = News::factory()->create(['author_id' => $otherEditor->id]);
    $ownNews->delete();
    $otherNews->delete();

    actingAs($regionalEditor)
        ->get('/editorial/trash?type=news')
        ->assertOk()
        ->assertInertia(fn ($inertia) => $inertia
            ->has('items', 1)
            ->where('items.0.id', $ownNews->id)
            ->where('items.0.can_restore', false),
        );
});
