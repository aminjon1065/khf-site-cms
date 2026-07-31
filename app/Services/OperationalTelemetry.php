<?php

namespace App\Services;

use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;

final class OperationalTelemetry
{
    public const CACHE_KEY = 'operations:telemetry:v1';

    private const MAX_SAMPLES = 200;

    /**
     * @var array<string, float|int>
     */
    private array $queueStartedAt = [];

    public function startQueueJob(string $id): void
    {
        $this->queueStartedAt[$id] = hrtime(true);
    }

    public function finishQueueJob(string $id, string $queue, bool $failed = false): void
    {
        $startedAt = $this->queueStartedAt[$id] ?? hrtime(true);
        unset($this->queueStartedAt[$id]);

        $this->update('queue', [
            'name' => $queue,
            'duration_ms' => round((hrtime(true) - $startedAt) / 1_000_000, 1),
            'failed' => $failed,
            'recorded_at' => now()->toIso8601String(),
        ]);
    }

    public function recordApi(string $route, int $status, float $durationMs): void
    {
        $this->update('api', [
            'route' => $route,
            'status' => $status,
            'duration_ms' => round($durationMs, 1),
            'recorded_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * @return array{
     *   api: array{samples: int, errors: int, p95_ms: float|null, routes: list<array{route: string, samples: int, errors: int, p95_ms: float|null}>},
     *   queue: array{samples: int, failures: int, p95_ms: float|null, last_processed_at: string|null}
     * }
     */
    public function summary(): array
    {
        /** @var array{
         *   api?: list<array{route: string, status: int, duration_ms: float, recorded_at: string}>,
         *   queue?: list<array{name: string, duration_ms: float, failed: bool, recorded_at: string}>
         * } $data
         */
        $data = Cache::get(self::CACHE_KEY, []);
        $api = $data['api'] ?? [];
        $queue = $data['queue'] ?? [];
        $routeGroups = [];

        foreach ($api as $sample) {
            $routeGroups[$sample['route']][] = $sample;
        }

        $routes = [];

        foreach ($routeGroups as $route => $samples) {
            $routes[] = [
                'route' => $route,
                'samples' => count($samples),
                'errors' => count(array_filter($samples, fn (array $sample): bool => $sample['status'] >= 400)),
                'p95_ms' => $this->percentile($this->durations($samples), 0.95),
            ];
        }

        usort(
            $routes,
            fn (array $left, array $right): int => ($right['p95_ms'] ?? 0) <=> ($left['p95_ms'] ?? 0),
        );

        $lastQueueSample = $queue !== [] ? $queue[array_key_last($queue)] : null;

        return [
            'api' => [
                'samples' => count($api),
                'errors' => count(array_filter($api, fn (array $sample): bool => $sample['status'] >= 400)),
                'p95_ms' => $this->percentile($this->durations($api), 0.95),
                'routes' => $routes,
            ],
            'queue' => [
                'samples' => count($queue),
                'failures' => count(array_filter($queue, fn (array $sample): bool => $sample['failed'])),
                'p95_ms' => $this->percentile($this->durations($queue), 0.95),
                'last_processed_at' => $lastQueueSample['recorded_at'] ?? null,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $sample
     */
    private function update(string $group, array $sample): void
    {
        try {
            Cache::lock(self::CACHE_KEY.':lock', 5)->block(1, function () use ($group, $sample): void {
                $data = Cache::get(self::CACHE_KEY, ['api' => [], 'queue' => []]);
                $samples = [...($data[$group] ?? []), $sample];
                $data[$group] = array_slice($samples, -self::MAX_SAMPLES);

                Cache::put(self::CACHE_KEY, $data, now()->addDays(7));
            });
        } catch (LockTimeoutException) {
            // Telemetry is deliberately best-effort and may never block work.
        }
    }

    /**
     * @param  list<float|int|string>  $values
     */
    private function percentile(array $values, float $percentile): ?float
    {
        if ($values === []) {
            return null;
        }

        $values = array_map('floatval', $values);
        sort($values, SORT_NUMERIC);
        $rank = max(0, (int) ceil(count($values) * $percentile) - 1);

        return round($values[$rank], 1);
    }

    /**
     * @param  list<array{duration_ms: float, ...}>  $samples
     * @return list<float>
     */
    private function durations(array $samples): array
    {
        return array_map(
            fn (array $sample): float => $sample['duration_ms'],
            $samples,
        );
    }
}
