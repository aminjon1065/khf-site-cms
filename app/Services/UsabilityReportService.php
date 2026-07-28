<?php

namespace App\Services;

use App\Models\UsabilitySession;
use App\Support\UsabilityStudy;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class UsabilityReportService
{
    /**
     * @return array{
     *     summary: array{
     *         participants: int,
     *         task_attempts: int,
     *         unassisted_success_rate: float,
     *         sus_average: float|null,
     *         irreversible_errors: int,
     *         ready: bool
     *     },
     *     targets: array<string, array{label: string, actual: int|float|null, target: string, passed: bool}>,
     *     task_metrics: list<array{
     *         key: string,
     *         label: string,
     *         attempts: int,
     *         unassisted_success_rate: float,
     *         p75_seconds: int|null,
     *         target_seconds: int|null
     *     }>,
     *     sessions: list<array{
     *         participant_code: string,
     *         role: string,
     *         experience_level: string,
     *         sus_score: float,
     *         completed_at: string
     *     }>
     * }
     */
    public function report(): array
    {
        return Cache::remember('usability:report:v1', now()->addMinutes(5), function (): array {
            $sessions = UsabilitySession::query()
                ->whereNotNull('completed_at')
                ->latest('completed_at')
                ->get();

            return $this->buildReport($sessions);
        });
    }

    public function forget(): void
    {
        Cache::forget('usability:report:v1');
    }

    /**
     * @param  Collection<int, UsabilitySession>  $sessions
     * @return array{
     *     summary: array{
     *         participants: int,
     *         task_attempts: int,
     *         unassisted_success_rate: float,
     *         sus_average: float|null,
     *         irreversible_errors: int,
     *         ready: bool
     *     },
     *     targets: array<string, array{label: string, actual: int|float|null, target: string, passed: bool}>,
     *     task_metrics: list<array{
     *         key: string,
     *         label: string,
     *         attempts: int,
     *         unassisted_success_rate: float,
     *         p75_seconds: int|null,
     *         target_seconds: int|null
     *     }>,
     *     sessions: list<array{
     *         participant_code: string,
     *         role: string,
     *         experience_level: string,
     *         sus_score: float,
     *         completed_at: string
     *     }>
     * }
     */
    private function buildReport(Collection $sessions): array
    {
        $taskMetrics = collect(UsabilityStudy::TASKS)
            ->map(function (array $definition, string $key) use ($sessions): array {
                $attempts = $sessions
                    ->map(fn (UsabilitySession $session): ?array => $session->tasks[$key] ?? null)
                    ->filter(fn (?array $task): bool => is_array($task))
                    ->values();
                $successful = $attempts->filter(
                    fn (array $task): bool => (bool) $task['completed'] && ! (bool) $task['assisted'],
                )->count();

                return [
                    'key' => $key,
                    'label' => $definition['label'],
                    'attempts' => $attempts->count(),
                    'unassisted_success_rate' => $this->percentage($successful, $attempts->count()),
                    'p75_seconds' => $this->percentile75(
                        array_values(
                            $attempts
                                ->filter(fn (array $task): bool => (bool) $task['completed'])
                                ->map(fn (array $task): int => (int) $task['duration_seconds'])
                                ->all(),
                        ),
                    ),
                    'target_seconds' => $definition['target_seconds'],
                ];
            })
            ->values();

        $allAttempts = $sessions->flatMap(
            fn (UsabilitySession $session): array => array_values($session->tasks),
        );
        $successfulAttempts = $allAttempts->filter(
            fn (array $task): bool => (bool) $task['completed'] && ! (bool) $task['assisted'],
        )->count();
        $unassistedSuccessRate = $this->percentage($successfulAttempts, $allAttempts->count());
        $susAverage = $sessions->isEmpty() ? null : round((float) $sessions->avg('sus_score'), 2);
        $irreversibleErrors = $allAttempts->filter(
            fn (array $task): bool => (bool) $task['irreversible_error'],
        )->count();
        $taskMetric = fn (string $key): array => $taskMetrics
            ->firstWhere('key', $key) ?? ['p75_seconds' => null];

        $targets = [
            'participants' => [
                'label' => 'Реальные участники',
                'actual' => $sessions->count(),
                'target' => '≥ 5',
                'passed' => $sessions->count() >= 5,
            ],
            'unassisted_success_rate' => [
                'label' => 'Задачи без помощи',
                'actual' => $unassistedSuccessRate,
                'target' => '≥ 90%',
                'passed' => $unassistedSuccessRate >= 90,
            ],
            'news_first' => [
                'label' => 'Первая новость, p75',
                'actual' => $taskMetric('news_first')['p75_seconds'],
                'target' => '≤ 600 сек.',
                'passed' => $taskMetric('news_first')['p75_seconds'] !== null
                    && $taskMetric('news_first')['p75_seconds'] <= 600,
            ],
            'news_repeat' => [
                'label' => 'Повторная новость, p75',
                'actual' => $taskMetric('news_repeat')['p75_seconds'],
                'target' => '≤ 300 сек.',
                'passed' => $taskMetric('news_repeat')['p75_seconds'] !== null
                    && $taskMetric('news_repeat')['p75_seconds'] <= 300,
            ],
            'critical_alert' => [
                'label' => 'Критическое предупреждение, p75',
                'actual' => $taskMetric('critical_alert')['p75_seconds'],
                'target' => '≤ 180 сек.',
                'passed' => $taskMetric('critical_alert')['p75_seconds'] !== null
                    && $taskMetric('critical_alert')['p75_seconds'] <= 180,
            ],
            'sus_average' => [
                'label' => 'Средний SUS',
                'actual' => $susAverage,
                'target' => '≥ 85',
                'passed' => $susAverage !== null && $susAverage >= 85,
            ],
            'irreversible_errors' => [
                'label' => 'Необратимые ошибки',
                'actual' => $irreversibleErrors,
                'target' => '= 0',
                'passed' => $irreversibleErrors === 0,
            ],
        ];

        return [
            'summary' => [
                'participants' => $sessions->count(),
                'task_attempts' => $allAttempts->count(),
                'unassisted_success_rate' => $unassistedSuccessRate,
                'sus_average' => $susAverage,
                'irreversible_errors' => $irreversibleErrors,
                'ready' => collect($targets)->every(fn (array $target): bool => $target['passed']),
            ],
            'targets' => $targets,
            'task_metrics' => array_values($taskMetrics->all()),
            'sessions' => array_values(
                $sessions
                    ->take(20)
                    ->map(fn (UsabilitySession $session): array => [
                        'participant_code' => $session->participant_code,
                        'role' => $session->role,
                        'experience_level' => $session->experience_level,
                        'sus_score' => $session->sus_score,
                        'completed_at' => $session->completed_at->toIso8601String(),
                    ])
                    ->all(),
            ),
        ];
    }

    private function percentage(int $successful, int $total): float
    {
        return $total === 0 ? 0 : round(($successful / $total) * 100, 2);
    }

    /**
     * @param  list<int>  $values
     */
    private function percentile75(array $values): ?int
    {
        if ($values === []) {
            return null;
        }

        sort($values);
        $index = max(0, (int) ceil(count($values) * 0.75) - 1);

        return $values[$index];
    }
}
