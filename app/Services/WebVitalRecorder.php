<?php

namespace App\Services;

use App\Models\WebVitalSample;
use App\Support\WebVitalThresholds;
use Illuminate\Support\Facades\Cache;

final class WebVitalRecorder
{
    /**
     * @param  array{name: string, value: float|int, id: string, path: string, locale: string, device: string, navigation_type: string}  $payload
     */
    public function record(array $payload): WebVitalSample
    {
        $metric = $payload['name'];
        $value = (float) $payload['value'];

        $sample = WebVitalSample::query()->firstOrCreate(
            ['sample_hash' => hash('sha256', "{$metric}|{$payload['id']}")],
            [
                'metric' => $metric,
                'value' => $value,
                'rating' => WebVitalThresholds::rating($metric, $value),
                'route' => WebVitalThresholds::normalizeRoute($payload['path']),
                'locale' => $payload['locale'],
                'device' => $payload['device'],
                'navigation_type' => $payload['navigation_type'],
            ],
        );

        if ($sample->wasRecentlyCreated) {
            Cache::forget(WebVitalsReportService::CACHE_KEY);
        }

        return $sample;
    }
}
