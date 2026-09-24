<?php

use App\Jobs\PerformMediaConversions;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\MediaLibrary\Conversions\ConversionCollection;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

// Тесты медиаконверсий (`MediaConversionQueueTest`, `MediaTest`) реально
// пересобирают изображения через GD и spatie/image-optimizer. При дефолтных
// 128 МБ прогон падает Fatal error по памяти — причём НЕ детерминированно:
// хватит её или нет, зависит от порядка тестов и фрагментации, поэтому
// падение выглядит как «случайно красный CI». Поднимаем лимит для тестового
// процесса (`php artisan test` запускает Pest отдельным процессом, так что
// флаг `-d memory_limit=` из командной строки до него не доходит).
if ((int) ini_get('memory_limit') !== -1) {
    ini_set('memory_limit', '512M');
}

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

// Public API tests use the canonical locale unless a test explicitly selects
// another one. This prevents the host process language from changing fixtures.
beforeEach(function () {
    $this->withHeader('Accept-Language', 'ru');
})->in('Feature/Api');

// P0-1: no Feature test may reach the real network. `preventStrayRequests()`
// makes any request a test does NOT explicitly fake (via its own
// `Http::fake([...])`) throw instead of silently escaping to a real host —
// this is what let `RevalidateFrontend` hit a real localhost port during
// `php artisan test` when the developer's `.env` happened to have a
// revalidation URL configured. `phpunit.xml` additionally forces
// `FRONTEND_REVALIDATION_URL` empty, so the job short-circuits before any
// request in tests that don't care about the webhook.
//
// Намеренно БЕЗ общего `Http::fake()`: catch-all stub перехватывал бы всё
// первым и возвращал 200, из-за чего тесты, проверяющие 401/500/обрыв
// соединения и сам StrayRequestException, переставали видеть свои ошибки.
// Пусть незамоканный запрос падает громко — это и есть смысл P0-1.
// Scoped to `Feature` (via `pest()->...->in()`, not the bare `beforeEach()`
// function, which silently never runs unless the file already falls under
// an existing `->in()`/`->extend()` scope): `tests/Unit` intentionally uses
// plain PHPUnit test cases with no Laravel app booted, so the `Http` facade
// root does not exist there.
pest()->beforeEach(function () {
    Http::preventStrayRequests();
})->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Roles an administrator builds on the «Роли и права» screen, for tests about
 * rights the three built-in roles don't combine — someone who only edits,
 * only approves, only looks. These are the former fixed roles of the CMS:
 * installations that had people in them keep them as roles of their own.
 * The limit to a region is not part of any role — it is set on the account
 * (`limited_to_region`).
 *
 * @return array<string, list<string>>
 */
function customTestRoles(): array
{
    $grants = [
        'translator' => [
            'taxonomy' => 'view,edit', 'alerts' => 'view,edit', 'news' => 'view,edit',
            'projects' => 'view,edit', 'announcements' => 'view,edit', 'pages' => 'view,edit',
            'instructions' => 'view,edit', 'documents' => 'view', 'media' => 'view',
        ],
        'regional_editor' => [
            'taxonomy' => 'view,create,edit', 'alerts' => 'view,create,edit', 'news' => 'view,create,edit',
            'projects' => 'view,create,edit', 'announcements' => 'view,create,edit', 'pages' => 'view,create,edit',
            'instructions' => 'view', 'documents' => 'view,create', 'media' => 'view,create',
        ],
        'alert_operator' => [
            'taxonomy' => 'view', 'alerts' => 'view,create,edit,delete,publish,approve', 'news' => 'view',
            'projects' => 'view', 'announcements' => 'view', 'pages' => 'view', 'instructions' => 'view,edit',
            'documents' => 'view', 'media' => 'view,create', 'home' => 'view', 'regions' => 'view',
        ],
        'approver' => [
            'taxonomy' => 'view', 'alerts' => 'view,publish,approve', 'news' => 'view,publish,approve',
            'projects' => 'view,publish,approve', 'announcements' => 'view,publish,approve',
            'pages' => 'view,publish,approve', 'instructions' => 'view,publish,approve',
            'documents' => 'view,approve', 'media' => 'view', 'home' => 'view,approve', 'submissions' => 'view,edit',
        ],
        'viewer' => [
            'taxonomy' => 'view', 'alerts' => 'view', 'news' => 'view', 'projects' => 'view',
            'announcements' => 'view', 'pages' => 'view', 'instructions' => 'view', 'documents' => 'view',
            'media' => 'view', 'home' => 'view', 'submissions' => 'view',
        ],
    ];

    return array_map(
        fn (array $modules): array => array_merge(...array_map(
            fn (string $module, string $actions): array => array_map(
                fn (string $action): string => "{$module}.{$action}",
                explode(',', $actions),
            ),
            array_keys($modules),
            $modules,
        )),
        $grants,
    );
}

/**
 * A role with exactly these rights, as an administrator would build it.
 *
 * @param  list<string>  $permissions
 */
function customRole(string $name, array $permissions): string
{
    Role::findOrCreate($name, 'web')->syncPermissions($permissions);

    return $name;
}

/**
 * Give a test user a role: a built-in one, or one of customTestRoles().
 */
function giveRole(User $user, string $role): User
{
    $custom = customTestRoles();

    if (array_key_exists($role, $custom)) {
        customRole($role, $custom[$role]);
    }

    $user->assignRole($role);

    return $user;
}

/**
 * Задание конверсий для уже загруженного media — ровно то, что кладёт в
 * очередь media-library при добавлении файла. Живёт здесь, а не в файле
 * теста: `MediaAuditCommandTest` и `MediaConversionQueueTest` оба им
 * пользуются, а при `php artisan test --parallel` файлы попадают в разные
 * процессы, и тест, где функция объявлена, может просто не загрузиться —
 * второй падал с `Call to undefined function`.
 */
function mediaConversionJob(Media $media): PerformMediaConversions
{
    return new PerformMediaConversions(
        ConversionCollection::createForMedia($media),
        $media,
    );
}
