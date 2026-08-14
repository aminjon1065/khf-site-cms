<?php

namespace App\Http\Controllers\Cms;

use App\Enums\ContentStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\AlertResource;
use App\Models\Activity;
use App\Models\Alert;
use App\Models\Announcement;
use App\Models\Document;
use App\Models\Instruction;
use App\Models\News;
use App\Models\Page;
use App\Models\Project;
use App\Models\Region;
use App\Models\User;
use App\Support\ContentTypes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
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
            'taskCenter' => $this->taskCenter($user),
            'activity' => $this->recentActivity($user),
            'calendar' => $this->calendar($user),
            'today' => now()->isoFormat('dddd, D MMMM YYYY'),
            'greetingName' => Str::of($user->name)->explode(' ')->first(),
        ]);
    }

    /**
     * @return array<int, array{key: string, value: int, label: string, tone: string|null, href: string|null}>
     */
    private function metrics(User $user): array
    {
        $incompleteTranslations = $this->alertQuery($user)
            ->whereIn('status', ['published', 'review', 'scheduled', 'updated'])
            ->get()
            ->filter(fn (Alert $a): bool => collect($a->languageCompleteness())->contains(fn (int $p): bool => $p < 100))
            ->count();

        // D-6 (CMS_AUDIT.md P2): drafts/review/published_month/translations
        // used to only ever count Alert + News. Alert and News keep their
        // original expressions above/below untouched (each has a small
        // pre-existing quirk — e.g. the alert `published_month` count has
        // never filtered by status — that this expansion isn't meant to
        // change); this loop adds Instruction/Document/Project/Announcement/
        // Page into the same four totals, which were previously silently
        // excluded from all of them.
        $byType = [
            'alert' => [
                'drafts' => $this->alertQuery($user)->where('status', ContentStatus::Draft->value)->count(),
                'review' => $this->alertQuery($user)->whereIn('status', ['review', 'translation_check'])->count(),
                'scheduled' => $this->alertQuery($user)->where('status', 'scheduled')->count(),
                'published_month' => $this->alertQuery($user)->whereMonth('published_at', now()->month)->count(),
                'translations' => $incompleteTranslations,
            ],
            'news' => [
                'drafts' => $this->newsQuery($user)->where('status', 'draft')->count(),
                'review' => $this->newsQuery($user)->where('status', 'review')->count(),
                'scheduled' => $this->newsQuery($user)->where('status', 'scheduled')->count(),
                'published_month' => $this->newsQuery($user)->where('status', 'published')->whereMonth('published_at', now()->month)->count(),
                'translations' => 0,
            ],
        ];

        foreach (ContentTypes::MAP as $type => $modelClass) {
            if (in_array($type, ['alert', 'news'], true)) {
                continue;
            }

            $translations = 0;
            if (method_exists($modelClass, 'languageCompleteness')) {
                $translations = $modelClass::query()->accessibleTo($user)
                    ->whereIn('status', ['published', 'review', 'scheduled', 'updated'])
                    ->get()
                    ->filter(fn (Model $m): bool => collect($m->languageCompleteness())->contains(fn (int $p): bool => $p < 100))
                    ->count();
            }

            $byType[$type] = [
                'drafts' => $modelClass::query()->accessibleTo($user)->where('status', ContentStatus::Draft->value)->count(),
                'review' => $modelClass::query()->accessibleTo($user)->whereIn('status', [ContentStatus::Review->value, ContentStatus::TranslationCheck->value])->count(),
                'scheduled' => 0,
                'published_month' => $modelClass::query()->accessibleTo($user)->where('status', ContentStatus::Published->value)->whereMonth('published_at', now()->month)->count(),
                'translations' => $translations,
            ];
        }

        return [
            ['key' => 'active', 'value' => $this->alertQuery($user)->active()->count(), 'label' => 'активных предупреждения', 'tone' => 'warn', 'href' => $this->metricHref($user, 'active', $byType)],
            ['key' => 'drafts', 'value' => $this->metricSum($byType, 'drafts'), 'label' => 'черновиков', 'tone' => null, 'href' => $this->metricHref($user, 'drafts', $byType)],
            ['key' => 'review', 'value' => $this->metricSum($byType, 'review'), 'label' => 'на согласовании', 'tone' => null, 'href' => $this->metricHref($user, 'review', $byType)],
            // Not expanded to the other 5 types: only Alert/News have a
            // `scheduled_at` column and a scheduler at all (see
            // ProcessScheduledContent) — the rest structurally never reach
            // status=scheduled, so looping them would just add 0.
            ['key' => 'scheduled', 'value' => $this->metricSum($byType, 'scheduled'), 'label' => 'запланировано', 'tone' => null, 'href' => $this->metricHref($user, 'scheduled', $byType)],
            ['key' => 'published_month', 'value' => $this->metricSum($byType, 'published_month'), 'label' => 'опубликовано за месяц', 'tone' => null, 'href' => $this->metricHref($user, 'published_month', $byType)],
            ['key' => 'translations', 'value' => $this->metricSum($byType, 'translations'), 'label' => 'незавершённых переводов', 'tone' => 'danger', 'href' => $this->metricHref($user, 'translations', $byType)],
        ];
    }

    /**
     * @param  array<string, array{drafts: int, review: int, scheduled: int, published_month: int, translations: int}>  $byType
     */
    private function metricSum(array $byType, string $metric): int
    {
        return array_sum(array_map(fn (array $row): int => $row[$metric], $byType));
    }

    /**
     * Карточка открывает уже существующий срез — сохранённый вид списка,
     * центр согласования или очередь переводов — и только если роль
     * может открыть этот экран.
     *
     * @param  array<string, array{drafts: int, review: int, scheduled: int, published_month: int, translations: int}>  $byType
     */
    private function metricHref(User $user, string $key, array $byType): ?string
    {
        return match ($key) {
            'active' => $user->can('alerts.view')
                ? route('alerts.index', ['view' => 'active'], false)
                : null,
            'translations' => $this->translationsHref($user),
            'review' => $this->reviewHref($user, $byType),
            'drafts' => $this->metricListHref($user, $byType, 'drafts', 'drafts', ['news', 'page', 'announcement', 'project', 'document', 'instruction', 'alert']),
            'scheduled' => $this->metricListHref($user, $byType, 'scheduled', 'scheduled', ['news', 'alert']),
            'published_month' => $this->metricListHref($user, $byType, 'published_month', 'published', ['news', 'page', 'announcement', 'project', 'document', 'instruction', 'alert']),
            default => null,
        };
    }

    private function translationsHref(User $user): ?string
    {
        foreach (['news', 'pages', 'projects', 'instructions', 'announcements', 'documents'] as $module) {
            if ($user->can("{$module}.edit")) {
                return route('editorial.translations', [], false);
            }
        }

        return null;
    }

    /**
     * @param  array<string, array{drafts: int, review: int, scheduled: int, published_month: int, translations: int}>  $byType
     */
    private function reviewHref(User $user, array $byType): ?string
    {
        foreach (ContentTypes::MAP as $type => $_modelClass) {
            if ($user->can(ContentTypes::module($type).'.approve')) {
                return route('approvals', [], false);
            }
        }

        return $this->metricListHref($user, $byType, 'review', 'review', ['news', 'alert', 'page', 'announcement', 'project', 'document', 'instruction']);
    }

    /**
     * @param  array<string, array{drafts: int, review: int, scheduled: int, published_month: int, translations: int}>  $byType
     * @param  list<string>  $fallback
     */
    private function metricListHref(User $user, array $byType, string $metric, string $view, array $fallback): ?string
    {
        $chosen = null;
        $max = 0;

        foreach (ContentTypes::MAP as $type => $_modelClass) {
            if (! $user->can(ContentTypes::module($type).'.view')) {
                continue;
            }

            $count = $byType[$type][$metric] ?? 0;
            if ($count > $max) {
                $chosen = $type;
                $max = $count;
            }
        }

        if ($chosen === null) {
            foreach ($fallback as $type) {
                if ($user->can(ContentTypes::module($type).'.view')) {
                    $chosen = $type;
                    break;
                }
            }
        }

        if ($chosen === null) {
            return null;
        }

        return $this->typeListUrl($chosen, $view);
    }

    private function typeListUrl(string $type, string $view): string
    {
        $module = ContentTypes::module($type);

        if ($type === 'alert') {
            return match ($view) {
                'drafts' => route('alerts.index', ['view' => 'all', 'status' => ContentStatus::Draft->value], false),
                'published' => route('alerts.index', ['view' => 'all', 'status' => ContentStatus::Published->value], false),
                default => route('alerts.index', ['view' => $view], false),
            };
        }

        return route("{$module}.index", ['view' => $view], false);
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

        foreach (ContentTypes::MAP as $type => $modelClass) {
            if (! $user->can(ContentTypes::module($type).'.approve')) {
                continue;
            }

            foreach ($modelClass::query()->accessibleTo($user)
                ->whereIn('status', [ContentStatus::Review->value, ContentStatus::TranslationCheck->value])
                ->with('author')
                ->limit(3)
                ->get() as $model) {
                if (! $user->can('approve', $model)) {
                    continue;
                }

                $authorName = is_string($n = data_get($model, 'author.name')) ? $n : '—';
                $tasks[] = [
                    'id' => "approval-{$type}-{$model->getKey()}",
                    'priority' => 20,
                    'kind' => 'urgent',
                    'kind_label' => 'Согласовать',
                    'title' => $this->typeTitle($model, $type),
                    'meta' => ContentTypes::label($type).' · автор '.$authorName.' · ожидает согласования',
                    'due' => 'сегодня',
                    'due_tone' => 'danger',
                    'action' => 'Согласовать',
                    'href' => route('approvals', [], false),
                ];
            }
        }

        foreach (ContentTypes::MAP as $type => $modelClass) {
            $module = ContentTypes::module($type);

            if (! $user->can("{$module}.edit")) {
                continue;
            }

            foreach ($modelClass::query()
                ->accessibleTo($user)
                ->where('author_id', $user->id)
                ->whereIn('status', [ContentStatus::Returned->value, ContentStatus::Draft->value])
                ->orderByRaw("CASE status WHEN 'returned' THEN 0 ELSE 1 END")
                ->oldest('updated_at')
                ->limit(2)
                ->get() as $model) {
                if (! $user->can('update', $model)) {
                    continue;
                }

                $isReturned = $model->status === ContentStatus::Returned;
                $tasks[] = [
                    'id' => ($isReturned ? 'returned' : 'draft')."-{$type}-{$model->getKey()}",
                    'priority' => $isReturned ? 10 : 50,
                    'kind' => $isReturned ? 'returned' : 'draft',
                    'kind_label' => $isReturned ? 'Исправить' : 'Черновик',
                    'title' => $this->typeTitle($model, $type),
                    'meta' => ContentTypes::label($type).($isReturned
                        ? ' · возвращено с проверки'
                        : ' · ваш незавершённый материал'),
                    'due' => $model->updated_at?->diffForHumans(['parts' => 1]) ?? '',
                    'due_tone' => $isReturned ? 'danger' : 'neutral',
                    'action' => $isReturned ? 'Исправить' : 'Продолжить',
                    'href' => route("{$module}.edit", $model, false),
                ];
            }

            if ($user->can("{$module}.approve") || ! method_exists($modelClass, 'languageCompleteness')) {
                continue;
            }

            foreach ($modelClass::query()
                ->accessibleTo($user)
                ->where('status', ContentStatus::TranslationCheck->value)
                ->oldest('updated_at')
                ->limit(2)
                ->get()
                ->filter(fn (Model $model): bool => collect($model->languageCompleteness())
                    ->contains(fn (int $percent): bool => $percent < 100)) as $model) {
                if (! $user->can('update', $model)) {
                    continue;
                }

                $tasks[] = [
                    'id' => "translation-{$type}-{$model->getKey()}",
                    'priority' => 30,
                    'kind' => 'translation',
                    'kind_label' => 'Перевести',
                    'title' => $this->typeTitle($model, $type),
                    'meta' => ContentTypes::label($type).' · не завершены обязательные локали',
                    'due' => $model->updated_at?->diffForHumans(['parts' => 1]) ?? '',
                    'due_tone' => 'warn',
                    'action' => 'Перевести',
                    'href' => route("{$module}.edit", $model, false),
                ];
            }
        }

        if ($user->can('alerts.edit')) {
            foreach ($this->alertQuery($user)->active()->whereNotNull('ends_at')->where('ends_at', '<=', now()->addDays(2))->limit(2)->get() as $alert) {
                $tasks[] = [
                    'id' => "expiring-alert-{$alert->id}",
                    'priority' => 40,
                    'kind' => 'expiring',
                    'kind_label' => 'Истекает',
                    'title' => ($alert->getTranslation('title', 'ru', false) ?: $alert->internal_title).' — срок действия истекает',
                    'meta' => 'Предупреждение · завершается '.$alert->ends_at?->isoFormat('D.MM в HH:mm'),
                    'due' => $alert->ends_at?->diffForHumans(['parts' => 1]) ?? '',
                    'due_tone' => 'warn',
                    'action' => 'Продлить',
                    'href' => route('alerts.edit', $alert, false),
                ];
            }
        }

        usort($tasks, fn (array $left, array $right): int => [$left['priority'], $left['id']] <=> [$right['priority'], $right['id']]);

        return array_map(function (array $task): array {
            unset($task['priority']);

            return $task;
        }, array_slice($tasks, 0, 6));
    }

    /**
     * @return array{href: string, label: string}|null
     */
    private function taskCenter(User $user): ?array
    {
        foreach (ContentTypes::MAP as $type => $_modelClass) {
            if ($user->can(ContentTypes::module($type).'.approve')) {
                return [
                    'href' => route('approvals', [], false),
                    'label' => 'Центр согласования',
                ];
            }
        }

        foreach (['news', 'pages', 'projects', 'instructions', 'announcements', 'documents'] as $module) {
            if ($user->can("{$module}.edit")) {
                return [
                    'href' => route('editorial.translations', [], false),
                    'label' => 'Очередь переводов',
                ];
            }
        }

        return null;
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

        // D-6 (CMS_AUDIT.md P2): Instruction/Document/Project/Announcement/
        // Page had zero presence in the calendar before — not even a
        // recently/soon-published entry from their own `published_at`.
        // News and Alert keep their existing, more specific branches above
        // (scheduled-or-published, and expiry respectively) untouched.
        foreach (ContentTypes::MAP as $type => $modelClass) {
            if (in_array($type, ['news', 'alert'], true)) {
                continue;
            }

            foreach ($modelClass::query()->accessibleTo($user)
                ->whereBetween('published_at', [$start, $end])
                ->limit(2)
                ->get() as $model) {
                /** @var Instruction|Document|Project|Announcement|Page $model */
                $events[] = [
                    'date' => $model->published_at?->toDateString(),
                    'time' => $model->published_at?->format('H:i'),
                    'label' => ContentTypes::label($type).': '.Str::limit($this->typeTitle($model, $type), 44),
                    'tone' => 'ok',
                ];
            }
        }

        usort($events, fn ($a, $b) => ($a['date'].$a['time']) <=> ($b['date'].$b['time']));

        return $events;
    }

    /**
     * Localized display title for any workflow content type, keyed the
     * same way as ApprovalController's own (separate, not shared, to avoid
     * coupling the two controllers) title resolution.
     */
    private function typeTitle(Model $model, string $type): string
    {
        if ($model instanceof Alert) {
            return $model->getTranslation('title', 'ru', false) ?: $model->internal_title;
        }

        $field = in_array($type, ['instruction', 'document'], true) ? 'name' : 'title';

        /** @var Alert|News|Instruction|Document|Project|Announcement|Page $model */
        return $model->getTranslation($field, 'ru', false) ?: '—';
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
