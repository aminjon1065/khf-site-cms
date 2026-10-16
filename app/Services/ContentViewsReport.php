<?php

namespace App\Services;

use App\Contracts\Workflowable;
use App\Models\User;
use App\Support\ContentTitle;
use App\Support\ContentTypes;
use App\Support\PublicSite;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * «Статистика просмотров»: how many times the public pages were opened over
 * the last days — in total, per day, per site language and per type — and
 * which materials were read most. Only the types the user may view.
 */
class ContentViewsReport
{
    /**
     * Periods offered on the page, in days.
     *
     * @var list<int>
     */
    public const PERIODS = [7, 30, 90];

    /**
     * @return array{
     *     days: int,
     *     total: int,
     *     daily: list<array{date: string, views: int}>,
     *     by_locale: array<string, int>,
     *     by_type: list<array{type: string, label: string, views: int}>,
     *     top: list<array{type: string, type_label: string, title: string, views: int, edit_url: string, public_url: string|null}>
     * }
     */
    public function summary(User $user, int $days): array
    {
        $days = in_array($days, self::PERIODS, true) ? $days : self::PERIODS[0];
        $types = $this->typesFor($user);
        $from = CarbonImmutable::today()->subDays($days - 1);

        $query = fn (): Builder => DB::table('content_views')
            ->whereIn('content_type', $types)
            ->where('date', '>=', $from->toDateString());

        $perDay = $query()->selectRaw('date, SUM(views) as views')->groupBy('date')->pluck('views', 'date');
        $daily = [];
        for ($day = $from; $day->lessThanOrEqualTo(CarbonImmutable::today()); $day = $day->addDay()) {
            $daily[] = ['date' => $day->toDateString(), 'views' => (int) ($perDay[$day->toDateString()] ?? 0)];
        }

        $byLocale = $query()->selectRaw('locale, SUM(views) as views')->groupBy('locale')->pluck('views', 'locale');
        $byType = $query()->selectRaw('content_type, SUM(views) as views')->groupBy('content_type')->pluck('views', 'content_type');

        return [
            'days' => $days,
            'total' => (int) array_sum(array_column($daily, 'views')),
            'daily' => $daily,
            'by_locale' => [
                'tj' => (int) ($byLocale['tj'] ?? 0),
                'ru' => (int) ($byLocale['ru'] ?? 0),
                'en' => (int) ($byLocale['en'] ?? 0),
            ],
            'by_type' => array_map(fn (string $type): array => [
                'type' => $type,
                'label' => ContentTypes::META[$type]['label'],
                'views' => (int) ($byType[$type] ?? 0),
            ], $types),
            'top' => $this->top($query()),
        ];
    }

    /**
     * The twenty most read materials of the period.
     *
     * @return list<array{type: string, type_label: string, title: string, views: int, edit_url: string, public_url: string|null}>
     */
    private function top(Builder $query): array
    {
        $rows = $query
            ->selectRaw('content_type, content_id, SUM(views) as views')
            ->groupBy('content_type', 'content_id')
            ->orderByDesc('views')
            ->limit(20)
            ->get();

        $models = [];
        foreach ($rows->groupBy('content_type') as $type => $items) {
            $class = ContentTypes::MAP[(string) $type];
            $models[$type] = $class::withTrashed()->whereKey($items->pluck('content_id')->all())->get()->keyBy('id');
        }

        $top = [];
        foreach ($rows as $row) {
            $type = (string) $row->content_type;
            /** @var (Model&Workflowable)|null $model */
            $model = $models[$type]->get($row->content_id);

            if ($model === null) {
                continue;
            }

            $top[] = [
                'type' => $type,
                'type_label' => ContentTypes::META[$type]['label'],
                'title' => ContentTitle::of($model) ?: 'Без названия',
                'views' => (int) $row->views,
                'edit_url' => ContentTypes::META[$type]['route']."/{$model->getKey()}/edit",
                'public_url' => PublicSite::urlFor($model),
            ];
        }

        return $top;
    }

    /**
     * @return list<string>
     */
    private function typesFor(User $user): array
    {
        return array_values(array_filter(
            ContentViewRecorder::TYPES,
            fn (string $type): bool => $user->can(ContentTypes::META[$type]['module'].'.view'),
        ));
    }
}
