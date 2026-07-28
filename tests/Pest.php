<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

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
