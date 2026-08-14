<?php

use App\Jobs\RevalidateFrontend;
use App\Models\MenuItem;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Queue;

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
    $parent = MenuItem::query()->create([
        'location' => 'main',
        'label' => ['ru' => 'Раздел', 'tg' => 'Бахш'],
        'url' => '/section',
        'enabled' => true,
        'sort' => 0,
    ]);
    MenuItem::query()->create([
        'location' => 'main',
        'label' => ['ru' => 'Дочерний пункт', 'tg' => 'Зербанд'],
        'url' => '/section/child',
        'parent_id' => $parent->id,
        'enabled' => true,
        'sort' => 0,
    ]);

    actingAs(menuUser('admin'))
        ->get('/menu')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('menu/index')
            ->has('menus.main', 1)
            ->where('menus.main.0.label.ru', 'Раздел')
            ->has('menus.main.0.children', 1)
            ->where('menus.main.0.children.0.url', '/section/child'));
});

it('forbids a role without settings access from the menu manager', function () {
    actingAs(menuUser('editor'))->get('/menu')->assertForbidden();
});

it('syncs a menu location: creates new items and removes omitted ones', function () {
    Queue::fake();

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

    Queue::assertPushed(
        RevalidateFrontend::class,
        fn (RevalidateFrontend $job): bool => $job->type === 'shell'
            && $job->tags() === ['cms:shell:ru', 'cms:shell:tj', 'cms:shell:en']
            && $job->afterCommit === true,
    );
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

it('saves a one-level tree and exposes it on the public menu', function () {
    Queue::fake();

    actingAs(menuUser('admin'))->put('/menu', [
        'items' => [
            'main' => [
                [
                    'id' => null,
                    'label' => ['ru' => 'О комитете', 'tg' => 'Дар бораи кумита', 'en' => 'About'],
                    'url' => '',
                    'enabled' => true,
                    'children' => [
                        [
                            'id' => null,
                            'label' => ['ru' => 'Руководство', 'tg' => 'Роҳбарӣ', 'en' => 'Leadership'],
                            'url' => '/leadership',
                            'enabled' => true,
                        ],
                    ],
                ],
            ],
            'footer' => [],
        ],
    ])->assertRedirect();

    $parent = MenuItem::query()->where('location', 'main')->whereNull('parent_id')->first();

    expect($parent)->not->toBeNull()
        ->and($parent->url)->toBeNull()
        ->and($parent->children)->toHaveCount(1)
        ->and($parent->children->first()?->url)->toBe('/leadership');

    $public = $this->getJson('/api/v1/menu?locale=ru')->assertOk()->json('data.main');

    expect($public)->toHaveCount(1)
        ->and($public[0]['label'])->toBe('О комитете')
        ->and($public[0]['url'])->toBeNull()
        ->and($public[0]['children'])->toHaveCount(1)
        ->and($public[0]['children'][0]['url'])->toBe('/leadership');
});

it('deletes omitted children and then the parent', function () {
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

    expect(MenuItem::query()->find($child->id))->toBeNull()
        ->and(MenuItem::query()->find($parent->id))->toBeNull();
});

it('rejects a third nesting level', function () {
    actingAs(menuUser('admin'))->put('/menu', [
        'items' => [
            'main' => [
                [
                    'id' => null,
                    'label' => ['ru' => 'Раздел', 'tg' => 'Бахш', 'en' => 'Section'],
                    'url' => '/section',
                    'enabled' => true,
                    'children' => [
                        [
                            'id' => null,
                            'label' => ['ru' => 'Дочерний', 'tg' => 'Фарзанд', 'en' => 'Child'],
                            'url' => '/section/child',
                            'enabled' => true,
                            'children' => [
                                [
                                    'id' => null,
                                    'label' => ['ru' => 'Внук', 'tg' => 'Набера', 'en' => 'Grand'],
                                    'url' => '/section/grand',
                                    'enabled' => true,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'footer' => [],
        ],
    ])->assertSessionHasErrors('items.main.0.children.0.children');
});

it('rejects the same menu item id twice in one location', function () {
    $item = MenuItem::query()->create([
        'location' => 'main',
        'label' => ['ru' => 'Новости', 'tg' => 'Хабарҳо'],
        'url' => '/news',
        'enabled' => true,
        'sort' => 0,
    ]);

    actingAs(menuUser('admin'))->put('/menu', [
        'items' => [
            'main' => [
                [
                    'id' => $item->id,
                    'label' => ['ru' => 'Новости', 'tg' => 'Хабарҳо', 'en' => 'News'],
                    'url' => '/news',
                    'enabled' => true,
                    'children' => [
                        [
                            'id' => $item->id,
                            'label' => ['ru' => 'Ещё раз', 'tg' => 'Бори дигар', 'en' => 'Again'],
                            'url' => '/news/again',
                            'enabled' => true,
                        ],
                    ],
                ],
            ],
            'footer' => [],
        ],
    ])->assertSessionHasErrors('items.main.0.children.0.id');
});

it('does not rewrite a footer item when its id is submitted in the main menu', function () {
    $footer = MenuItem::query()->create([
        'location' => 'footer',
        'label' => ['ru' => 'Контакты', 'tg' => 'Тамос'],
        'url' => '/contacts',
        'enabled' => true,
        'sort' => 0,
    ]);

    actingAs(menuUser('admin'))->put('/menu', [
        'items' => [
            'main' => [
                [
                    'id' => $footer->id,
                    'label' => ['ru' => 'Новости', 'tg' => 'Хабарҳо', 'en' => 'News'],
                    'url' => '/news',
                    'enabled' => true,
                    'children' => [],
                ],
            ],
            'footer' => [
                [
                    'id' => $footer->id,
                    'label' => ['ru' => 'Контакты', 'tg' => 'Тамос', 'en' => 'Contacts'],
                    'url' => '/contacts',
                    'enabled' => true,
                    'children' => [],
                ],
            ],
        ],
    ])->assertRedirect();

    expect($footer->fresh())
        ->location->toBe('footer')
        ->url->toBe('/contacts')
        ->and(MenuItem::query()->where('location', 'main')->where('url', '/news')->exists())->toBeTrue();
});

it('refuses at the DB level to delete a menu item that still has children', function () {
    // D-6: no code path deletes a parent with children today —
    // MenuController::update() already excludes such items from its own
    // deletion set (see the sibling "preserves nested items" test above).
    // This exercises the new `restrictOnDelete()` FK directly, as a
    // backstop for any future delete path (single-item destroy route,
    // tinker, a seeder) that doesn't reimplement that same exclusion.
    $parent = MenuItem::query()->create([
        'location' => 'main',
        'label' => ['ru' => 'Раздел'],
        'url' => '/section',
        'enabled' => true,
        'sort' => 0,
    ]);
    MenuItem::query()->create([
        'location' => 'main',
        'label' => ['ru' => 'Дочерний пункт'],
        'url' => '/section/child',
        'parent_id' => $parent->id,
        'enabled' => true,
        'sort' => 0,
    ]);

    expect(fn () => $parent->delete())->toThrow(QueryException::class);
    expect(MenuItem::query()->find($parent->id))->not->toBeNull();
});

it('forbids a non-admin from saving the menu', function () {
    actingAs(menuUser('chief_editor'))->put('/menu', [
        'items' => ['main' => [], 'footer' => []],
    ])->assertForbidden();
});
