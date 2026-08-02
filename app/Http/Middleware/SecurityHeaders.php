<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baseline security headers for every response the application itself renders:
 * the editorial panel (session cookies, unpublished material) and the public
 * API. The public site has had this contract from the start; the CMS answered
 * without a single one of these headers, which is the more sensitive of the
 * two hosts.
 *
 * Deliberately *not* a full Content-Security-Policy: the panel is Inertia +
 * Vite, and a script policy there needs its own pass with a measured nonce or
 * hash strategy. `frame-ancestors` is the part that can be set today without
 * risking a broken editor, and it is the one that stops clickjacking; the rest
 * of CSP is tracked separately.
 *
 * Media and built assets are served by the web server straight from disk and
 * never reach PHP — their headers belong to the nginx recipe in DEPLOYMENT.md.
 * The same goes for hiding `Server`/`X-Powered-By`: PHP-FPM and nginx add them
 * after this code has run, so `expose_php=Off` and `fastcgi_hide_header` are
 * the honest place for that, not a middleware that cannot prove it worked.
 */
class SecurityHeaders
{
    /**
     * @var array<string, string>
     */
    private const HEADERS = [
        'Content-Security-Policy' => "frame-ancestors 'none'",
        'X-Frame-Options' => 'DENY',
        'X-Content-Type-Options' => 'nosniff',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), browsing-topics=()',
        'Cross-Origin-Opener-Policy' => 'same-origin',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        foreach (self::HEADERS as $header => $value) {
            $response->headers->set($header, $value, false);
        }

        // Only over TLS: sent on a plain-HTTP answer the header is ignored by
        // browsers anyway, and locking a developer's http:// host into HTTPS
        // for two years would be a hard-to-undo accident. No `preload` — the
        // apex domain declares it for the whole zone (see the front config).
        if ($request->isSecure()) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=63072000; includeSubDomains',
                false,
            );
        }

        return $response;
    }
}
