<?php

use App\Models\HomeBlock;
use App\Models\User;
use Database\Seeders\HomeBlockSeeder;
use Database\Seeders\RolePermissionSeeder;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;

beforeEach(function () {
    seed([RolePermissionSeeder::class, HomeBlockSeeder::class]);
});

function hbUser(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

it('lets a chief editor open the home manager', function () {
    actingAs(hbUser('chief_editor'))->get('/home-blocks')->assertOk();
});

it('forbids a role without home access from opening the manager', function () {
    // translator has no home permission at all.
    actingAs(hbUser('translator'))->get('/home-blocks')->assertForbidden();
});

it('saves block order, visibility and limits', function () {
    $blocks = HomeBlock::query()->orderBy('sort')->get();

    // Move latest_news to the front, disable documents, set news limit to 4.
    $ordered = $blocks
        ->sortBy(fn (HomeBlock $b): int => $b->type === 'latest_news' ? -1 : $b->sort)
        ->values();

    $payload = ['blocks' => $ordered->map(fn (HomeBlock $b): array => [
        'id' => $b->id,
        'enabled' => $b->type !== 'documents',
        'title' => $b->getTranslations('title'),
        'limit' => $b->type === 'latest_news' ? 4 : null,
    ])->all()];

    actingAs(hbUser('chief_editor'))->put('/home-blocks', $payload)->assertRedirect();

    $news = HomeBlock::query()->where('type', 'latest_news')->first();
    $documents = HomeBlock::query()->where('type', 'documents')->first();

    expect($news->sort)->toBe(0)
        ->and($news->config['limit'])->toBe(4)
        ->and($documents->enabled)->toBeFalse();
});

it('forbids an editor (view-only home access) from saving', function () {
    actingAs(hbUser('editor'))->put('/home-blocks', ['blocks' => []])->assertForbidden();
});
