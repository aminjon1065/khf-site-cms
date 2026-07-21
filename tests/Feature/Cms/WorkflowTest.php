<?php

use App\Enums\ContentStatus;
use App\Models\Activity;
use App\Models\Alert;
use App\Models\User;
use App\Notifications\WorkflowNotification;
use App\Services\WorkflowService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

use function Pest\Laravel\seed;

beforeEach(function () {
    seed(RolePermissionSeeder::class);
    $this->workflow = app(WorkflowService::class);
});

function makeUser(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

it('records a transition, updates status and writes a workflow row', function () {
    $author = makeUser('editor');
    $alert = Alert::factory()->create(['status' => ContentStatus::Draft, 'author_id' => $author->id]);

    $this->workflow->transition($alert, ContentStatus::Review, $author);

    expect($alert->fresh()->status)->toBe(ContentStatus::Review);
    expect($alert->transitions()->where('to_status', 'review')->exists())->toBeTrue();
});

it('requires a comment when returning to the author', function () {
    $actor = makeUser('approver');
    $alert = Alert::factory()->create(['status' => ContentStatus::Review]);

    expect(fn () => $this->workflow->transition($alert, ContentStatus::Returned, $actor, null, force: true))
        ->toThrow(ValidationException::class);

    expect($alert->fresh()->status)->toBe(ContentStatus::Review);
});

it('rejects an invalid transition without bypassing the workflow graph', function () {
    $actor = makeUser('chief_editor');
    $alert = Alert::factory()->create(['status' => ContentStatus::Draft]);

    expect(fn () => $this->workflow->transition($alert, ContentStatus::Returned, $actor, 'Вернуть.'))
        ->toThrow(ValidationException::class);

    expect($alert->fresh()->status)->toBe(ContentStatus::Draft);
});

it('notifies available module approvers when no approver is assigned', function () {
    Notification::fake();
    $author = makeUser('editor');
    $chiefEditor = makeUser('chief_editor');
    $alert = Alert::factory()->create([
        'status' => ContentStatus::Draft,
        'author_id' => $author->id,
        'approver_id' => null,
    ]);

    $this->workflow->transition($alert, ContentStatus::Review, $author);

    Notification::assertSentTo($chiefEditor, WorkflowNotification::class);
});

it('requires a comment when cancelling a published alert', function () {
    $actor = makeUser('alert_operator');
    $alert = Alert::factory()->published()->create();

    expect(fn () => $this->workflow->transition($alert, ContentStatus::Cancelled, $actor, ''))
        ->toThrow(ValidationException::class);
});

it('accepts a return with a comment and notifies the author', function () {
    Notification::fake();
    $author = makeUser('editor');
    $approver = makeUser('approver');
    $alert = Alert::factory()->create(['status' => ContentStatus::Review, 'author_id' => $author->id]);

    $this->workflow->transition($alert, ContentStatus::Returned, $approver, 'Уточните формулировки.');

    expect($alert->fresh()->status)->toBe(ContentStatus::Returned);
    Notification::assertSentTo($author, WorkflowNotification::class);
});

it('blocks a non-operator from publishing a critical alert', function () {
    $editor = makeUser('editor');
    $alert = Alert::factory()->critical()->create(['status' => ContentStatus::Approved]);

    expect(fn () => $this->workflow->transition($alert, ContentStatus::Published, $editor))
        ->toThrow(ValidationException::class);
});

it('lets an alert operator publish a critical alert and stamps published_at', function () {
    $operator = makeUser('alert_operator');
    $alert = Alert::factory()->critical()->create(['status' => ContentStatus::Approved, 'published_at' => null]);

    $this->workflow->transition($alert, ContentStatus::Published, $operator);

    $fresh = $alert->fresh();
    expect($fresh->status)->toBe(ContentStatus::Published);
    expect($fresh->published_at)->not->toBeNull();
});

it('logs a critical activity entry when an alert is published', function () {
    $operator = makeUser('alert_operator');
    $alert = Alert::factory()->create(['status' => ContentStatus::Approved]);

    $this->workflow->transition($alert, ContentStatus::Published, $operator);

    expect(Activity::query()->where('is_critical', true)->exists())->toBeTrue();
});
