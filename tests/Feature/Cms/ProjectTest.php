<?php

use App\Enums\ContentStatus;
use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;

beforeEach(function () {
    seed(RolePermissionSeeder::class);
});

function projUser(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

it('grants the editor role the new projects permissions', function () {
    expect(projUser('editor')->can('projects.create'))->toBeTrue();
    expect(projUser('viewer')->can('projects.create'))->toBeFalse();
});

it('lets an editor open the project create form', function () {
    actingAs(projUser('editor'))->get('/projects/create')->assertOk();
});

it('forbids a viewer from opening the create form', function () {
    actingAs(projUser('viewer'))->get('/projects/create')->assertForbidden();
});

it('creates a draft project with cleaned goals, timeline and direction', function () {
    actingAs(projUser('editor'))->post('/projects', [
        'title' => ['ru' => 'Новый проект', 'tg' => '', 'en' => ''],
        'lifecycle_status' => 'implementing',
        'years' => '2026–2030',
        'partner' => 'ООН',
        'budget' => '10 млн долл. США',
        'goals' => ['ru' => ['Цель 1', '   ']], // blank goal dropped
        'timeline' => [
            ['date' => '2026', 'text' => 'Старт проекта', 'tone' => 'success'],
            ['date' => '', 'text' => '', 'tone' => 'info'], // empty row dropped
        ],
        'direction' => ['address' => 'Душанбе', 'phone' => '112', 'email' => 'x@khf.tj'],
        'action' => 'draft',
    ])->assertRedirect('/projects');

    $project = Project::query()->first();

    expect($project->status)->toBe(ContentStatus::Draft)
        ->and($project->lifecycle_status)->toBe(ProjectStatus::Implementing)
        ->and($project->slug)->not->toBeEmpty()
        ->and($project->goals['ru'])->toBe(['Цель 1'])
        ->and($project->timeline)->toHaveCount(1)
        ->and($project->direction['email'])->toBe('x@khf.tj');
});

it('requires a russian title and a lifecycle status', function () {
    actingAs(projUser('editor'))->post('/projects', [
        'title' => ['ru' => ''],
        'action' => 'draft',
    ])->assertSessionHasErrors(['title.ru', 'lifecycle_status']);
});

it('sends a project to review when an editor submits', function () {
    actingAs(projUser('editor'))->post('/projects', [
        'title' => ['ru' => 'Проект на согласование', 'tg' => '', 'en' => ''],
        'lifecycle_status' => 'preparation',
        'action' => 'submit',
        'publish_mode' => 'review',
    ])->assertRedirect('/projects');

    expect(Project::query()->first()->status)->toBe(ContentStatus::Review);
});

it('publishes a project and it becomes public', function () {
    $project = Project::factory()->create([
        'slug' => 'proj-pub',
        'title' => ['ru' => 'Публичный проект', 'tg' => '', 'en' => ''],
    ]);

    actingAs(projUser('chief_editor'))->post("/projects/{$project->id}/publish")->assertRedirect();

    expect($project->fresh()->status)->toBe(ContentStatus::Published);

    $this->getJson('/api/v1/projects/proj-pub')
        ->assertOk()
        ->assertJsonPath('data.title', 'Публичный проект');
});

it('forbids a viewer from deleting a project', function () {
    $project = Project::factory()->create();

    actingAs(projUser('viewer'))->delete("/projects/{$project->id}")->assertForbidden();
});

it('soft-deletes a project for an authorized user', function () {
    $project = Project::factory()->create();

    actingAs(projUser('chief_editor'))->delete("/projects/{$project->id}")->assertRedirect();

    expect(Project::query()->find($project->id))->toBeNull()
        ->and(Project::withTrashed()->find($project->id))->not->toBeNull();
});
