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

    public function recordApi(string $route, int $status, float $durationMs, ?string $cache = null): void
    {
        $this->update('api', [
            'route' => $route,
            'status' => $status,
            'duration_ms' => round($durationMs, 1),
            'cache' => $cache,
            'recorded_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * Попадания в read-model-кэш считаются отдельно от выборки запросов и на
     * каждом запросе. Иначе доля попаданий считалась бы по той же выборке, что
     * и остальные метрики, — а туда по построению попадают медленные и
     * ошибочные ответы, то есть как раз промахи: получилась бы не метрика, а
     * заниженное число, в которое нельзя верить. Счётчик — один атомарный
     * инкремент в сутки на исход, а не запись выборки под локом.
     */
    public function recordCacheOutcome(string $outcome): void
    {
        $key = $this->cacheCounterKey($outcome);

        Cache::add($key, 0, now()->addDays(2));
        Cache::increment($key);
    }

    private function cacheCounterKey(string $outcome): string
    {
        return self::CACHE_KEY.":cache:{$outcome}:".now()->format('Y-m-d');
    }

    /**
     * @return array{hits: int, misses: int, partial: int, requests: int, hit_rate: float|null}
     */
    private function cacheSummary(): array
    {
        $hits = (int) Cache::get($this->cacheCounterKey('hit'), 0);
        $misses = (int) Cache::get($this->cacheCounterKey('miss'), 0);
        $partial = (int) Cache::get($this->cacheCounterKey('partial'), 0);
        $requests = $hits + $misses + $partial;

        return [
            'hits' => $hits,
            'misses' => $misses,
            // Частичное попадание считаем половиной: запрос, собравший часть
            // данных из кэша, честнее округлять к середине, чем записывать
            // целиком в одну из сторон.
            'partial' => $partial,
            'requests' => $requests,
            'hit_rate' => $requests === 0
                ? null
                : round(($hits + $partial / 2) / $requests, 3),
        ];
    }

    /**
     * @return array{
     *   api: array{samples: int, client_errors: int, server_errors: int, p95_ms: float|null, window: array{from: string|null, to: string|null}, routes: list<array{route: string, samples: int, client_errors: int, server_errors: int, p95_ms: float|null}>},
     *   queue: array{samples: int, failures: int, p95_ms: float|null, window: array{from: string|null, to: string|null}, last_processed_at: string|null},
     *   cache: array{hits: int, misses: int, partial: int, requests: int, hit_rate: float|null}
     * }
     */
    public function summary(): array
    {
        /** @var array{
         *   api?: list<array{route: string, status: int, duration_ms: float, cache?: string|null, recorded_at: string}>,
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
                // 4xx и 5xx разделены намеренно: общее число «ошибок»
                // смешивает 404 от краулера с отказом сервера, и по нему
                // нельзя решить, инцидент это или обычный шум.
                'client_errors' => $this->countStatuses($samples, 400, 499),
                'server_errors' => $this->countStatuses($samples, 500, 599),
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
                'client_errors' => $this->countStatuses($api, 400, 499),
                'server_errors' => $this->countStatuses($api, 500, 599),
                'p95_ms' => $this->percentile($this->durations($api), 0.95),
                // Окно, которое покрывает выборка. Без него «p95 = 400 мс»
                // нельзя прочитать: двести замеров могли уложиться в минуту
                // пиковой нагрузки или растянуться на три дня.
                'window' => $this->window($api),
                'routes' => $routes,
            ],
            'queue' => [
                'samples' => count($queue),
                'failures' => count(array_filter($queue, fn (array $sample): bool => $sample['failed'])),
                'p95_ms' => $this->percentile($this->durations($queue), 0.95),
                'window' => $this->window($queue),
                'last_processed_at' => $lastQueueSample['recorded_at'] ?? null,
            ],
            'cache' => $this->cacheSummary(),
        ];
    }

    /**
     * @param  list<array{status?: int, ...}>  $samples
     */
    private function countStatuses(array $samples, int $from, int $to): int
    {
        return count(array_filter(
            $samples,
            fn (array $sample): bool => ($sample['status'] ?? 0) >= $from && ($sample['status'] ?? 0) <= $to,
        ));
    }

    /**
     * @param  list<array{recorded_at?: string, ...}>  $samples
     * @return array{from: string|null, to: string|null}
     */
    private function window(array $samples): array
    {
        $timestamps = array_values(array_filter(array_map(
            fn (array $sample): ?string => $sample['recorded_at'] ?? null,
            $samples,
        )));

        if ($timestamps === []) {
            return ['from' => null, 'to' => null];
        }

        return ['from' => min($timestamps), 'to' => max($timestamps)];
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
