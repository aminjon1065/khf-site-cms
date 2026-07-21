<?php

use App\Enums\ContentStatus;
use App\Models\Alert;
use App\Models\User;
use App\Notifications\WorkflowNotification;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Notification;

use function Pest\Laravel\artisan;
use function Pest\Laravel\seed;

beforeEach(function () {
    seed(RolePermissionSeeder::class);
});

it('publishes scheduled alerts whose time has passed', function () {
    $alert = Alert::factory()->create([
        'status' => ContentStatus::Scheduled,
        'scheduled_at' => now()->subMinutes(5),
        'published_at' => null,
    ]);

    artisan('content:process-scheduled')->assertSuccessful();

    $fresh = $alert->fresh();
    expect($fresh->status)->toBe(ContentStatus::Published);
    expect($fresh->published_at)->not->toBeNull();
});

it('auto-completes published alerts past their end time', function () {
    $alert = Alert::factory()->published()->create(['ends_at' => now()->subMinute()]);

    artisan('content:process-scheduled')->assertSuccessful();

    expect($alert->fresh()->status)->toBe(ContentStatus::Completed);
});

it('notifies the author about an alert expiring within 24 hours', function () {
    Notification::fake();
    $author = User::factory()->create();
    $alert = Alert::factory()->published()->create([
        'ends_at' => now()->addHours(12),
        'author_id' => $author->id,
        'expiry_notified_at' => null,
    ]);

    artisan('content:process-scheduled')->assertSuccessful();

    Notification::assertSentTo($author, WorkflowNotification::class);
    expect($alert->fresh()->expiry_notified_at)->not->toBeNull();
});

it('uses the Dushanbe timezone for editorial and scheduled dates', function () {
    expect(config('app.timezone'))->toBe('Asia/Dushanbe')
        ->and(config('app.schedule_timezone'))->toBe('Asia/Dushanbe');
});

it('does not treat a published alert as active before its start time', function () {
    $futureAlert = Alert::factory()->published()->create([
        'starts_at' => now()->addHour(),
        'ends_at' => now()->addHours(2),
    ]);

    expect(Alert::query()->active()->whereKey($futureAlert)->exists())->toBeFalse();
});
