<?php

use App\Enums\RegionType;
use App\Jobs\RevalidateFrontend;
use App\Models\HomeBlock;
use App\Models\Leader;
use App\Models\Region;
use App\Models\StructureUnit;
use App\Models\User;
use App\Support\FrontendRevalidation;
use Database\Seeders\HomeBlockSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;

beforeEach(function () {
    seed(RolePermissionSeeder::class);
    Queue::fake();
});

function referenceAdmin(): User
{
    $user = User::factory()->create();
    $user->assignRole('admin');

    return $user;
}

/**
 * The public site caches reference data under one tag per locale; a change
 * in the CMS must refresh exactly those tags (contract with
 * khf-site-front/lib/cache-tags.ts).
 */
function assertSiteRefreshed(string $type, string $resource): void
{
    Queue::assertPushed(
        RevalidateFrontend::class,
        fn (RevalidateFrontend $job): bool => $job->type === $type
            && $job->tags() === ["cms:{$resource}:ru", "cms:{$resource}:tj", "cms:{$resource}:en"],
    );
}

it('builds one tag per site locale for reference data', function (string $type, string $resource) {
    expect(FrontendRevalidation::forReference($type)['tags'])
        ->toBe(["cms:{$resource}:ru", "cms:{$resource}:tj", "cms:{$resource}:en"]);
})->with([
    ['home', 'home'],
    ['leadership', 'leadership'],
    ['structure', 'structure'],
    ['region', 'regions'],
    ['category', 'categories'],
]);

it('refreshes the home page when its blocks change', function () {
    seed(HomeBlockSeeder::class);
    $payload = ['blocks' => HomeBlock::query()->orderBy('sort')->get()->map(fn (HomeBlock $b): array => [
        'id' => $b->id,
        'enabled' => true,
        'title' => $b->getTranslations('title'),
        'limit' => null,
    ])->all()];

    actingAs(referenceAdmin())->put('/home-blocks', $payload)->assertRedirect();

    assertSiteRefreshed('home', 'home');
});

it('refreshes the leadership page when the roster changes', function () {
    $leader = Leader::factory()->create();

    actingAs(referenceAdmin())->delete("/leadership/{$leader->id}")->assertRedirect('/leadership');

    assertSiteRefreshed('leadership', 'leadership');
});

it('refreshes the structure page when a unit changes', function () {
    $unit = StructureUnit::factory()->create();

    actingAs(referenceAdmin())->delete("/structure/{$unit->id}")->assertRedirect('/structure');

    assertSiteRefreshed('structure', 'structure');
});

it('refreshes region data when a region changes', function () {
    $region = Region::query()->create([
        'name' => ['ru' => 'Тестовый регион', 'tg' => 'Минтақаи санҷишӣ', 'en' => 'Test region'],
        'code' => 'reference-test',
        'type' => RegionType::Oblast,
        'districts_count' => 0,
        'sort' => 99,
    ]);

    actingAs(referenceAdmin())->delete("/regions/{$region->id}")->assertRedirect('/regions');

    assertSiteRefreshed('region', 'regions');
});

it('refreshes category data when news categories change', function () {
    actingAs(referenceAdmin())->put('/taxonomy', [
        'categories' => [['id' => null, 'name' => ['ru' => 'Учения'], 'slug' => '']],
        'tags' => [],
    ])->assertRedirect();

    assertSiteRefreshed('category', 'categories');
});
