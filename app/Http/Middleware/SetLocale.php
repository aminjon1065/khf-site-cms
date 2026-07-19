<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var string $locale */
        $locale = $request->session()->get('locale', config('app.locale', 'ru'));

        if (in_array($locale, ['ru', 'tg', 'en'], true)) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
