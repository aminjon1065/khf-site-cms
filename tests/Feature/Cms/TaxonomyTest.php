<?php

use App\Models\Category;
use App\Models\News;
use App\Models\Tag;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;

beforeEach(function () {
    seed(RolePermissionSeeder::class);
});

function taxUser(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

function newsCategory(string $ru, string $slug): Category
{
    return Category::create(['type' => 'news', 'name' => ['ru' => $ru], 'slug' => $slug, 'sort' => 0]);
}

it('lets an editor open the taxonomy manager', function () {
    actingAs(taxUser('editor'))->get('/taxonomy')->assertOk();
});

it('forbids a user without any role', function () {
    actingAs(User::factory()->create())->get('/taxonomy')->assertForbidden();
});

it('forbids a viewer from saving taxonomy', function () {
    actingAs(taxUser('viewer'))->put('/taxonomy', [
        'categories' => [],
        'tags' => [],
    ])->assertForbidden();
});

it('creates categories and tags with auto-generated slugs', function () {
    actingAs(taxUser('editor'))->put('/taxonomy', [
        'categories' => [
            ['id' => null, 'name' => ['ru' => 'Спасательные операции'], 'slug' => ''],
        ],
        'tags' => [
            ['id' => null, 'name' => ['ru' => 'учения'], 'slug' => ''],
        ],
    ])->assertRedirect();

    $category = Category::query()->where('type', 'news')->first();
    $tag = Tag::query()->first();

    expect($category)->not->toBeNull()
        ->and($category->slug)->toMatch('/^[a-z0-9-]+$/')
        ->and($category->getTranslation('name', 'ru'))->toBe('Спасательные операции')
        ->and($tag)->not->toBeNull()
        ->and($tag->slug)->toMatch('/^[a-z0-9-]+$/');
});

it('updates an existing category', function () {
    $cat = newsCategory('Старое', 'staroe');

    actingAs(taxUser('editor'))->put('/taxonomy', [
        'categories' => [
            ['id' => $cat->id, 'name' => ['ru' => 'Новое имя'], 'slug' => 'staroe'],
        ],
        'tags' => [],
    ])->assertRedirect();

    expect($cat->refresh()->getTranslation('name', 'ru'))->toBe('Новое имя')
        ->and(Category::query()->where('type', 'news')->count())->toBe(1);
});

it('rejects a category named only in a non-Russian language', function () {
    actingAs(taxUser('editor'))->put('/taxonomy', [
        'categories' => [
            ['id' => null, 'name' => ['tg' => 'Танҳо тоҷикӣ'], 'slug' => ''],
        ],
        'tags' => [],
    ])->assertSessionHasErrors('categories.0.name.ru');

    expect(Category::query()->where('type', 'news')->count())->toBe(0);
});

it('ignores a fully empty term row', function () {
    actingAs(taxUser('editor'))->put('/taxonomy', [
        'categories' => [
            ['id' => null, 'name' => ['ru' => '', 'tg' => '', 'en' => ''], 'slug' => ''],
        ],
        'tags' => [],
    ])->assertRedirect();

    expect(Category::query()->where('type', 'news')->count())->toBe(0);
});

it('clears an existing non-Russian translation instead of retaining it', function () {
    $cat = Category::create([
        'type' => 'news',
        'name' => ['ru' => 'Спорт', 'en' => 'Sport'],
        'slug' => 'sport',
        'sort' => 0,
    ]);

    actingAs(taxUser('editor'))->put('/taxonomy', [
        'categories' => [
            ['id' => $cat->id, 'name' => ['ru' => 'Спорт', 'en' => ''], 'slug' => 'sport'],
        ],
        'tags' => [],
    ])->assertRedirect();

    $names = $cat->refresh()->getTranslations('name');

    expect($names)->toHaveKey('ru')
        ->and($names)->not->toHaveKey('en');
});

it('detaches a removed category from its news (FK nullOnDelete)', function () {
    $cat = newsCategory('Категория', 'kategoriya');
    $news = News::factory()->create(['category_id' => $cat->id]);

    actingAs(taxUser('editor'))->put('/taxonomy', [
        'categories' => [],
        'tags' => [],
    ])->assertRedirect();

    expect(Category::query()->find($cat->id))->toBeNull()
        ->and($news->refresh()->category_id)->toBeNull();
});

it('removes a tag and cascades its assignments', function () {
    $tag = Tag::create(['name' => ['ru' => 'сель'], 'slug' => 'sel']);
    $news = News::factory()->create();
    $news->tags()->attach($tag->id);

    actingAs(taxUser('editor'))->put('/taxonomy', [
        'categories' => [],
        'tags' => [],
    ])->assertRedirect();

    expect(Tag::query()->find($tag->id))->toBeNull()
        ->and($news->refresh()->tags()->count())->toBe(0);
});

it('suffixes a duplicate category slug within the type', function () {
    actingAs(taxUser('editor'))->put('/taxonomy', [
        'categories' => [
            ['id' => null, 'name' => ['ru' => 'Первая'], 'slug' => 'dup'],
            ['id' => null, 'name' => ['ru' => 'Вторая'], 'slug' => 'dup'],
        ],
        'tags' => [],
    ])->assertRedirect();

    $slugs = Category::query()->where('type', 'news')->pluck('slug');

    expect($slugs)->toContain('dup')->toContain('dup-2')
        ->and($slugs->duplicates())->toBeEmpty();
});
