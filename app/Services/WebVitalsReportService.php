<?php

namespace App\Services;

use App\Models\WebVitalSample;
use App\Support\WebVitalThresholds;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class WebVitalsReportService
{
    public const CACHE_KEY = 'rum:web-vitals:report:v1';

    /**
     * @return array{
     *     period_days: int,
     *     since: string,
     *     total_samples: int,
     *     metrics: list<array{metric: string, p75: float|null, samples: int, rating: string, provisional: bool}>,
     *     routes: list<array{route: string, samples: int, metrics: list<array{metric: string, p75: float|null, samples: int, rating: string, provisional: bool}>}>,
     *     devices: list<array{device: string, samples: int, metrics: list<array{metric: string, p75: float|null, samples: int, rating: string, provisional: bool}>}>
     * }
     */
    public function summary(int $days = 28): array
    {
        $days = max(1, min(90, $days));

        /** @var array{
         *     period_days: int,
         *     since: string,
         *     total_samples: int,
         *     metrics: list<array{metric: string, p75: float|null, samples: int, rating: string, provisional: bool}>,
         *     routes: list<array{route: string, samples: int, metrics: list<array{metric: string, p75: float|null, samples: int, rating: string, provisional: bool}>}>,
         *     devices: list<array{device: string, samples: int, metrics: list<array{metric: string, p75: float|null, samples: int, rating: string, provisional: bool}>}>
         * } $report
         */
        $report = Cache::remember(
            self::CACHE_KEY,
            now()->addMinutes(5),
            fn (): array => $this->build($days),
        );

        return $report;
    }

    /**
     * @return array{
     *     period_days: int,
     *     since: string,
     *     total_samples: int,
     *     metrics: list<array{metric: string, p75: float|null, samples: int, rating: string, provisional: bool}>,
     *     routes: list<array{route: string, samples: int, metrics: list<array{metric: string, p75: float|null, samples: int, rating: string, provisional: bool}>}>,
     *     devices: list<array{device: string, samples: int, metrics: list<array{metric: string, p75: float|null, samples: int, rating: string, provisional: bool}>}>
     * }
     */
    private function build(int $days): array
    {
        $since = now()->subDays($days);
        $overallRows = $this->percentileRows($since, []);
        $routeRows = $this->percentileRows($since, ['route']);
        $deviceRows = $this->percentileRows($since, ['device']);
        $metrics = $this->metricSummaries($overallRows);

        return [
            'period_days' => $days,
            'since' => $since->toIso8601String(),
            'total_samples' => array_sum(array_column($metrics, 'samples')),
            'metrics' => $metrics,
            'routes' => $this->routeSummaries($routeRows),
            'devices' => $this->deviceSummaries($deviceRows),
        ];
    }

    /**
     * Calculate exact nearest-rank p75 values in the database. Both supported
     * engines (SQLite and MySQL 8) implement the required window functions.
     *
     * @param  list<'route'|'device'>  $dimensions
     * @return list<array{metric: string, value: float, sample_count: int, route?: string, device?: string}>
     */
    private function percentileRows(mixed $since, array $dimensions): array
    {
        $partitionColumns = ['metric', ...$dimensions];
        $partition = implode(', ', $partitionColumns);

        $ranked = WebVitalSample::query()
            ->where('created_at', '>=', $since)
            ->whereIn('metric', WebVitalThresholds::METRICS)
            ->select([...$partitionColumns, 'value'])
            ->selectRaw("ROW_NUMBER() OVER (PARTITION BY {$partition} ORDER BY value) AS sample_rank")
            ->selectRaw("COUNT(*) OVER (PARTITION BY {$partition}) AS sample_count");

        $queryRows = DB::query()
            ->fromSub($ranked, 'ranked_vitals')
            ->whereRaw('sample_rank = CEIL(sample_count * 0.75)')
            ->get();
        $rows = [];

        foreach ($queryRows as $row) {
            $mapped = [
                'metric' => (string) $row->metric,
                'value' => (float) $row->value,
                'sample_count' => (int) $row->sample_count,
            ];

            if (in_array('route', $dimensions, true)) {
                $mapped['route'] = (string) $row->route;
            }

            if (in_array('device', $dimensions, true)) {
                $mapped['device'] = (string) $row->device;
            }

            $rows[] = $mapped;
        }

        return $rows;
    }

    /**
     * @param  list<array{metric: string, value: float, sample_count: int, route?: string, device?: string}>  $rows
     * @return list<array{metric: string, p75: float|null, samples: int, rating: string, provisional: bool}>
     */
    private function metricSummaries(array $rows): array
    {
        $summaries = [];

        foreach (WebVitalThresholds::METRICS as $metric) {
            $matchingRow = null;

            foreach ($rows as $row) {
                if ($row['metric'] === $metric) {
                    $matchingRow = $row;
                    break;
                }
            }

            $p75 = $matchingRow['value'] ?? null;
            $samples = $matchingRow['sample_count'] ?? 0;
            $summaries[] = [
                'metric' => $metric,
                'p75' => $p75,
                'samples' => $samples,
                'rating' => $p75 === null ? 'no-data' : WebVitalThresholds::rating($metric, $p75),
                'provisional' => $samples < 75,
            ];
        }

        return $summaries;
    }

    /**
     * @param  list<array{metric: string, value: float, sample_count: int, route?: string, device?: string}>  $rows
     * @return list<array{route: string, samples: int, metrics: list<array{metric: string, p75: float|null, samples: int, rating: string, provisional: bool}>}>
     */
    private function routeSummaries(array $rows): array
    {
        $grouped = [];

        foreach ($rows as $row) {
            $grouped[$row['route'] ?? '/other'][] = $row;
        }

        $summaries = [];

        foreach ($grouped as $route => $routeRows) {
            $metrics = $this->metricSummaries($routeRows);
            $summaries[] = [
                'route' => $route,
                'samples' => array_sum(array_column($metrics, 'samples')),
                'metrics' => $metrics,
            ];
        }

        usort($summaries, fn (array $left, array $right): int => $right['samples'] <=> $left['samples']);

        return array_slice($summaries, 0, 10);
    }

    /**
     * @param  list<array{metric: string, value: float, sample_count: int, route?: string, device?: string}>  $rows
     * @return list<array{device: string, samples: int, metrics: list<array{metric: string, p75: float|null, samples: int, rating: string, provisional: bool}>}>
     */
    private function deviceSummaries(array $rows): array
    {
        $grouped = [];

        foreach ($rows as $row) {
            $grouped[$row['device'] ?? 'unknown'][] = $row;
        }

        $summaries = [];

        foreach ($grouped as $device => $deviceRows) {
            $metrics = $this->metricSummaries($deviceRows);
            $summaries[] = [
                'device' => $device,
                'samples' => array_sum(array_column($metrics, 'samples')),
                'metrics' => $metrics,
            ];
        }

        usort($summaries, fn (array $left, array $right): int => $right['samples'] <=> $left['samples']);

        return $summaries;
    }
}
