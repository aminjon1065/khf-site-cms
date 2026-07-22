<?php

use App\Models\MenuItem;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;

beforeEach(function () {
    seed(RolePermissionSeeder::class);
});

function menuUser(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

it('lets an admin open the menu manager', function () {
    actingAs(menuUser('admin'))->get('/menu')->assertOk();
});

it('forbids a role without settings access from the menu manager', function () {
    actingAs(menuUser('editor'))->get('/menu')->assertForbidden();
});

it('syncs a menu location: creates new items and removes omitted ones', function () {
    $stale = MenuItem::query()->create([
        'location' => 'main',
        'label' => ['ru' => 'Старый пункт'],
        'url' => '/old',
        'enabled' => true,
        'sort' => 0,
    ]);

    actingAs(menuUser('admin'))->put('/menu', [
        'items' => [
            'main' => [
                ['id' => null, 'label' => ['ru' => 'Новости', 'tg' => 'Хабарҳо', 'en' => 'News'], 'url' => '/news', 'enabled' => true],
            ],
            'footer' => [],
        ],
    ])->assertRedirect();

    expect(MenuItem::query()->find($stale->id))->toBeNull()
        ->and(MenuItem::query()->where('location', 'main')->where('url', '/news')->exists())->toBeTrue();
});

it('drops rows without a Russian label', function () {
    actingAs(menuUser('admin'))->put('/menu', [
        'items' => [
            'main' => [
                ['id' => null, 'label' => ['ru' => '', 'en' => 'Orphan'], 'url' => '/orphan', 'enabled' => true],
            ],
            'footer' => [],
        ],
    ])->assertRedirect();

    expect(MenuItem::query()->where('url', '/orphan')->exists())->toBeFalse();
});

it('rejects unsafe public menu URLs', function () {
    actingAs(menuUser('admin'))->put('/menu', [
        'items' => [
            'main' => [
                ['id' => null, 'label' => ['ru' => 'Опасно', 'tg' => 'Хатарнок', 'en' => 'Unsafe'], 'url' => 'javascript:alert(1)', 'enabled' => true],
            ],
            'footer' => [],
        ],
    ])->assertSessionHasErrors('items.main.0.url');

    expect(MenuItem::query()->where('url', 'javascript:alert(1)')->exists())->toBeFalse();
});

it('preserves nested items that are not represented by the root-only editor', function () {
    $parent = MenuItem::query()->create([
        'location' => 'main',
        'label' => ['ru' => 'Раздел'],
        'url' => '/section',
        'enabled' => true,
        'sort' => 0,
    ]);
    $child = MenuItem::query()->create([
        'location' => 'main',
        'label' => ['ru' => 'Дочерний пункт'],
        'url' => '/section/child',
        'parent_id' => $parent->id,
        'enabled' => true,
        'sort' => 0,
    ]);

    actingAs(menuUser('admin'))->put('/menu', [
        'items' => ['main' => [], 'footer' => []],
    ])->assertRedirect();

    expect($parent->fresh())->not->toBeNull()
        ->and($child->fresh())->not->toBeNull()
        ->and($child->fresh()->parent_id)->toBe($parent->id);
});

it('forbids a non-admin from saving the menu', function () {
    actingAs(menuUser('chief_editor'))->put('/menu', [
        'items' => ['main' => [], 'footer' => []],
    ])->assertForbidden();
});
