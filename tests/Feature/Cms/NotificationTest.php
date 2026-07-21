<?php

use App\Models\News;
use App\Models\User;
use App\Notifications\WorkflowNotification;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

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
