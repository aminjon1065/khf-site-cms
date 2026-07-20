<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the content locale for the public API from the `?locale=` query
 * parameter, falling back to the `Accept-Language` header, then to the
 * canonical `ru`. Only the three supported content locales are honoured; any
 * other value is coerced to `ru`. Unlike the admin {@see SetLocale}, the API
 * must be able to serve all three locales (including `en`).
 */
class ResolveApiLocale
{
    /**
     * @var list<string>
     */
    public const SUPPORTED = ['tg', 'ru', 'en'];

    public const DEFAULT = 'ru';

    public function handle(Request $request, Closure $next): Response
    {
        app()->setLocale($this->resolve($request));

        return $next($request);
    }

    private function resolve(Request $request): string
    {
        $requested = $request->query('locale');

        if (is_string($requested) && in_array($requested, self::SUPPORTED, true)) {
            return $requested;
        }

        if (! $request->hasHeader('Accept-Language')) {
            return self::DEFAULT;
        }

        // `ru` first so the "no acceptable match" fallback resolves to the
        // canonical locale rather than the first supported one.
        $header = $request->getPreferredLanguage(['ru', 'tg', 'en']);

        return in_array($header, self::SUPPORTED, true) ? $header : self::DEFAULT;
    }
}
