<?php

namespace App\Services;

use App\Models\Alert;
use App\Models\Region;
use App\Models\User;
use App\Support\PublicApiLabels;
use App\Support\PublicLocale;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Computes the public alert snapshot for the site map and home banner: the
 * current alert level and active-event count for each region, plus the global
 * alert state, derived from currently-active alerts.
 */
class AlertMapService
{
    /**
     * When the scheduler last reconciled alert states (published the due
     * ones, completed the expired ones) — written by ProcessScheduledContent,
     * kept without expiry so a stopped scheduler stays visible.
     */
    public const RECONCILED_AT_KEY = 'alerts.reconciled_at';

    /**
     * The scheduler runs every minute; beyond this it is considered stopped.
     */
    private const RECONCILE_GRACE_MINUTES = 5;

    /**
     * @var array<string, int>
     */
    private const RANK = ['none' => 0, 'info' => 1, 'warning' => 2, 'danger' => 3, 'critical' => 4];

    /**
     * Global state + per-region statuses.
     *
     * @return array{state: string, count: int, updated_at: string, regions: list<array{key: string, name: string, level: string, count: int, statusText: string}>}
     */
    public function snapshot(string $locale = 'ru', ?User $user = null): array
    {
        $alertsQuery = Alert::query()->accessibleTo($user)->active()->with('regions');
        PublicLocale::available($alertsQuery, 'title', $locale);
        /** @var Collection<int, Alert> $alerts */
        $alerts = $alertsQuery->get();
        $regions = Region::query()
            ->when(
                $user?->hasRole('regional_editor'),
                fn ($query) => $query->whereKey($user->region_id ?? 0),
            )
            ->orderBy('sort')
            ->get();

        return $this->snapshotFor($alerts, $regions, $locale);
    }

    /**
     * Build a snapshot from already-loaded alerts and regions so compound read
     * models do not repeat the same active-alert query.
     *
     * @param  Collection<int, Alert>  $alerts
     * @param  Collection<int, Region>  $regions
     * @return array{state: string, count: int, updated_at: string, regions: list<array{key: string, name: string, level: string, count: int, statusText: string}>}
     */
    public function snapshotFor(Collection $alerts, Collection $regions, string $locale): array
    {
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
                'name' => $region->getTranslation('name', $locale, false),
                'level' => $level,
                'count' => $touching->count(),
                'statusText' => PublicApiLabels::get('region_status', $level, $locale),
            ];
        }

        return [
            'state' => $maxRank >= 3 ? 'critical' : ($maxRank >= 1 ? 'warning' : 'calm'),
            'count' => $alerts->count(),
            'updated_at' => $this->reliableAsOf()->toIso8601String(),
            'regions' => $statuses,
        ];
    }

    /**
     * The moment this state is known to be true: now, when the snapshot reads
     * the database — unless the scheduler that starts and ends alerts on time
     * has stopped; then only as of its last run. Saying «no alerts as of now»
     * after hours without reconciliation would be a false calm.
     */
    private function reliableAsOf(): CarbonImmutable
    {
        // To the minute: the site shows hours and minutes, and the response
        // (and its ETag) stays the same within a minute.
        $now = CarbonImmutable::now()->startOfMinute();
        $reconciledAt = Cache::get(self::RECONCILED_AT_KEY);

        if (is_string($reconciledAt)) {
            $at = CarbonImmutable::parse($reconciledAt);

            if ($at->lt($now->subMinutes(self::RECONCILE_GRACE_MINUTES))) {
                return $at;
            }
        }

        return $now;
    }

    /**
     * @return list<array{key: string, name: string, level: string, count: int, statusText: string}>
     */
    public function regionStatuses(string $locale = 'ru'): array
    {
        return $this->snapshot($locale)['regions'];
    }
}
