<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/** Adds correlation, structured access logging and conditional GET to the API. */
class PublicApiResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = hrtime(true);
        $incomingId = (string) $request->header('X-Request-ID', '');
        $requestId = preg_match('/^[A-Za-z0-9_.:-]{1,100}$/', $incomingId) === 1
            ? $incomingId
            : (string) Str::uuid();

        $response = $next($request);
        $response->headers->set('X-Request-ID', $requestId);

        if ($request->isMethod('GET')) {
            $this->addCaching($request, $response);
        }

        Log::info('public_api_request', [
            'request_id' => $requestId,
            'method' => $request->method(),
            'path' => '/'.$request->path(),
            'status' => $response->getStatusCode(),
            'duration_ms' => round((hrtime(true) - $startedAt) / 1_000_000, 1),
        ]);

        return $response;
    }

    private function addCaching(Request $request, Response $response): void
    {
        if ($request->routeIs('api.health', 'api.ready')) {
            $response->headers->set('Cache-Control', 'no-store');

            return;
        }

        if (! $response->isSuccessful()) {
            $response->headers->set('Cache-Control', 'no-store');

            return;
        }

        $etag = '"'.hash('sha256', (string) $response->getContent()).'"';
        $response->headers->set('ETag', $etag);
        $response->headers->set('Cache-Control', 'public, max-age=30, stale-while-revalidate=60');
        $response->headers->set('Vary', 'Accept-Language, Accept-Encoding');

        if ($request->headers->get('If-None-Match') === $etag) {
            $response->setNotModified();
        }
    }
}
