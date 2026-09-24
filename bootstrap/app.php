<?php

use App\Http\Middleware\EnsureEditorialVersion;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\PublicApiResponse;
use App\Http\Middleware\RequireTwoFactor;
use App\Http\Middleware\ResolveApiLocale;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use App\Support\UploadLimits;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Middleware\ValidatePostSize;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        // Global, not per-stack: middleware of the `web`/`api` groups does not
        // run for a request that matched no route at all, and a 404 page is
        // exactly the kind of response an attacker gets to shape.
        $middleware->append(SecurityHeaders::class);

        // A save heavier than php.ini `post_max_size` is answered with a
        // form error (see withExceptions), which needs the session: the check
        // runs inside the groups, after the session starts, not before them.
        $middleware->remove(ValidatePostSize::class);

        $middleware->web(append: [
            HandleAppearance::class,
            SetLocale::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            ValidatePostSize::class,
        ]);

        // Public read-only API: stateless, no session/CSRF, locale resolved per request.
        $middleware->api(prepend: [
            ResolveApiLocale::class,
        ]);
        $middleware->api(append: [
            PublicApiResponse::class,
            ValidatePostSize::class,
        ]);

        $middleware->alias([
            '2fa.required' => RequireTwoFactor::class,
            'editorial.version' => EnsureEditorialVersion::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // A save heavier than php.ini `post_max_size` arrives empty. Instead
        // of an error page, the editor stays with a form error: the typed
        // text is kept in the browser and can be saved with fewer files.
        $exceptions->render(function (PostTooLargeException $exception, Request $request) {
            if ($request->expectsJson()) {
                return null;
            }

            $limit = UploadLimits::requestLimitMegabytes();

            return back()->withErrors([
                'upload' => 'Файлы слишком большие для одного сохранения'
                    .($limit !== null ? " (сервер принимает до {$limit} МБ за раз)" : '')
                    .'. Сохраните материал с частью файлов, затем добавьте остальные.',
            ]);
        });
    })->create();
