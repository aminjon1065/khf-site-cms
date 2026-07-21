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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->getAllPermissions()->isNotEmpty(), 403);

        $activeAlerts = $this->alertQuery($user)->active()->with(['regions', 'author'])->orderByDesc('severity')->get();

        return Inertia::render('dashboard', [
            'metrics' => $this->metrics($user),
            'operationalLevel' => $this->operationalLevel($activeAlerts),
            'activeAlerts' => AlertResource::collection($activeAlerts->take(4))->resolve(),
            'regionStatuses' => Region::query()
                ->when($user->hasRole('regional_editor'), fn (Builder $query) => $query->whereKey($user->region_id ?? 0))
                ->orderBy('sort')
                ->get()
                ->map(fn (Region $r): array => [
                    'id' => $r->id,
                    'name' => $r->getTranslation('name', 'ru'),
                    'status' => $r->status,
                ])->all(),
            'tasks' => $this->attentionTasks($user),
            'activity' => $this->recentActivity($user),
            'calendar' => $this->calendar($user),
            'today' => now()->isoFormat('dddd, D MMMM YYYY'),
            'greetingName' => Str::of($user->name)->explode(' ')->first(),
        ]);
    }

    /**
     * @return array<int, array{key: string, value: int, label: string, tone: string|null}>
     */
    private function metrics(User $user): array
    {
        $incompleteTranslations = $this->alertQuery($user)
            ->whereIn('status', ['published', 'review', 'scheduled', 'updated'])
            ->get()
            ->filter(fn (Alert $a): bool => collect($a->languageCompleteness())->contains(fn (int $p): bool => $p < 100))
            ->count();

        return [
            ['key' => 'active', 'value' => $this->alertQuery($user)->active()->count(), 'label' => 'активных предупреждения', 'tone' => 'warn'],
            ['key' => 'drafts', 'value' => $this->alertQuery($user)->where('status', ContentStatus::Draft->value)->count() + $this->newsQuery($user)->where('status', 'draft')->count(), 'label' => 'черновиков', 'tone' => null],
            ['key' => 'review', 'value' => $this->alertQuery($user)->whereIn('status', ['review', 'translation_check'])->count() + $this->newsQuery($user)->where('status', 'review')->count(), 'label' => 'на согласовании', 'tone' => null],
            ['key' => 'scheduled', 'value' => $this->alertQuery($user)->where('status', 'scheduled')->count() + $this->newsQuery($user)->where('status', 'scheduled')->count(), 'label' => 'запланировано', 'tone' => null],
            ['key' => 'published_month', 'value' => $this->newsQuery($user)->where('status', 'published')->whereMonth('published_at', now()->month)->count() + $this->alertQuery($user)->whereMonth('published_at', now()->month)->count(), 'label' => 'опубликовано за месяц', 'tone' => null],
            ['key' => 'translations', 'value' => $incompleteTranslations, 'label' => 'незавершённых переводов', 'tone' => 'danger'],
        ];
    }

    /**
     * @param  Collection<int, Alert>  $activeAlerts
     */
    private function operationalLevel(Collection $activeAlerts): string
    {
        if ($activeAlerts->isEmpty()) {
            return 'calm';
        }

        $hasCritical = $activeAlerts->contains(fn (Alert $alert): bool => $alert->severity->value === 'critical');

        return $hasCritical ? 'critical' : 'active';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function attentionTasks(User $user): array
    {
        $tasks = [];

        foreach ($this->alertQuery($user)->whereIn('status', ['review', 'translation_check'])->with('author')->limit(3)->get() as $alert) {
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

        foreach ($this->alertQuery($user)->active()->whereNotNull('ends_at')->where('ends_at', '<=', now()->addDays(2))->limit(2)->get() as $alert) {
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
    private function recentActivity(User $user): array
    {
        if (! $user->can('users.view')) {
            return [];
        }

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
    private function calendar(User $user): array
    {
        $events = [];
        $start = now()->startOfDay();
        $end = now()->addDays(4)->endOfDay();

        foreach ($this->newsQuery($user)
            ->where(fn (Builder $query) => $query
                ->whereBetween('scheduled_at', [$start, $end])
                ->orWhereBetween('published_at', [$start, $end]))
            ->limit(6)
            ->get() as $news) {
            $when = $news->scheduled_at ?? $news->published_at;
            $events[] = [
                'date' => $when?->toDateString(),
                'time' => $when?->format('H:i'),
                'label' => 'Новость: '.Str::limit($news->getTranslation('title', 'ru'), 48),
                'tone' => $news->status === ContentStatus::Published ? 'ok' : 'accent',
            ];
        }

        foreach ($this->alertQuery($user)->active()->whereBetween('ends_at', [$start, $end])->limit(4)->get() as $alert) {
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

    /**
     * @return Builder<Alert>
     */
    private function alertQuery(User $user): Builder
    {
        return Alert::query()->accessibleTo($user);
    }

    /**
     * @return Builder<News>
     */
    private function newsQuery(User $user): Builder
    {
        return News::query()->accessibleTo($user);
    }

    private function initials(string $name): string
    {
        return Str::of($name)->explode(' ')->take(2)->map(fn ($p) => Str::upper(Str::substr($p, 0, 1)))->implode('');
    }
}
