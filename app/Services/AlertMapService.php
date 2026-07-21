<?php

namespace App\Services;

use App\Models\Alert;
use App\Models\Region;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Computes the public alert snapshot for the site map and home banner: the
 * current alert level and active-event count for each region, plus the global
 * alert state, derived from currently-active alerts.
 */
class AlertMapService
{
    /**
     * @var array<string, int>
     */
    private const RANK = ['none' => 0, 'info' => 1, 'warning' => 2, 'danger' => 3, 'critical' => 4];

    /**
     * @var array<string, string>
     */
    private const STATUS_TEXT = [
        'none' => 'Обстановка штатная',
        'info' => 'Информационное уведомление',
        'warning' => 'Действует предупреждение',
        'danger' => 'Опасная обстановка',
        'critical' => 'Критическая ситуация',
    ];

    /**
     * Global state + per-region statuses.
     *
     * @return array{state: string, count: int, regions: list<array{key: string, name: string, level: string, count: int, statusText: string}>}
     */
    public function snapshot(string $locale = 'ru', ?User $user = null): array
    {
        /** @var Collection<int, Alert> $alerts */
        $alerts = Alert::query()->accessibleTo($user)->active()->with('regions')->get();
        $regions = Region::query()
            ->when(
                $user?->hasRole('regional_editor'),
                fn ($query) => $query->whereKey($user->region_id ?? 0),
            )
            ->orderBy('sort')
            ->get();

        $statuses = [];
        $maxRank = 0;

        foreach ($regions as $region) {
            $touching = $alerts->filter(
                fn (Alert $alert): bool => $alert->territory_type === 'country'
                    || $alert->regions->contains('code', $region->code),
            );

            $level = 'none';

            foreach ($touching as $alert) {
                $candidate = $alert->severity->level();

                if (self::RANK[$candidate] > self::RANK[$level]) {
                    $level = $candidate;
                }
            }

            $maxRank = max($maxRank, self::RANK[$level]);

            $statuses[] = [
                'key' => $region->code,
                'name' => $region->getTranslation('name', $locale),
                'level' => $level,
                'count' => $touching->count(),
                'statusText' => self::STATUS_TEXT[$level],
            ];
        }

        return [
            'state' => $maxRank >= 3 ? 'critical' : ($maxRank >= 1 ? 'warning' : 'calm'),
            'count' => $alerts->count(),
            'regions' => $statuses,
        ];
    }

    /**
     * @return list<array{key: string, name: string, level: string, count: int, statusText: string}>
     */
    public function regionStatuses(string $locale = 'ru'): array
    {
        return $this->snapshot($locale)['regions'];
    }
}
