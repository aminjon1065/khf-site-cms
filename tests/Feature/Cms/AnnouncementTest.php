<?php

use App\Enums\AnnouncementKind;
use App\Enums\ContentStatus;
use App\Models\Announcement;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;

beforeEach(function () {
    seed(RolePermissionSeeder::class);
});

function annUser(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

it('grants the editor role the new announcements permissions', function () {
    expect(annUser('editor')->can('announcements.create'))->toBeTrue();
    expect(annUser('viewer')->can('announcements.create'))->toBeFalse();
});

it('lets an editor open the announcement create form', function () {
    actingAs(annUser('editor'))->get('/announcements/create')->assertOk();
});

it('forbids a viewer from opening the create form', function () {
    actingAs(annUser('viewer'))->get('/announcements/create')->assertForbidden();
});

it('creates a draft announcement with its metadata', function () {
    actingAs(annUser('editor'))->post('/announcements', [
        'title' => ['ru' => 'Вакансия оператора 112', 'tg' => '', 'en' => ''],
        'kind' => 'vacancy',
        'org' => 'ЦУКС',
        'deadline' => now()->addMonth()->toDateString(),
        'action' => 'draft',
    ])->assertRedirect('/announcements');

    $announcement = Announcement::query()->first();

    expect($announcement->status)->toBe(ContentStatus::Draft)
        ->and($announcement->kind)->toBe(AnnouncementKind::Vacancy)
        ->and($announcement->org)->toBe('ЦУКС');
});

it('requires a russian title and a kind', function () {
    actingAs(annUser('editor'))->post('/announcements', [
        'title' => ['ru' => ''],
        'action' => 'draft',
    ])->assertSessionHasErrors(['title.ru', 'kind']);
});

it('sends an announcement to review when an editor submits', function () {
    actingAs(annUser('editor'))->post('/announcements', [
        'title' => ['ru' => 'Тендер на согласование', 'tg' => '', 'en' => ''],
        'kind' => 'tender',
        'action' => 'submit',
        'publish_mode' => 'review',
    ])->assertRedirect('/announcements');

    expect(Announcement::query()->first()->status)->toBe(ContentStatus::Review);
});

it('publishes an announcement and it becomes public', function () {
    $announcement = Announcement::factory()->create([
        'deadline' => now()->addMonth(),
        'title' => ['ru' => 'Публичное объявление', 'tg' => '', 'en' => ''],
    ]);

    actingAs(annUser('chief_editor'))->post("/announcements/{$announcement->id}/publish")->assertRedirect();

    expect($announcement->fresh()->status)->toBe(ContentStatus::Published);

    $this->getJson('/api/v1/announcements')
        ->assertOk()
        ->assertJsonPath('data.0.title', 'Публичное объявление');
});

it('forbids a viewer from deleting an announcement', function () {
    $announcement = Announcement::factory()->create();

    actingAs(annUser('viewer'))->delete("/announcements/{$announcement->id}")->assertForbidden();
});

it('soft-deletes an announcement for an authorized user', function () {
    $announcement = Announcement::factory()->create();

    actingAs(annUser('chief_editor'))->delete("/announcements/{$announcement->id}")->assertRedirect();

    expect(Announcement::query()->find($announcement->id))->toBeNull()
        ->and(Announcement::withTrashed()->find($announcement->id))->not->toBeNull();
});
