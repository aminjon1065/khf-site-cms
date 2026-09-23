<?php

use App\Enums\ContentStatus;
use App\Enums\RegionType;
use App\Models\News;
use App\Models\Page;
use App\Models\Region;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Notification;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;

beforeEach(function () {
    seed(RolePermissionSeeder::class);
    Notification::fake();
});

function bulkUser(string $role, array $attributes = []): User
{
    $user = User::factory()->create($attributes);
    $user->assignRole($role);

    return $user;
}

it('publishes each material through the checks of a single publication', function () {
    $ready = News::factory()->create();
    $unfinished = News::factory()->create([
        'title' => ['ru' => 'Черновик без текста', 'tg' => '', 'en' => ''],
        'summary' => ['ru' => '', 'tg' => '', 'en' => ''],
        'body' => ['ru' => '', 'tg' => '', 'en' => ''],
    ]);
    $live = News::factory()->published()->create(['title' => ['ru' => 'Уже на сайте', 'tg' => '', 'en' => '']]);

    actingAs(bulkUser('chief_editor'))
        ->postJson('/editorial/news/bulk', ['action' => 'publish', 'ids' => [$ready->id, $unfinished->id, $live->id, 999999]])
        ->assertOk()
        ->assertJson([
            'done' => 1,
            'message' => 'Опубликовано: 1 из 4.',
            'skipped' => [
                [
                    'id' => $unfinished->id,
                    'title' => 'Черновик без текста',
                    'reason' => 'Перед публикацией исправьте: Хотя бы одна языковая версия заполнена.',
                ],
                ['id' => $live->id, 'title' => 'Уже на сайте', 'reason' => 'Уже на сайте.'],
                ['id' => 999999, 'title' => '№ 999999', 'reason' => 'Не найден — возможно, его уже удалили.'],
            ],
        ]);

    expect($ready->fresh()->status)->toBe(ContentStatus::Published)
        ->and($ready->transitions()->first()->to_status)->toBe('published')
        ->and($unfinished->fresh()->status)->toBe(ContentStatus::Draft);
});

it('offers publishing only to those who may publish', function () {
    $draft = News::factory()->create();

    actingAs(bulkUser('translator'))
        ->postJson('/editorial/news/bulk', ['action' => 'publish', 'ids' => [$draft->id]])
        ->assertForbidden();

    expect($draft->fresh()->status)->toBe(ContentStatus::Draft);
});

it('sends drafts for approval and leaves the rest as they are', function () {
    $draft = News::factory()->create();
    $returned = News::factory()->create(['status' => ContentStatus::Returned]);
    $inReview = News::factory()->create(['status' => ContentStatus::Review]);

    actingAs(bulkUser('editor'))
        ->postJson('/editorial/news/bulk', ['action' => 'submit', 'ids' => [$draft->id, $returned->id, $inReview->id]])
        ->assertOk()
        ->assertJsonPath('done', 2)
        ->assertJsonPath('message', 'Отправлено на согласование: 2 из 3.')
        ->assertJsonPath('skipped.0.reason', 'Уже на согласовании.');

    expect($draft->fresh()->status)->toBe(ContentStatus::Review)
        ->and($returned->fresh()->status)->toBe(ContentStatus::Review);
});

it('keeps a regional editor to their own materials without revealing others', function () {
    $region = Region::query()->create([
        'name' => ['ru' => 'Регион', 'tg' => 'Минтақа', 'en' => 'Region'],
        'code' => 'bulk-region',
        'type' => RegionType::Oblast,
        'districts_count' => 1,
        'sort' => 1,
    ]);
    $regionalEditor = bulkUser('regional_editor', ['region_id' => $region->id]);
    $own = News::factory()->create(['author_id' => $regionalEditor->id]);
    $foreign = News::factory()->create([
        'author_id' => bulkUser('editor')->id,
        'title' => ['ru' => 'Чужой черновик', 'tg' => '', 'en' => ''],
    ]);

    actingAs($regionalEditor)
        ->postJson('/editorial/news/bulk', ['action' => 'submit', 'ids' => [$own->id, $foreign->id]])
        ->assertOk()
        ->assertJsonPath('done', 1)
        ->assertJsonPath('skipped.0.title', "№ {$foreign->id}")
        ->assertJsonMissing(['title' => 'Чужой черновик']);

    expect($own->fresh()->status)->toBe(ContentStatus::Review)
        ->and($foreign->fresh()->status)->toBe(ContentStatus::Draft);
});

it('moves materials to the trash but keeps the pages the site depends on', function () {
    $history = Page::factory()->create(['slug' => 'history']);
    $about = Page::factory()->create(['slug' => 'about']);

    actingAs(bulkUser('chief_editor'))
        ->postJson('/editorial/pages/bulk', ['action' => 'trash', 'ids' => [$history->id, $about->id]])
        ->assertOk()
        ->assertJsonPath('done', 1)
        ->assertJsonPath('skipped.0.reason', 'Эту страницу выводит раздел сайта: её можно только снять с публикации.');

    expect($history->fresh()->trashed())->toBeTrue()
        ->and($about->fresh()->trashed())->toBeFalse();
});

it('checks the right to delete before moving anything to the trash', function () {
    $draft = News::factory()->create();

    // Editors may publish news but not delete it.
    actingAs(bulkUser('editor'))
        ->postJson('/editorial/news/bulk', ['action' => 'trash', 'ids' => [$draft->id]])
        ->assertForbidden();

    expect($draft->fresh()->trashed())->toBeFalse();
});

it('rejects an empty or oversized selection and unknown actions', function (array $payload, string $field) {
    actingAs(bulkUser('chief_editor'))
        ->postJson('/editorial/news/bulk', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'nothing selected' => [['action' => 'publish', 'ids' => []], 'ids'],
    'more than a page' => [['action' => 'publish', 'ids' => range(1, 101)], 'ids'],
    'repeated ids' => [['action' => 'publish', 'ids' => [1, 1]], 'ids.0'],
    'unknown action' => [['action' => 'archive', 'ids' => [1]], 'action'],
]);

it('knows only the editorial lists', function () {
    actingAs(bulkUser('chief_editor'))
        ->postJson('/editorial/alerts/bulk', ['action' => 'publish', 'ids' => [1]])
        ->assertNotFound();
});
