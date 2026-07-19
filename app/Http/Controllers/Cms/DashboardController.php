<?php

namespace App\Http\Controllers\Cms;

use App\Enums\ContentStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\AlertResource;
use App\Models\Activity;
use App\Models\Alert;
use App\Models\News;
use App\Models\Region;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $activeAlerts = Alert::query()->active()->with(['regions', 'author'])->orderByDesc('severity')->get();

        return Inertia::render('dashboard', [
            'metrics' => $this->metrics(),
            'operationalLevel' => $this->operationalLevel($activeAlerts->count()),
            'activeAlerts' => AlertResource::collection($activeAlerts->take(4))->resolve(),
            'regionStatuses' => Region::query()->orderBy('sort')->get()->map(fn (Region $r): array => [
                'id' => $r->id,
                'name' => $r->getTranslation('name', 'ru'),
                'status' => $r->status,
            ])->all(),
            'tasks' => $this->attentionTasks(),
            'activity' => $this->recentActivity(),
            'calendar' => $this->calendar(),
            'today' => now()->isoFormat('dddd, D MMMM YYYY'),
            'greetingName' => Str::of($user->name)->explode(' ')->first(),
        ]);
    }

    /**
     * @return array<int, array{key: string, value: int, label: string, tone: string|null}>
     */
    private function metrics(): array
    {
        $incompleteTranslations = Alert::query()
            ->whereIn('status', ['published', 'review', 'scheduled', 'updated'])
            ->get()
            ->filter(fn (Alert $a): bool => collect($a->languageCompleteness())->contains(fn (int $p): bool => $p < 100))
            ->count();

        return [
            ['key' => 'active', 'value' => Alert::query()->active()->count(), 'label' => 'активных предупреждения', 'tone' => 'warn'],
            ['key' => 'drafts', 'value' => Alert::query()->where('status', ContentStatus::Draft->value)->count() + News::query()->where('status', 'draft')->count(), 'label' => 'черновиков', 'tone' => null],
            ['key' => 'review', 'value' => Alert::query()->whereIn('status', ['review', 'translation_check'])->count() + News::query()->where('status', 'review')->count(), 'label' => 'на согласовании', 'tone' => null],
            ['key' => 'scheduled', 'value' => Alert::query()->where('status', 'scheduled')->count() + News::query()->where('status', 'scheduled')->count(), 'label' => 'запланировано', 'tone' => null],
            ['key' => 'published_month', 'value' => News::query()->where('status', 'published')->whereMonth('published_at', now()->month)->count() + Alert::query()->whereMonth('published_at', now()->month)->count(), 'label' => 'опубликовано за месяц', 'tone' => null],
            ['key' => 'translations', 'value' => $incompleteTranslations, 'label' => 'незавершённых переводов', 'tone' => 'danger'],
        ];
    }

    private function operationalLevel(int $activeCount): string
    {
        if ($activeCount === 0) {
            return 'calm';
        }

        $hasCritical = Alert::query()->active()->where('severity', 'critical')->exists();

        return $hasCritical ? 'critical' : 'active';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function attentionTasks(): array
    {
        $tasks = [];

        foreach (Alert::query()->whereIn('status', ['review', 'translation_check'])->with('author')->limit(3)->get() as $alert) {
            $authorName = is_string($n = data_get($alert, 'author.name')) ? $n : '—';
            $tasks[] = [
                'kind' => 'urgent',
                'kind_label' => 'Срочно',
                'title' => $alert->getTranslation('title', 'ru', false) ?: $alert->internal_title,
                'meta' => 'Предупреждение · автор '.$authorName.' · ожидает согласования',
                'due' => 'сегодня',
                'due_tone' => 'danger',
                'action' => 'Согласовать',
                'href' => '/approvals',
            ];
        }

        foreach (Alert::query()->active()->whereNotNull('ends_at')->where('ends_at', '<=', now()->addDays(2))->limit(2)->get() as $alert) {
            $tasks[] = [
                'kind' => 'expiring',
                'kind_label' => 'Истекает',
                'title' => ($alert->getTranslation('title', 'ru', false) ?: $alert->internal_title).' — срок действия истекает',
                'meta' => 'Предупреждение · завершается '.$alert->ends_at?->isoFormat('D.MM в HH:mm'),
                'due' => $alert->ends_at?->diffForHumans(['parts' => 1]) ?? '',
                'due_tone' => 'warn',
                'action' => 'Продлить',
                'href' => '/alerts/'.$alert->id.'/edit',
            ];
        }

        return array_slice($tasks, 0, 4);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recentActivity(): array
    {
        return Activity::query()->with('causer')->latest()->limit(5)->get()->map(function (Activity $a): array {
            $causer = $a->causer;
            $name = $causer instanceof User ? $causer->name : 'система';

            return [
                'initials' => $this->initials($name),
                'text' => $a->description,
                'who' => $name,
                'when' => $a->created_at?->isoFormat('D MMM, HH:mm') ?? '',
                'section' => $a->log_name ?? '',
            ];
        })->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function calendar(): array
    {
        $events = [];
        $start = now()->startOfDay();
        $end = now()->addDays(4)->endOfDay();

        foreach (News::query()->whereBetween('scheduled_at', [$start, $end])->orWhereBetween('published_at', [$start, $end])->limit(6)->get() as $news) {
            $when = $news->scheduled_at ?? $news->published_at;
            $events[] = [
                'date' => $when?->toDateString(),
                'time' => $when?->format('H:i'),
                'label' => 'Новость: '.Str::limit($news->getTranslation('title', 'ru'), 48),
                'tone' => $news->status === ContentStatus::Published ? 'ok' : 'accent',
            ];
        }

        foreach (Alert::query()->active()->whereBetween('ends_at', [$start, $end])->limit(4)->get() as $alert) {
            $events[] = [
                'date' => $alert->ends_at?->toDateString(),
                'time' => $alert->ends_at?->format('H:i'),
                'label' => 'Завершение: '.Str::limit($alert->getTranslation('title', 'ru', false) ?: $alert->internal_title, 44),
                'tone' => 'warn',
            ];
        }

        usort($events, fn ($a, $b) => ($a['date'].$a['time']) <=> ($b['date'].$b['time']));

        return $events;
    }

    private function initials(string $name): string
    {
        return Str::of($name)->explode(' ')->take(2)->map(fn ($p) => Str::upper(Str::substr($p, 0, 1)))->implode('');
    }
}
