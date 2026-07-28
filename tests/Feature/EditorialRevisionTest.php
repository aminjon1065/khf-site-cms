<?php

use App\Enums\ContentStatus;
use App\Models\EditorialRevision;
use App\Models\News;
use App\Models\User;
use App\Support\EditorialContent;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;

beforeEach(function () {
    seed(RolePermissionSeeder::class);
});

function editorialUser(string $role = 'editor'): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

/**
 * @return array<string, mixed>
 */
function autosavePayload(
    News $news,
    string $draftKey,
    ?int $cursor = null,
    ?string $openedAt = null,
): array {
    return [
        'content_type' => 'news',
        'content_id' => $news->id,
        'draft_key' => $draftKey,
        'data' => [
            'title' => ['ru' => 'Автосохранённый заголовок', 'tg' => '', 'en' => ''],
            'body' => ['ru' => 'Локальная работа редактора', 'tg' => '', 'en' => ''],
        ],
        'base_version' => app(EditorialContent::class)->version($news),
        'revision_cursor' => $cursor,
        'opened_at' => $openedAt ?? now()->subMinute()->toIso8601String(),
    ];
}

it('stores a durable autosave without mutating the published model', function () {
    $editor = editorialUser();
    $news = News::factory()->published()->create([
        'title' => ['ru' => 'Опубликованный заголовок', 'tg' => '', 'en' => ''],
    ]);

    actingAs($editor)
        ->postJson('/editorial/autosave', autosavePayload($news, (string) Str::uuid()))
        ->assertCreated()
        ->assertJsonPath('revision_id', 1);

    $revision = EditorialRevision::query()->firstOrFail();

    expect(data_get($revision->data, 'title.ru'))->toBe('Автосохранённый заголовок')
        ->and($revision->source)->toBe('autosave')
        ->and($news->fresh()->getTranslation('title', 'ru'))->toBe('Опубликованный заголовок')
        ->and($news->fresh()->status)->toBe(ContentStatus::Published);
});

it('returns a conflict when another tab autosaved after this editor opened the form', function () {
    $editor = editorialUser();
    $news = News::factory()->create();
    $openedAt = now()->subMinute()->toIso8601String();

    actingAs($editor)
        ->postJson('/editorial/autosave', autosavePayload(
            $news,
            (string) Str::uuid(),
            openedAt: $openedAt,
        ))
        ->assertCreated();

    actingAs($editor)
        ->postJson('/editorial/autosave', autosavePayload(
            $news,
            (string) Str::uuid(),
            openedAt: $openedAt,
        ))
        ->assertConflict()
        ->assertJsonPath('message', 'Материал изменён в другой вкладке или другим сотрудником.')
        ->assertJsonPath('remote.title.ru', 'Автосохранённый заголовок');
});

it('allows an explicit local version to resolve an autosave conflict', function () {
    $editor = editorialUser();
    $news = News::factory()->create();
    $openedAt = now()->subMinute()->toIso8601String();

    actingAs($editor)
        ->postJson('/editorial/autosave', autosavePayload(
            $news,
            (string) Str::uuid(),
            openedAt: $openedAt,
        ))
        ->assertCreated();

    actingAs($editor)
        ->postJson('/editorial/autosave', [
            ...autosavePayload($news, (string) Str::uuid(), openedAt: $openedAt),
            'force' => true,
            'data' => ['title' => ['ru' => 'Моя выбранная версия']],
        ])
        ->assertCreated();

    expect(EditorialRevision::query()->count())->toBe(2)
        ->and(data_get(EditorialRevision::query()->latest('id')->first()?->data, 'title.ru'))
        ->toBe('Моя выбранная версия');
});

it('rejects a stale normal save with an actionable conflict error', function () {
    $editor = editorialUser();
    $news = News::factory()->create();
    $staleVersion = app(EditorialContent::class)->version($news);

    $news->forceFill(['updated_at' => now()->addMinute()])->saveQuietly();

    actingAs($editor)->put("/news/{$news->id}", [
        'title' => ['ru' => 'Старая вкладка'],
        'action' => 'draft',
        '_editorial_version' => $staleVersion,
    ])->assertSessionHasErrors('editorial_conflict');

    expect($news->fresh()->getTranslation('title', 'ru'))
        ->not->toBe('Старая вкладка');
});

it('captures manual revisions and lists them newest first', function () {
    $editor = editorialUser();
    $news = News::factory()->create();
    $version = app(EditorialContent::class)->version($news);

    actingAs($editor)->put("/news/{$news->id}", [
        'title' => ['ru' => 'Ручное сохранение'],
        'action' => 'draft',
        '_editorial_version' => $version,
    ])->assertRedirect('/news');

    actingAs($editor)
        ->getJson("/editorial/news/{$news->id}/revisions")
        ->assertOk()
        ->assertJsonPath('data.0.source', 'manual')
        ->assertJsonPath('data.0.saved_by', $editor->name);
});

it('restores editorial fields while preserving workflow status', function () {
    $chiefEditor = editorialUser('chief_editor');
    $news = News::factory()->published()->create([
        'title' => ['ru' => 'Текущая версия', 'tg' => '', 'en' => ''],
    ]);
    $revision = EditorialRevision::factory()->create([
        'content_type' => 'news',
        'content_id' => $news->id,
        'user_id' => $chiefEditor->id,
        'data' => [
            'title' => ['ru' => 'Восстановленная версия', 'tg' => '', 'en' => ''],
            'body' => ['ru' => 'Старый текст', 'tg' => '', 'en' => ''],
        ],
        'source' => 'manual',
    ]);

    actingAs($chiefEditor)
        ->postJson("/editorial/revisions/{$revision->id}/restore")
        ->assertOk()
        ->assertJsonPath('data.title.ru', 'Восстановленная версия');

    $news->refresh();

    expect($news->getTranslation('title', 'ru'))->toBe('Восстановленная версия')
        ->and($news->status)->toBe(ContentStatus::Published)
        ->and(EditorialRevision::query()->where('source', 'before_restore')->exists())
        ->toBeTrue();
});

it('forbids users without edit access from autosaving content', function () {
    $viewer = editorialUser('viewer');
    $news = News::factory()->create();

    actingAs($viewer)
        ->postJson('/editorial/autosave', autosavePayload($news, (string) Str::uuid()))
        ->assertForbidden();
});

it('wires recovery, locking and revision history into every editorial form', function (string $form) {
    $source = file_get_contents(resource_path("js/pages/{$form}/form.tsx"));

    expect($source)
        ->toContain('autosave={{')
        ->toContain('baseVersion:')
        ->toContain('onRecover:')
        ->toContain('_editorial_version:');
})->with([
    'news',
    'pages',
    'projects',
    'instructions',
    'announcements',
    'documents',
]);
