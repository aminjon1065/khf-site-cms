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
    giveRole($user, $role);

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

it('does not offer blocks the public site never shows', function () {
    actingAs(hbUser('chief_editor'))->get('/home-blocks')
        ->assertInertia(fn ($page) => $page->where(
            'blocks',
            fn ($blocks): bool => collect($blocks)->pluck('type')->doesntContain('emergency_contacts')
                && collect($blocks)->firstWhere('type', 'latest_news')['max_limit'] === HomeBlock::MAX_ITEMS['latest_news'],
        ));
});

it('seeds every block within what its section on the site shows', function () {
    HomeBlock::query()->whereIn('type', array_keys(HomeBlock::MAX_ITEMS))->get()
        ->each(fn (HomeBlock $block) => expect($block->config['limit'])->toBeLessThanOrEqual(HomeBlock::MAX_ITEMS[$block->type]));

    expect(HomeBlock::query()->where('type', 'active_alerts')->sole()->getTranslations('title'))
        ->toBe(['tg' => 'Огоҳиҳо', 'ru' => 'Предупреждения', 'en' => 'Warnings']);
});

it('fits stored blocks to the site layout and keeps titles editors wrote', function () {
    HomeBlock::query()->delete();
    $alerts = HomeBlock::query()->create([
        'type' => 'active_alerts', 'sort' => 0, 'enabled' => true, 'config' => ['limit' => 6],
        'title' => ['tg' => 'Хулосаи оперативӣ', 'ru' => 'Оперативная сводка', 'en' => 'Current warnings'],
    ]);
    $news = HomeBlock::query()->create([
        'type' => 'latest_news', 'sort' => 1, 'enabled' => true, 'config' => ['limit' => 4],
        'title' => ['ru' => 'Новости ведомства'],
    ]);

    (require database_path('migrations/2026_09_23_182355_fit_home_blocks_to_site_layout.php'))->up();

    expect($alerts->fresh()->config['limit'])->toBe(3)
        ->and($alerts->fresh()->getTranslations('title'))->toBe(['tg' => 'Огоҳиҳо', 'ru' => 'Предупреждения', 'en' => 'Current warnings'])
        ->and($news->fresh()->config['limit'])->toBe(4)
        ->and($news->fresh()->getTranslations('title'))->toBe(['ru' => 'Новости ведомства']);
});

it('refuses more items than a block shows on the site', function () {
    $news = HomeBlock::query()->where('type', 'latest_news')->sole();

    actingAs(hbUser('chief_editor'))->put('/home-blocks', ['blocks' => [[
        'id' => $news->id,
        'enabled' => true,
        'title' => $news->getTranslations('title'),
        'limit' => HomeBlock::MAX_ITEMS['latest_news'] + 1,
    ]]])->assertSessionHasErrors('blocks.0.limit');
});
