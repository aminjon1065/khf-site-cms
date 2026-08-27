<?php

use App\Enums\AnnouncementKind;
use App\Enums\ContentStatus;
use App\Models\Announcement;
use App\Models\Project;
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
        'application_url' => '/contacts',
        'action' => 'draft',
    ])->assertRedirect('/announcements');

    $announcement = Announcement::query()->first();

    expect($announcement->status)->toBe(ContentStatus::Draft)
        ->and($announcement->kind)->toBe(AnnouncementKind::Vacancy)
        ->and($announcement->org)->toBe('ЦУКС')
        ->and($announcement->slug)->toBe('vakansiya-operatora-112')
        ->and($announcement->application_url)->toBe('/contacts');
});

it('saves the project a tender belongs to', function () {
    // Регрессия: поле было во всей цепочке — миграция, $fillable, правила
    // AnnouncementRequest, payload формы и сам <select> в редакторе, — но
    // fill() его не присваивал. Редактор выбирал проект, получал «сохранено»,
    // а связь не записывалась: блок «Тендеры проекта» на публичной странице
    // оставался пустым, и ввод исчезал без единого сообщения.
    //
    // Прежние тесты проверяли только чтение через API и проставляли связь
    // фабрикой, поэтому путь сохранения из CMS был не покрыт вовсе.
    $project = Project::factory()->published()->create();

    actingAs(annUser('editor'))->post('/announcements', [
        'title' => ['ru' => 'Закупка инструмента', 'tg' => '', 'en' => ''],
        'kind' => 'tender',
        'project_id' => $project->id,
        'action' => 'draft',
    ])->assertRedirect('/announcements');

    expect(Announcement::query()->first()->project_id)->toBe($project->id);
});

it('clears the project when the editor picks «вне проекта»', function () {
    // Пустая строка из <select> — это отсутствие связи, а не проект с id 0.
    $project = Project::factory()->published()->create();
    $announcement = Announcement::factory()->create([
        'kind' => AnnouncementKind::Tender,
        'project_id' => $project->id,
    ]);

    actingAs(annUser('editor'))->put("/announcements/{$announcement->id}", [
        'title' => $announcement->getTranslations('title'),
        'kind' => 'tender',
        'project_id' => '',
        'action' => 'draft',
    ])->assertRedirect();

    expect($announcement->fresh()->project_id)->toBeNull();
});

it('rejects unsafe application links and duplicate slugs', function () {
    Announcement::factory()->create(['slug' => 'reserved-address']);

    actingAs(annUser('editor'))->post('/announcements', [
        'title' => ['ru' => 'Новое объявление'],
        'kind' => 'vacancy',
        'slug' => 'reserved-address',
        'application_url' => 'javascript:alert(1)',
        'action' => 'draft',
    ])->assertSessionHasErrors(['slug', 'application_url']);
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
        'title' => ['ru' => 'Публичное объявление', 'tg' => 'Эълони оммавӣ', 'en' => ''],
    ]);

    actingAs(annUser('chief_editor'))->post("/announcements/{$announcement->id}/publish")->assertRedirect();

    expect($announcement->fresh()->status)->toBe(ContentStatus::Published);

    // Явный `?locale=`: у Symfony-тест-клиента дефолтный `Accept-Language`
    // всегда `en-us` (см. Request::create()), а без ru/tg-заголовка
    // ResolveApiLocale отдаст `en`, где у объявления пустой перевод.
    $this->getJson('/api/v1/announcements?locale=ru')
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
