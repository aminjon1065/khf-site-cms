<?php

namespace App\Http\Controllers\Cms;

use App\Enums\AnnouncementKind;
use App\Enums\ContentStatus;
use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Announcement\AnnouncementRequest;
use App\Http\Resources\AnnouncementResource;
use App\Models\Announcement;
use App\Models\User;
use App\Services\WorkflowService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AnnouncementController extends Controller
{
    public function __construct(private readonly WorkflowService $workflow) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Announcement::class);

        $view = $request->string('view', 'all')->toString();
        $perPage = (int) $request->integer('per_page', 25);

        $query = Announcement::query()->with('author');
        $this->applyView($query, $view, $request->user());
        $this->applyFilters($query, $request);
        $this->applySort($query, $request);

        $announcements = $query->paginate($perPage)->withQueryString();

        return Inertia::render('announcements/index', [
            'announcements' => AnnouncementResource::collection($announcements->items())->resolve(),
            'meta' => [
                'from' => $announcements->firstItem(),
                'to' => $announcements->lastItem(),
                'total' => $announcements->total(),
                'per_page' => $announcements->perPage(),
                'current_page' => $announcements->currentPage(),
                'last_page' => $announcements->lastPage(),
                'prev' => $announcements->previousPageUrl(),
                'next' => $announcements->nextPageUrl(),
            ],
            'filters' => [
                'view' => $view,
                'search' => $request->string('search')->toString(),
                'status' => $request->string('status')->toString(),
                'kind' => $request->string('kind')->toString(),
                'sort' => $request->string('sort')->toString(),
                'dir' => $request->string('dir', 'desc')->toString(),
            ],
            'savedViews' => $this->savedViewCounts($request),
            'options' => ['statuses' => ContentStatus::options(), 'kinds' => AnnouncementKind::options()],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Announcement::class);

        return Inertia::render('announcements/form', [
            'announcement' => null,
            'reference' => $this->reference(),
        ]);
    }

    public function edit(Announcement $announcement): Response
    {
        $this->authorize('update', $announcement);

        $announcement->load('author');

        return Inertia::render('announcements/form', [
            'announcement' => $this->formPayload($announcement),
            'reference' => $this->reference(),
        ]);
    }

    public function store(AnnouncementRequest $request): RedirectResponse
    {
        $this->authorize('create', Announcement::class);

        $announcement = new Announcement;
        $this->fill($announcement, $request);
        $announcement->author_id = $request->user()?->id;
        $announcement->status = ContentStatus::Draft;
        $announcement->save();

        $this->runPublishAction($announcement, $request);

        return redirect('/announcements')->with('success', $this->savedMessage($request));
    }

    public function update(AnnouncementRequest $request, Announcement $announcement): RedirectResponse
    {
        $this->authorize('update', $announcement);

        $this->fill($announcement, $request);
        $announcement->save();

        $this->runPublishAction($announcement, $request);

        return redirect('/announcements')->with('success', $this->savedMessage($request));
    }

    public function destroy(Announcement $announcement): RedirectResponse
    {
        $this->authorize('delete', $announcement);
        $announcement->delete();

        return back()->with('success', 'Объявление удалено.');
    }

    public function duplicate(Announcement $announcement): RedirectResponse
    {
        $this->authorize('create', Announcement::class);

        $copy = $announcement->replicate(['published_at']);
        $title = $announcement->getTranslations('title');
        $title['ru'] = ($title['ru'] ?? '').' (копия)';
        $copy->setTranslations('title', $title);
        $copy->status = ContentStatus::Draft;
        $copy->author_id = request()->user()?->id;
        $copy->save();

        return redirect('/announcements/'.$copy->id.'/edit')->with('success', 'Создана копия объявления.');
    }

    public function publish(Request $request, Announcement $announcement): RedirectResponse
    {
        $this->authorize('publish', $announcement);
        $this->workflow->transition($announcement, ContentStatus::Published, $request->user(), force: true);

        return back()->with('success', 'Объявление опубликовано.');
    }

    public function unpublish(Request $request, Announcement $announcement): RedirectResponse
    {
        $this->authorize('publish', $announcement);
        $validated = $request->validate(['comment' => ['required', 'string', 'min:3']], [
            'comment.required' => 'Укажите причину снятия с публикации.',
        ]);
        $this->workflow->transition($announcement, ContentStatus::Archived, $request->user(), $validated['comment'], force: true);

        return back()->with('success', 'Объявление снято с публикации.');
    }

    // ---------------------------------------------------------------- helpers

    /**
     * @param  Builder<Announcement>  $query
     */
    private function applyView(Builder $query, string $view, ?User $user): void
    {
        match ($view) {
            'published' => $query->public(),
            'review' => $query->whereIn('status', [ContentStatus::Review->value, ContentStatus::TranslationCheck->value, ContentStatus::Approved->value]),
            'drafts' => $query->whereIn('status', [ContentStatus::Draft->value, ContentStatus::Returned->value]),
            'mine' => $query->where('author_id', $user?->id),
            default => null,
        };
    }

    /**
     * @param  Builder<Announcement>  $query
     */
    private function applyFilters(Builder $query, Request $request): void
    {
        if ($search = $request->string('search')->toString()) {
            $query->where(function (Builder $q) use ($search): void {
                $q->where('title->ru', 'like', "%{$search}%")
                    ->orWhere('title->tg', 'like', "%{$search}%")
                    ->orWhere('org', 'like', "%{$search}%");
            });
        }
        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }
        if ($kind = $request->string('kind')->toString()) {
            $query->where('kind', $kind);
        }
    }

    /**
     * @param  Builder<Announcement>  $query
     */
    private function applySort(Builder $query, Request $request): void
    {
        $dir = $request->string('dir', 'desc')->toString() === 'asc' ? 'asc' : 'desc';

        match ($request->string('sort')->toString()) {
            'status' => $query->orderBy('status', $dir),
            'deadline' => $query->orderBy('deadline', $dir),
            'kind' => $query->orderBy('kind', $dir),
            default => $query->orderByDesc('updated_at'),
        };
    }

    /**
     * @return array<int, array{key: string, label: string, count: int}>
     */
    private function savedViewCounts(Request $request): array
    {
        $views = [
            ['key' => 'all', 'label' => 'Все объявления'],
            ['key' => 'published', 'label' => 'Опубликованные'],
            ['key' => 'review', 'label' => 'На согласовании'],
            ['key' => 'drafts', 'label' => 'Черновики'],
            ['key' => 'mine', 'label' => 'Мои материалы'],
        ];

        return array_map(function (array $v) use ($request): array {
            $q = Announcement::query();
            $this->applyView($q, $v['key'], $request->user());
            $v['count'] = $q->count();

            return $v;
        }, $views);
    }

    /**
     * @return array<string, mixed>
     */
    private function reference(): array
    {
        return [
            'kinds' => AnnouncementKind::options(),
            'authors' => User::query()->role([
                RoleName::Editor->value,
                RoleName::ChiefEditor->value,
                RoleName::Admin->value,
            ])->get()->map(fn (User $u): array => ['value' => $u->id, 'label' => $u->name])->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formPayload(Announcement $announcement): array
    {
        return [
            'id' => $announcement->id,
            'title' => $announcement->getTranslations('title'),
            'body' => $announcement->getTranslations('body'),
            'kind' => $announcement->kind->value,
            'org' => $announcement->org,
            'deadline' => $announcement->deadline?->toDateString(),
            'status' => $announcement->status->value,
            'is_open' => $announcement->isOpen(),
            'published_at' => $announcement->published_at?->toIso8601String(),
            'languages' => $announcement->languageCompleteness(),
        ];
    }

    private function fill(Announcement $announcement, AnnouncementRequest $request): void
    {
        $announcement->fill([
            'kind' => $request->input('kind'),
            'org' => $request->input('org'),
            'deadline' => $request->input('deadline'),
        ]);

        foreach (['title', 'body'] as $field) {
            /** @var array<string, string|null> $values */
            $values = $request->input($field, []);
            $announcement->setTranslations($field, array_filter(
                $values,
                fn (?string $v): bool => $v !== null && trim($v) !== '',
            ));
        }
    }

    private function runPublishAction(Announcement $announcement, AnnouncementRequest $request): void
    {
        if ($request->input('action') !== 'submit') {
            return;
        }

        $user = $request->user();

        match ($request->input('publish_mode', 'review')) {
            'now' => $this->authorizeAndPublish($announcement, $user),
            default => $this->workflow->transition($announcement, ContentStatus::Review, $user, force: true),
        };
    }

    private function authorizeAndPublish(Announcement $announcement, ?User $user): void
    {
        if ($user && $user->can('publish', $announcement)) {
            $this->workflow->transition($announcement, ContentStatus::Published, $user, force: true);
        } else {
            $this->workflow->transition($announcement, ContentStatus::Review, $user, force: true);
        }
    }

    private function savedMessage(AnnouncementRequest $request): string
    {
        if ($request->input('action') !== 'submit') {
            return 'Черновик сохранён.';
        }

        return $request->input('publish_mode') === 'now'
            ? 'Объявление опубликовано.'
            : 'Объявление отправлено на согласование.';
    }
}
