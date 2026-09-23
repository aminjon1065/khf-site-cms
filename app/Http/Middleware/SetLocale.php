<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The staff interface is Russian-only (owner decision, 2026-09-23): content is
 * still written in ru/tg/en, but labels, dates and validation messages of the
 * admin are always Russian, whatever APP_LOCALE says.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        app()->setLocale('ru');

        return $next($request);
    }
}
