<?php

use App\Enums\ContentStatus;
use App\Models\Alert;
use App\Models\Announcement;
use App\Models\Page;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;

beforeEach(function () {
    seed(RolePermissionSeeder::class);
});

function approver(): User
{
    $user = User::factory()->create();
    $user->assignRole('chief_editor');

    return $user;
}

it('shows pending items in the approval queue', function () {
    Alert::factory()->create(['status' => ContentStatus::Review]);

    actingAs(approver())->get('/approvals')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('approvals')->has('queue', 1)->has('detail'));
});

it('includes every workflow content module in the approval queue', function () {
    Project::factory()->create(['status' => ContentStatus::Review]);
    Announcement::factory()->create(['status' => ContentStatus::TranslationCheck]);
    Page::factory()->create(['status' => ContentStatus::Review]);

    actingAs(approver())->get('/approvals')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('approvals')
            ->has('queue', 3)
            ->where('queue.0.type', fn (string $type): bool => in_array($type, ['project', 'announcement', 'page'], true)));
});

it('approves and returns newly supported workflow modules', function () {
    $project = Project::factory()->create(['status' => ContentStatus::Review]);
    $page = Page::factory()->create(['status' => ContentStatus::Review]);
    $user = approver();

    actingAs($user)->post('/approvals/approve', ['type' => 'project', 'id' => $project->id])
        ->assertRedirect('/approvals');

    actingAs($user)->post('/approvals/return', [
        'type' => 'page',
        'id' => $page->id,
        'comment' => 'Дополните содержание страницы.',
    ])->assertRedirect('/approvals');

    expect($project->fresh()->status)->toBe(ContentStatus::Published)
        ->and($page->fresh()->status)->toBe(ContentStatus::Returned);
});

it('approves and publishes a material', function () {
    $alert = Alert::factory()->create(['status' => ContentStatus::Review]);

    actingAs(approver())->post('/approvals/approve', ['type' => 'alert', 'id' => $alert->id])
        ->assertRedirect('/approvals');

    expect($alert->fresh()->status)->toBe(ContentStatus::Published);
});

it('requires a comment to return a material to its author', function () {
    $alert = Alert::factory()->create(['status' => ContentStatus::Review]);

    actingAs(approver())->post('/approvals/return', ['type' => 'alert', 'id' => $alert->id])
        ->assertSessionHasErrors('comment');

    expect($alert->fresh()->status)->toBe(ContentStatus::Review);
});

it('returns a material with a comment', function () {
    $alert = Alert::factory()->create(['status' => ContentStatus::Review]);

    actingAs(approver())->post('/approvals/return', ['type' => 'alert', 'id' => $alert->id, 'comment' => 'Уточните участок реки.'])
        ->assertRedirect('/approvals');

    expect($alert->fresh()->status)->toBe(ContentStatus::Returned);
});

it('forbids a viewer from approving', function () {
    $viewer = User::factory()->create();
    $viewer->assignRole('viewer');
    $alert = Alert::factory()->create(['status' => ContentStatus::Review]);

    actingAs($viewer)->post('/approvals/approve', ['type' => 'alert', 'id' => $alert->id])->assertForbidden();
});

it('forbids users without approval permissions from opening the approval center', function () {
    $viewer = User::factory()->create();
    $viewer->assignRole('viewer');

    actingAs($viewer)->get('/approvals')->assertForbidden();
    actingAs(User::factory()->create())->get('/approvals')->assertForbidden();
});

it('does not expose a material that is outside the approval queue', function () {
    $draft = Alert::factory()->create(['status' => ContentStatus::Draft]);

    actingAs(approver())
        ->get("/approvals?type=alert&id={$draft->id}")
        ->assertNotFound();
});
