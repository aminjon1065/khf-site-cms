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
use App\Services\AlertMapService;
use App\Support\ContentLocales;
use App\Support\ContentTitle;
use App\Support\ContentTypes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(private readonly AlertMapService $alertMap) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->getAllPermissions()->isNotEmpty(), 403);

        $activeAlerts = $this->alertQuery($user)->active()->with(['regions', 'author'])->orderByDesc('severity')->get();

        return Inertia::render('dashboard', [
            'metrics' => $this->metrics($user),
            'operationalLevel' => $this->operationalLevel($activeAlerts),
            'activeAlerts' => AlertResource::collection($activeAlerts->take(4))->resolve(),
            'regionStatuses' => $this->regionStatuses($user, $activeAlerts),
            'tasks' => $this->attentionTasks($user),
            'taskCenter' => $this->taskCenter($user),
            'activity' => $this->recentActivity($user),
            'calendar' => $this->calendar($user),
            'today' => now()->isoFormat('dddd, D MMMM YYYY'),
            'greetingName' => Str::of($user->name)->explode(' ')->first(),
        ]);
    }

    /**
     * Each region's situation as the site shows it: derived from the active
     * alerts that touch it, the same way as the public map and «Оперативная
     * обстановка» — not from the region's own stored status.
     *
     * @param  EloquentCollection<int, Alert>  $activeAlerts
     * @return list<array{key: string, name: string, level: string, count: int}>
     */
    private function regionStatuses(User $user, EloquentCollection $activeAlerts): array
    {
        $regions = Region::query()
            ->when($user->hasRole('regional_editor'), fn (Builder $query) => $query->whereKey($user->region_id ?? 0))
            ->orderBy('sort')
            ->get();

        return array_map(
            fn (array $region): array => [
                'key' => $region['key'],
                'name' => $region['name'],
                'level' => $region['level'],
                'count' => $region['count'],
            ],
            $this->alertMap->snapshotFor($activeAlerts, $regions, 'ru')['regions'],
        );
    }

    /**
     * The translation metric covers materials of the last 30 days — published
     * then, or, if not yet published, created then: those are what readers
     * see now. Counting the whole one-language archive gave a red number in
     * the hundreds that never went down and meant nothing.
     *
     * @param  Builder<covariant Model>  $query
     */
    private function publishedRecently(Builder $query): void
    {
        $since = now()->subDays(30);

        $query->where('published_at', '>=', $since)
            ->orWhere(fn (Builder $unpublished) => $unpublished
                ->whereNull('published_at')
                ->where('created_at', '>=', $since));
    }

    /**
     * @return array<int, array{key: string, value: int, label: string, tone: string|null, href: string|null}>
     */
    private function metrics(User $user): array
    {
        $incompleteTranslations = $this->alertQuery($user)
            ->whereIn('status', ['published', 'review', 'scheduled', 'updated'])
            ->where(fn (Builder $query) => $this->publishedRecently($query))
            ->get()
            ->filter(fn (Alert $a): bool => ContentLocales::missingRequired($a->languageCompleteness()) !== [])
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
                'published_month' => $this->alertQuery($user)->whereYear('published_at', now()->year)->whereMonth('published_at', now()->month)->count(),
                'translations' => $incompleteTranslations,
            ],
            'news' => [
                'drafts' => $this->newsQuery($user)->where('status', 'draft')->count(),
                'review' => $this->newsQuery($user)->where('status', 'review')->count(),
                'scheduled' => $this->newsQuery($user)->where('status', 'scheduled')->count(),
                'published_month' => $this->newsQuery($user)->where('status', 'published')->whereYear('published_at', now()->year)->whereMonth('published_at', now()->month)->count(),
                'translations' => $this->newsQuery($user)
                    ->whereIn('status', ['published', 'review', 'scheduled', 'updated'])
                    ->where(fn (Builder $query) => $this->publishedRecently($query))
                    ->get()
                    ->filter(fn (News $n): bool => ContentLocales::missingRequired($n->languageCompleteness()) !== [])
                    ->count(),
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
                    ->where(fn (Builder $query) => $this->publishedRecently($query))
                    ->get()
                    ->filter(fn (Model $m): bool => ContentLocales::missingRequired($m->languageCompleteness()) !== [])
                    ->count();
            }

            $byType[$type] = [
                'drafts' => $modelClass::query()->accessibleTo($user)->where('status', ContentStatus::Draft->value)->count(),
                'review' => $modelClass::query()->accessibleTo($user)->whereIn('status', [ContentStatus::Review->value, ContentStatus::TranslationCheck->value])->count(),
                'scheduled' => 0,
                'published_month' => $modelClass::query()->accessibleTo($user)->where('status', ContentStatus::Published->value)->whereYear('published_at', now()->year)->whereMonth('published_at', now()->month)->count(),
                'translations' => $translations,
            ];
        }

        return [
            ['key' => 'active', 'value' => $this->alertQuery($user)->active()->count(), 'label' => 'действующие предупреждения', 'tone' => 'warn', 'href' => $this->metricHref($user, 'active', $byType)],
            ['key' => 'drafts', 'value' => $this->metricSum($byType, 'drafts'), 'label' => 'черновики', 'tone' => null, 'href' => $this->metricHref($user, 'drafts', $byType)],
            ['key' => 'review', 'value' => $this->metricSum($byType, 'review'), 'label' => 'на согласовании', 'tone' => null, 'href' => $this->metricHref($user, 'review', $byType)],
            // Not expanded to the other 5 types: only Alert/News have a
            // `scheduled_at` column and a scheduler at all (see
            // ProcessScheduledContent) — the rest structurally never reach
            // status=scheduled, so looping them would just add 0.
            ['key' => 'scheduled', 'value' => $this->metricSum($byType, 'scheduled'), 'label' => 'запланировано', 'tone' => null, 'href' => $this->metricHref($user, 'scheduled', $byType)],
            ['key' => 'published_month', 'value' => $this->metricSum($byType, 'published_month'), 'label' => 'опубликовано за месяц', 'tone' => null, 'href' => $this->metricHref($user, 'published_month', $byType)],
            ['key' => 'translations', 'value' => $this->metricSum($byType, 'translations'), 'label' => 'без перевода за 30 дней', 'tone' => 'warn', 'href' => $this->metricHref($user, 'translations', $byType)],
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
                    'title' => $this->typeTitle($model),
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
                    'title' => $this->typeTitle($model),
                    'meta' => ContentTypes::label($type).($isReturned
                        ? ' · возвращено на доработку'
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
                    'title' => $this->typeTitle($model),
                    'meta' => ContentTypes::label($type).' · не завершены переводы',
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
                    'label' => 'Согласование',
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
                'label' => 'Новость: '.Str::limit(ContentTitle::of($news), 48),
                'tone' => $news->status === ContentStatus::Published ? 'ok' : 'accent',
            ];
        }

        foreach ($this->alertQuery($user)->active()->whereBetween('ends_at', [$start, $end])->limit(4)->get() as $alert) {
            $events[] = [
                'date' => $alert->ends_at?->toDateString(),
                'time' => $alert->ends_at?->format('H:i'),
                'label' => 'Завершение: '.Str::limit(ContentTitle::of($alert) ?: $alert->internal_title, 44),
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
                    'label' => ContentTypes::label($type).': '.Str::limit($this->typeTitle($model), 44),
                    'tone' => 'ok',
                ];
            }
        }

        usort($events, fn ($a, $b) => ($a['date'].$a['time']) <=> ($b['date'].$b['time']));

        return $events;
    }

    /**
     * Display title for any workflow content type, in the first language the
     * material is filled in.
     */
    private function typeTitle(Model $model): string
    {
        $title = ContentTitle::of($model);

        if ($model instanceof Alert) {
            return $title ?: $model->internal_title;
        }

        return $title ?: '—';
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
