<?php

namespace App\Http\Middleware;

use App\Services\OperationalTelemetry;
use App\Services\PublicReadModelCache;
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

        $readModels = app(PublicReadModelCache::class);
        $readModels->startRequest();

        $response = $next($request);
        $response->headers->set('X-Request-ID', $requestId);

        // Отдельным заголовком, а не только в метрике: без него «медленно»
        // и «холодный кэш» невозможно различить на конкретном запросе — ни
        // в логе edge, ни руками через curl.
        $cache = $readModels->outcome();
        if ($cache !== null) {
            $response->headers->set('X-Cache', strtoupper($cache));
            app(OperationalTelemetry::class)->recordCacheOutcome($cache);
        }

        if ($request->isMethod('GET')) {
            $this->addCaching($request, $response);
        }

        $this->logRequest($request, $response, $requestId, $startedAt, $cache);

        return $response;
    }

    private function logRequest(
        Request $request,
        Response $response,
        string $requestId,
        int $startedAt,
        ?string $cache,
    ): void {
        $durationMs = round((hrtime(true) - $startedAt) / 1_000_000, 1);
        $status = $response->getStatusCode();
        $slowThreshold = (float) config('observability.api.slow_request_ms', 750);
        $sampleRate = min(max((float) config('observability.api.sample_rate', 0.01), 0), 1);

        $level = match (true) {
            $status >= 500 => 'error',
            $status >= 400, $durationMs >= $slowThreshold => 'warning',
            $this->sample($sampleRate) => 'info',
            default => null,
        };

        if ($level === null) {
            return;
        }

        app(OperationalTelemetry::class)->recordApi(
            $request->route()?->getName() ?? 'unmatched',
            $status,
            $durationMs,
            $cache,
        );

        Log::log($level, 'public_api_response', [
            'event' => 'public_api_response',
            'request_id' => $requestId,
            'method' => $request->method(),
            'route' => $request->route()?->getName() ?? 'unmatched',
            'status' => $status,
            'duration_ms' => $durationMs,
            'slow' => $durationMs >= $slowThreshold,
            'cache' => $cache,
        ]);
    }

    private function sample(float $rate): bool
    {
        return $rate >= 1 || ($rate > 0 && random_int(1, 1_000_000) <= (int) round($rate * 1_000_000));
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
