<?php

use App\Enums\SubmissionStatus;
use App\Models\Submission;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;

beforeEach(function () {
    seed(RolePermissionSeeder::class);
});

function subUser(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

it('lets a chief editor open the submissions list', function () {
    actingAs(subUser('chief_editor'))->get('/submissions')->assertOk();
});

it('clamps an excessive submissions page size', function () {
    actingAs(subUser('chief_editor'))->get('/submissions?per_page=999999')
        ->assertInertia(fn ($page) => $page
            ->component('submissions/index')
            ->where('meta.per_page', 100));
});

it('forbids a role without submissions access from the list', function () {
    actingAs(subUser('editor'))->get('/submissions')->assertForbidden();
});

it('shows a submission detail to an authorized user', function () {
    $submission = Submission::factory()->create();

    actingAs(subUser('chief_editor'))->get("/submissions/{$submission->id}")->assertOk();
});

it('updates the status and assignee', function () {
    $submission = Submission::factory()->create();
    $assignee = subUser('admin');

    actingAs(subUser('chief_editor'))->put("/submissions/{$submission->id}", [
        'status' => 'in_progress',
        'assigned_to' => $assignee->id,
    ])->assertRedirect();

    $submission->refresh();

    expect($submission->status)->toBe(SubmissionStatus::InProgress)
        ->and($submission->assigned_to)->toBe($assignee->id);
});

it('adds an internal comment attributed to the current user', function () {
    $submission = Submission::factory()->create();
    $user = subUser('chief_editor');

    actingAs($user)->post("/submissions/{$submission->id}/comments", [
        'body' => 'Передано в региональное управление.',
    ])->assertRedirect();

    expect($submission->comments()->count())->toBe(1)
        ->and($submission->comments()->first()->user_id)->toBe($user->id);
});

it('forbids a viewer from updating a submission', function () {
    $submission = Submission::factory()->create();

    actingAs(subUser('viewer'))->put("/submissions/{$submission->id}", [
        'status' => 'reviewed',
    ])->assertForbidden();
});

it('soft-deletes a submission for an authorized user', function () {
    $submission = Submission::factory()->create();

    actingAs(subUser('chief_editor'))->delete("/submissions/{$submission->id}")->assertRedirect();

    expect(Submission::query()->find($submission->id))->toBeNull()
        ->and(Submission::withTrashed()->find($submission->id))->not->toBeNull();
});
