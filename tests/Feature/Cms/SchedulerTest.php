<?php

use App\Enums\ContentStatus;
use App\Models\Alert;
use App\Models\News;
use App\Models\User;
use App\Notifications\WorkflowNotification;
use App\Services\AlertMapService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
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

it('records when it last reconciled alert states, for the site', function () {
    $this->travelTo(now()->setTime(10, 5));

    artisan('content:process-scheduled')->assertSuccessful();

    expect(Cache::get(AlertMapService::RECONCILED_AT_KEY))->toBe(now()->toIso8601String());
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

it('keeps publishing and completing when one material fails its checks', function () {
    Notification::fake();
    $author = User::factory()->create();

    // Not publishable: no title in any required language.
    $broken = News::factory()->create([
        'status' => ContentStatus::Scheduled,
        'scheduled_at' => now()->subMinutes(2),
        'published_at' => null,
        'author_id' => $author->id,
        'title' => [],
    ]);
    $alert = Alert::factory()->create([
        'status' => ContentStatus::Scheduled,
        'scheduled_at' => now()->subMinute(),
        'published_at' => null,
    ]);
    $expired = Alert::factory()->published()->create(['ends_at' => now()->subMinute()]);

    artisan('content:process-scheduled')->assertSuccessful();

    expect($broken->fresh()->status)->toBe(ContentStatus::Scheduled)
        ->and($alert->fresh()->status)->toBe(ContentStatus::Published)
        ->and($expired->fresh()->status)->toBe(ContentStatus::Completed)
        ->and(Cache::get(AlertMapService::RECONCILED_AT_KEY))->not->toBeNull();

    Notification::assertSentTo($author, WorkflowNotification::class, 1);
});

it('tells the author about a failed scheduled publication once, not every run', function () {
    Notification::fake();
    Log::spy();
    $author = User::factory()->create();
    News::factory()->create([
        'status' => ContentStatus::Scheduled,
        'scheduled_at' => now()->subMinute(),
        'published_at' => null,
        'author_id' => $author->id,
        'title' => [],
    ]);

    artisan('content:process-scheduled')->assertSuccessful();
    artisan('content:process-scheduled')->assertSuccessful();

    Notification::assertSentToTimes($author, WorkflowNotification::class, 1);
    Log::shouldHaveReceived('critical')
        ->withArgs(fn (string $message): bool => $message === 'scheduled_content_failed')
        ->once();
});

it('runs every minute so a planned alert is not late', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event): bool => str_contains((string) $event->command, 'content:process-scheduled'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('* * * * *')
        ->and($event->withoutOverlapping)->toBeTrue()
        ->and($event->expiresAt)->toBe(10);
});
