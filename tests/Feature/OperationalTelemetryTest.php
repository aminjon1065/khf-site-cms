<?php

use App\Services\OperationalTelemetry;
use Illuminate\Support\Facades\Cache;

it('reports exact bounded API and queue operational percentiles', function () {
    Cache::forget(OperationalTelemetry::CACHE_KEY);
    $telemetry = app(OperationalTelemetry::class);

    foreach ([10, 20, 30, 40, 500] as $duration) {
        $telemetry->recordApi('api.news.index', $duration === 500 ? 503 : 200, $duration);
    }
    $telemetry->recordApi('api.health', 200, 5);

    $telemetry->startQueueJob('job-1');
    $telemetry->finishQueueJob('job-1', 'critical');

    $summary = $telemetry->summary();

    expect($summary['api'])
        ->samples->toBe(6)
        ->errors->toBe(1)
        ->p95_ms->toBe(500.0)
        ->and($summary['api']['routes'][0])
        ->route->toBe('api.news.index')
        ->samples->toBe(5)
        ->errors->toBe(1)
        ->and($summary['queue'])
        ->samples->toBe(1)
        ->failures->toBe(0)
        ->last_processed_at->not->toBeNull();
});
