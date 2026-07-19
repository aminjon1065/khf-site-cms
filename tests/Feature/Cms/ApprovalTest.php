<?php

use App\Enums\ContentStatus;
use App\Models\Alert;
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
