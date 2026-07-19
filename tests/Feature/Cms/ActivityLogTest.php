<?php

use App\Models\Activity;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;

beforeEach(function () {
    seed(RolePermissionSeeder::class);
});

function auditor(): User
{
    $user = User::factory()->create();
    $user->assignRole('admin');

    return $user;
}

function makeActivity(bool $critical): void
{
    $activity = new Activity;
    $activity->forceFill([
        'log_name' => 'alerts',
        'description' => $critical ? 'Опубликовал предупреждение' : 'Создал черновик',
        'event' => 'workflow',
        'is_critical' => $critical,
        'ip_address' => '10.0.0.1',
        'properties' => ['attributes' => ['status' => 'published'], 'old' => ['status' => 'approved']],
    ]);
    $activity->save();
}

it('renders the activity log for a privileged user', function () {
    makeActivity(false);

    actingAs(auditor())->get('/activity')->assertOk();
});

it('filters to critical actions only', function () {
    makeActivity(true);
    makeActivity(false);

    actingAs(auditor())->get('/activity?critical=1&period=365')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('activities', 1));
});

it('exports the activity log as a CSV download', function () {
    makeActivity(true);

    $response = actingAs(auditor())->get('/activity/export?period=365');

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('text/csv');
});

it('forbids an editor from viewing the activity log', function () {
    $editor = User::factory()->create();
    $editor->assignRole('editor');

    actingAs($editor)->get('/activity')->assertForbidden();
});
