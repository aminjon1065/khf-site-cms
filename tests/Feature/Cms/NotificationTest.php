<?php

use App\Enums\ContentStatus;
use App\Models\News;
use App\Models\User;
use App\Notifications\WorkflowNotification;
use App\Services\WorkflowService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;

it('shows the authenticated users notifications and can mark them read', function () {
    $user = User::factory()->create();
    $notification = $user->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => WorkflowNotification::class,
        'data' => [
            'title' => 'Нужно согласование',
            'message' => 'Откройте материал.',
            'tone' => 'warn',
            'url' => '/news/1/edit',
        ],
    ]);

    actingAs($user)->get('/notifications')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('notifications/index')
            ->has('items', 1)
            ->where('items.0.id', $notification->id)
            ->where('items.0.read_at', null));

    actingAs($user)->post("/notifications/{$notification->id}/read")
        ->assertRedirect();

    expect($notification->fresh()->read_at)->not->toBeNull();
});

it('does not let a user mark another users notification as read', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $notification = $owner->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => WorkflowNotification::class,
        'data' => ['title' => 'Личное уведомление'],
    ]);

    actingAs($other)->post("/notifications/{$notification->id}/read")
        ->assertRedirect();

    expect($notification->fresh()->read_at)->toBeNull();
});

it('includes a direct editor URL in workflow notification data', function () {
    $news = News::factory()->create();
    $notification = new WorkflowNotification(
        $news,
        'Материал ожидает согласования',
        'Откройте материал.',
        'warn',
    );

    expect($notification->toArray(User::factory()->make()))
        ->toMatchArray([
            'subject_type' => $news->getMorphClass(),
            'subject_id' => $news->id,
            'url' => "/news/{$news->id}/edit",
        ]);
});

it('sends approvers to the approval center and authors the reviewer comment', function () {
    Notification::fake();
    seed(RolePermissionSeeder::class);

    $author = User::factory()->create();
    $author->assignRole('editor');
    $approver = User::factory()->create();
    $approver->assignRole('approver');
    $news = News::factory()->create([
        'author_id' => $author->id,
        'status' => ContentStatus::Draft,
    ]);
    $workflow = app(WorkflowService::class);

    $workflow->transition($news, ContentStatus::Review, $author);

    Notification::assertSentTo(
        $approver,
        WorkflowNotification::class,
        fn (WorkflowNotification $notification): bool => $notification->toArray($approver)['url'] === '/approvals',
    );

    $workflow->transition($news->fresh(), ContentStatus::Returned, $approver, 'Уточните дату учений.');

    Notification::assertSentTo(
        $author,
        WorkflowNotification::class,
        fn (WorkflowNotification $notification): bool => str_contains($notification->message, 'Комментарий: Уточните дату учений.')
            && $notification->toArray($author)['url'] === "/news/{$news->id}/edit",
    );
});
