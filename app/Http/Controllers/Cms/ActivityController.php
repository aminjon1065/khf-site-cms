<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ActivityController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', User::class);

        $activities = $this->query($request)->paginate(30)->withQueryString();

        return Inertia::render('activity', [
            'activities' => collect($activities->items())->map(fn (Activity $a) => $this->present($a))->all(),
            'meta' => [
                'from' => $activities->firstItem(),
                'to' => $activities->lastItem(),
                'total' => $activities->total(),
                'per_page' => $activities->perPage(),
                'prev' => $activities->previousPageUrl(),
                'next' => $activities->nextPageUrl(),
            ],
            'filters' => [
                'user' => $request->string('user')->toString(),
                'section' => $request->string('section')->toString(),
                'period' => $request->string('period', '7')->toString(),
                'critical' => $request->boolean('critical'),
            ],
            'options' => [
                'users' => User::query()->orderBy('name')->get()->map(fn (User $u) => ['value' => (string) $u->id, 'label' => $u->name])->all(),
                'sections' => Activity::query()->distinct()->pluck('log_name')->filter()->values()
                    ->map(fn ($s) => ['value' => $s, 'label' => $this->sectionLabel($s)])->all(),
                'periods' => [
                    ['value' => '1', 'label' => 'Сегодня'],
                    ['value' => '7', 'label' => '7 дней'],
                    ['value' => '30', 'label' => '30 дней'],
                    ['value' => '365', 'label' => 'За год'],
                ],
            ],
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', User::class);

        $rows = $this->query($request)->limit(5000)->get();
        $filename = 'activity-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'wb');
            if ($out === false) {
                return;
            }
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel
            fputcsv($out, ['Дата и время', 'Сотрудник', 'Действие', 'Изменение', 'IP', 'Местоположение', 'Критическое']);

            foreach ($rows as $activity) {
                $data = $this->present($activity);
                fputcsv($out, [
                    $data['datetime'],
                    $data['who'],
                    $data['action'],
                    $data['change'],
                    $data['ip'],
                    $data['location'],
                    $data['is_critical'] ? 'да' : 'нет',
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return Builder<Activity>
     */
    private function query(Request $request): Builder
    {
        $query = Activity::query()->with('causer')->latest();

        if ($userId = $request->string('user')->toString()) {
            $query->where('causer_id', $userId)->where('causer_type', User::class);
        }
        if ($section = $request->string('section')->toString()) {
            $query->where('log_name', $section);
        }
        if ($request->boolean('critical')) {
            $query->where('is_critical', true);
        }

        $days = (int) $request->string('period', '7')->toString();
        if ($days > 0) {
            $query->where('created_at', '>=', now()->subDays($days));
        }

        return $query;
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Activity $activity): array
    {
        $causer = $activity->causer;

        return [
            'id' => $activity->id,
            'datetime' => $activity->created_at?->isoFormat('DD.MM.YYYY, HH:mm') ?? '',
            'who' => $causer instanceof User ? $causer->name : 'система',
            'action' => $activity->description,
            'change' => $this->change($activity),
            'ip' => $activity->ip_address ?? '—',
            'location' => $activity->location ?? '',
            'section' => $this->sectionLabel($activity->log_name),
            'is_critical' => (bool) $activity->is_critical,
        ];
    }

    private function change(Activity $activity): string
    {
        /** @var array<string, mixed> $props */
        $props = $activity->properties->toArray();
        $new = $props['attributes'] ?? [];
        $old = $props['old'] ?? [];

        if (! is_array($new) || $new === []) {
            return isset($props['comment']) && is_string($props['comment']) ? $props['comment'] : '';
        }

        $parts = [];
        foreach ($new as $key => $value) {
            $before = is_array($old) ? ($old[$key] ?? '—') : '—';
            $parts[] = "{$key}: {$before} → {$value}";
        }

        return implode(' · ', $parts);
    }

    private function sectionLabel(?string $logName): string
    {
        return match ($logName) {
            'alerts' => 'Предупреждения',
            'news' => 'Новости',
            'instructions' => 'Инструкции',
            'documents' => 'Документы',
            'users' => 'Пользователи',
            'settings' => 'Настройки',
            'home' => 'Главная страница',
            default => $logName ?? 'Система',
        };
    }
}
