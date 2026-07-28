<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\News;
use App\Models\User;
use App\Support\NavBadges;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;

beforeEach(function () {
    seed(RolePermissionSeeder::class);
});

function sharedPropsUser(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

it('keeps notification bodies out of the initial shared payload', function () {
    $user = sharedPropsUser('viewer');
    $marker = 'large-notification-'.str_repeat('x', 4000);

    foreach (range(1, 8) as $index) {
        $user->notifications()->create([
            'id' => fake()->uuid(),
            'type' => 'test',
            'data' => [
                'title' => "Notification {$index}",
                'message' => $marker,
                'tone' => 'info',
            ],
        ]);
    }

    DB::flushQueryLog();
    DB::enableQueryLog();
    $response = actingAs($user)->get('/alerts')->assertOk();
    $notificationQueries = collect(DB::getQueryLog())
        ->pluck('query')
        ->filter(fn (string $query): bool => preg_match('/from [`"]notifications[`"]/', $query) === 1)
        ->values();
    DB::disableQueryLog();

    $response->assertInertia(fn (Assert $page) => $page
        ->component('alerts/index')
        ->where('notification_unread', 8)
        ->missing('notifications'));

    expect($response->getContent())->not->toContain($marker)
        ->and(strlen($response->getContent()))->toBeLessThan(50_000)
        ->and($notificationQueries)->toHaveCount(1)
        ->and($notificationQueries->first())->toContain('count(*)');
});

it('loads recent notifications only through an explicit partial reload', function () {
    $user = sharedPropsUser('viewer');
    $version = app(HandleInertiaRequests::class)->version(request());
    $user->notifications()->create([
        'id' => fake()->uuid(),
        'type' => 'test',
        'data' => [
            'title' => 'Publication ready',
            'message' => 'Open-on-demand marker',
            'tone' => 'ok',
        ],
    ]);

    $response = actingAs($user)
        ->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $version ?? '',
            'X-Inertia-Partial-Component' => 'alerts/index',
            'X-Inertia-Partial-Data' => 'notifications,notification_unread',
        ])
        ->get('/alerts')
        ->assertOk();

    $response
        ->assertJsonPath('component', 'alerts/index')
        ->assertJsonPath('props.notification_unread', 1)
        ->assertJsonPath('props.notifications.items.0.message', 'Open-on-demand marker')
        ->assertJsonMissingPath('props.auth')
        ->assertJsonMissingPath('props.nav_badges');
});

it('does not recompute once props already held by the inertia client', function () {
    $user = sharedPropsUser('viewer');
    $version = app(HandleInertiaRequests::class)->version(request());

    $response = actingAs($user)
        ->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $version ?? '',
            'X-Inertia-Except-Once-Props' => 'auth,nav_badges',
        ])
        ->get('/alerts')
        ->assertOk();

    $response
        ->assertJsonPath('component', 'alerts/index')
        ->assertJsonPath('props.notification_unread', 0)
        ->assertJsonMissingPath('props.auth')
        ->assertJsonMissingPath('props.nav_badges')
        ->assertJsonMissingPath('props.notifications')
        ->assertJsonPath('onceProps.auth.prop', 'auth')
        ->assertJsonPath('onceProps.nav_badges.prop', 'nav_badges');
});

it('counts approval badges in SQL without hydrating review models', function () {
    $user = sharedPropsUser('approver');
    News::factory()->count(5)->create([
        'status' => 'review',
    ]);

    DB::flushQueryLog();
    DB::enableQueryLog();
    $badges = NavBadges::for($user);
    $newsQueries = collect(DB::getQueryLog())
        ->pluck('query')
        ->filter(fn (string $query): bool => preg_match('/from [`"]news[`"]/', $query) === 1)
        ->values();
    DB::disableQueryLog();

    expect($badges['approval'])->toBe(5)
        ->and($newsQueries)->toHaveCount(1)
        ->and($newsQueries->first())->toContain('count(*)')
        ->and($newsQueries->first())->not->toContain('select *');
});
