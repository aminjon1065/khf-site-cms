<?php

use App\Enums\ContentStatus;
use App\Enums\RegionType;
use App\Models\Document;
use App\Models\News;
use App\Models\Page;
use App\Models\Region;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;

beforeEach(function () {
    seed(RolePermissionSeeder::class);
});

function translationQueueUser(string $role, array $attributes = []): User
{
    $user = User::factory()->create($attributes);
    $user->assignRole($role);

    return $user;
}

/**
 * @return array<string, string>
 */
function completeTranslations(string $value): array
{
    return ['tg' => $value, 'ru' => $value, 'en' => $value];
}

it('lists only incomplete materials the translator is allowed to edit', function () {
    $translator = translationQueueUser('translator');
    $news = News::factory()->create([
        'title' => completeTranslations('Новость'),
        'summary' => completeTranslations('Кратко'),
        'body' => ['tg' => 'Матн', 'ru' => 'Текст', 'en' => ''],
        'seo' => [
            'tg' => ['title' => 'SEO', 'description' => 'SEO'],
            'ru' => ['title' => 'SEO', 'description' => 'SEO'],
            'en' => ['title' => 'SEO', 'description' => 'SEO'],
        ],
    ]);
    Page::factory()->create([
        'title' => completeTranslations('Полная страница'),
        'body' => completeTranslations('Текст'),
        'seo_title' => completeTranslations('SEO'),
        'seo_description' => completeTranslations('SEO'),
    ]);
    Document::factory()->create([
        'name' => ['tg' => 'Санад', 'ru' => 'Документ', 'en' => ''],
    ]);

    actingAs($translator)
        ->get('/editorial/translations')
        ->assertOk()
        ->assertInertia(fn ($inertia) => $inertia
            ->component('editorial/translations')
            ->has('items', 1)
            ->where('items.0.id', $news->id)
            ->where('items.0.type', 'news')
            ->where('items.0.missing_locales', ['en'])
            ->where('items.0.edit_url', "/news/{$news->id}/edit")
            ->where('meta.total', 1)
            ->where('types', fn ($types) => collect($types)->pluck('value')->contains('documents') === false),
        );
});

it('filters by the locale that still needs translation', function () {
    $translator = translationQueueUser('translator');
    $englishMissing = News::factory()->create([
        'title' => completeTranslations('English missing'),
        'summary' => completeTranslations('Summary'),
        'body' => ['tg' => 'Матн', 'ru' => 'Текст', 'en' => ''],
        'seo' => [
            'tg' => ['title' => 'SEO', 'description' => 'SEO'],
            'ru' => ['title' => 'SEO', 'description' => 'SEO'],
            'en' => ['title' => 'SEO', 'description' => 'SEO'],
        ],
    ]);
    News::factory()->create([
        'title' => completeTranslations('Tajik missing'),
        'summary' => completeTranslations('Summary'),
        'body' => ['tg' => '', 'ru' => 'Текст', 'en' => 'Text'],
        'seo' => [
            'tg' => ['title' => 'SEO', 'description' => 'SEO'],
            'ru' => ['title' => 'SEO', 'description' => 'SEO'],
            'en' => ['title' => 'SEO', 'description' => 'SEO'],
        ],
    ]);

    actingAs($translator)
        ->get('/editorial/translations?type=news&locale=en')
        ->assertOk()
        ->assertInertia(fn ($inertia) => $inertia
            ->has('items', 1)
            ->where('items.0.id', $englishMissing->id)
            ->where('filters.type', 'news')
            ->where('filters.locale', 'en'),
        );
});

it('prioritizes translation check over older workflow states', function () {
    $editor = translationQueueUser('editor');
    $draft = News::factory()->create([
        'updated_at' => now()->subDay(),
    ]);
    $translationCheck = News::factory()->create([
        'status' => ContentStatus::TranslationCheck,
        'updated_at' => now(),
    ]);

    actingAs($editor)
        ->get('/editorial/translations?type=news')
        ->assertOk()
        ->assertInertia(fn ($inertia) => $inertia
            ->has('items', 2)
            ->where('items.0.id', $translationCheck->id)
            ->where('items.1.id', $draft->id),
        );
});

it('forbids users without any editorial edit permission', function () {
    actingAs(translationQueueUser('viewer'))
        ->get('/editorial/translations')
        ->assertForbidden();
});

it('limits regional editors to their own incomplete materials', function () {
    $region = Region::query()->create([
        'name' => completeTranslations('Регион'),
        'code' => 'translation-queue-region',
        'type' => RegionType::Oblast,
        'districts_count' => 1,
        'sort' => 1,
    ]);
    $regionalEditor = translationQueueUser('regional_editor', [
        'region_id' => $region->id,
    ]);
    $otherEditor = translationQueueUser('editor');
    $ownNews = News::factory()->create(['author_id' => $regionalEditor->id]);
    News::factory()->create(['author_id' => $otherEditor->id]);

    actingAs($regionalEditor)
        ->get('/editorial/translations?type=news')
        ->assertOk()
        ->assertInertia(fn ($inertia) => $inertia
            ->has('items', 1)
            ->where('items.0.id', $ownNews->id),
        );
});
