<?php

namespace App\Http\Controllers\Cms;

use App\Concerns\HandlesPendingChanges;
use App\Enums\ContentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Page\PageRequest;
use App\Http\Resources\PageResource;
use App\Models\Page;
use App\Models\User;
use App\Services\WorkflowService;
use App\Support\EditorialContent;
use App\Support\PublicSite;
use App\Support\RichText;
use App\Support\SaveOutcome;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PageController extends Controller
{
    use HandlesPendingChanges;

    /**
     * Form keys that upload, pick or remove photos and files.
     *
     * @var list<string>
     */
    private const MEDIA_INPUTS = [];

    public function __construct(
        private readonly WorkflowService $workflow,
        private readonly EditorialContent $editorialContent,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Page::class);

        $view = $request->string('view', 'all')->toString();
        $perPage = min(max((int) $request->integer('per_page', 25), 1), 100);

        $query = Page::query()->accessibleTo($request->user())->with(['author', 'parent']);
        $this->applyView($query, $view, $request->user());
        $this->applyFilters($query, $request);
        $this->applySort($query, $request);

        $pages = $query->paginate($perPage)->withQueryString();

        return Inertia::render('pages/index', [
            'pages' => PageResource::collection($pages->items())->resolve(),
            'meta' => [
                'from' => $pages->firstItem(),
                'to' => $pages->lastItem(),
                'total' => $pages->total(),
                'per_page' => $pages->perPage(),
                'current_page' => $pages->currentPage(),
                'last_page' => $pages->lastPage(),
                'prev' => $pages->previousPageUrl(),
                'next' => $pages->nextPageUrl(),
            ],
            'filters' => [
                'view' => $view,
                'search' => $request->string('search')->toString(),
                'status' => $request->string('status')->toString(),
                'sort' => $request->string('sort')->toString(),
                'dir' => $request->string('dir', 'desc')->toString(),
            ],
            'savedViews' => $this->savedViewCounts($request),
            'options' => ['statuses' => ContentStatus::editorialOptions()],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Page::class);

        return Inertia::render('pages/form', [
            'page' => null,
        ]);
    }

    public function edit(Request $request, Page $page): Response
    {
        $this->authorize('update', $page);

        $page->load('author');

        $pending = $this->pendingChangeProps($page, $request->user());

        return Inertia::render('pages/form', [
            'page' => $this->formPayload($page),
            ...$pending,
        ]);
    }

    public function store(PageRequest $request): RedirectResponse
    {
        $this->authorize('create', Page::class);

        $page = DB::transaction(function () use ($request): Page {
            $page = new Page;
            $this->fill($page, $request);
            $page->author_id = $request->user()?->id;
            $page->status = ContentStatus::Draft;
            $page->save();

            $this->runPublishAction($page, $request);

            return $page;
        });

        return redirect('/pages')->with('success', $this->savedMessage($page, $request));
    }

    public function update(PageRequest $request, Page $page): RedirectResponse
    {
        $this->authorize('update', $page);

        $proposal = $this->proposeInsteadOfSaving(
            $page,
            $request,
            fn () => $this->fill($page, $request),
            [],
            self::MEDIA_INPUTS,
            "/pages/{$page->id}/edit",
        );

        if ($proposal !== null) {
            return $proposal;
        }

        DB::transaction(function () use ($page, $request): void {
            $this->fill($page, $request);
            $page->save();

            $this->runPublishAction($page, $request);
        });

        $this->refreshSiteIfLive($page);

        return redirect('/pages')->with('success', $this->savedMessage($page, $request));
    }

    public function destroy(Page $page): RedirectResponse
    {
        $this->authorize('delete', $page);

        if (PublicSite::isSystemPage($page->slug)) {
            return back()->with('error', 'Эту страницу нельзя удалить: её выводит раздел сайта. Если она не нужна, снимите её с публикации.');
        }

        $page->delete();

        return back()->with('success', 'Страница удалена.');
    }

    public function duplicate(Page $page): RedirectResponse
    {
        $this->authorize('view', $page);
        $this->authorize('create', Page::class);

        $copy = $page->replicate(['published_at', 'slug']);
        $title = $page->getTranslations('title');
        $title['ru'] = ($title['ru'] ?? '').' (копия)';
        $copy->setTranslations('title', $title);
        $copy->slug = '';
        $copy->status = ContentStatus::Draft;
        $copy->author_id = request()->user()?->id;
        $copy->save();

        return redirect('/pages/'.$copy->id.'/edit')->with('success', 'Создана копия страницы.');
    }

    public function publish(Request $request, Page $page): RedirectResponse
    {
        $this->authorize('publish', $page);
        $this->workflow->transition($page, ContentStatus::Published, $request->user());

        return back()->with('success', 'Страница опубликована.');
    }

    public function unpublish(Request $request, Page $page): RedirectResponse
    {
        $this->authorize('publish', $page);
        $validated = $request->validate(['comment' => ['required', 'string', 'min:3']], [
            'comment.required' => 'Укажите причину снятия с публикации.',
        ]);
        $this->workflow->transition($page, ContentStatus::Draft, $request->user(), $validated['comment']);

        return back()->with('success', 'Страница снята с публикации.');
    }

    // ---------------------------------------------------------------- helpers

    /**
     * @param  Builder<Page>  $query
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
     * @param  Builder<Page>  $query
     */
    private function applyFilters(Builder $query, Request $request): void
    {
        if ($search = $request->string('search')->toString()) {
            $query->where(function (Builder $q) use ($search): void {
                $q->where('title->ru', 'like', "%{$search}%")
                    ->orWhere('title->tg', 'like', "%{$search}%")
                    ->orWhere('title->en', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }
        if ($status = $request->string('status')->toString()) {
            $query->whereIn('status', ContentStatus::filterValues($status));
        }
    }

    /**
     * @param  Builder<Page>  $query
     */
    private function applySort(Builder $query, Request $request): void
    {
        $dir = $request->string('dir', 'desc')->toString() === 'asc' ? 'asc' : 'desc';

        match ($request->string('sort')->toString()) {
            'status' => $query->orderBy('status', $dir),
            'title' => $query->orderBy('title->ru', $dir),
            default => $query->orderBy('sort')->orderByDesc('updated_at'),
        };
    }

    /**
     * @return array<int, array{key: string, label: string, count: int}>
     */
    private function savedViewCounts(Request $request): array
    {
        $views = [
            ['key' => 'all', 'label' => 'Все страницы'],
            ['key' => 'published', 'label' => 'Опубликованные'],
            ['key' => 'review', 'label' => 'На согласовании'],
            ['key' => 'drafts', 'label' => 'Черновики'],
            ['key' => 'mine', 'label' => 'Мои материалы'],
        ];

        return array_map(function (array $v) use ($request): array {
            $q = Page::query()->accessibleTo($request->user());
            $this->applyView($q, $v['key'], $request->user());
            $v['count'] = $q->count();

            return $v;
        }, $views);
    }

    /**
     * @return array<string, mixed>
     */
    private function formPayload(Page $page): array
    {
        return [
            'id' => $page->id,
            'title' => $page->getTranslations('title'),
            'body' => $page->getTranslations('body'),
            'seo_title' => $page->getTranslations('seo_title'),
            'seo_description' => $page->getTranslations('seo_description'),
            'slug' => $page->slug,
            'public_path' => PublicSite::pagePath($page->slug),
            'is_system' => PublicSite::isSystemPage($page->slug),
            'status' => $page->status->value,
            'parent_id' => $page->parent_id,
            'sort' => $page->sort,
            'published_at' => $page->published_at?->toIso8601String(),
            'languages' => $page->languageCompleteness(),
            'updated_at' => $page->updated_at?->toIso8601String(),
            'preview_url' => $this->editorialContent->previewUrl($page),
        ];
    }

    private function fill(Page $page, PageRequest $request): void
    {
        foreach (['title', 'seo_title', 'seo_description'] as $field) {
            /** @var array<string, string|null> $values */
            $values = $request->input($field, []);
            $page->setTranslations($field, array_filter(
                $values,
                fn (?string $v): bool => $v !== null && trim($v) !== '',
            ));
        }

        $page->setTranslations('body', RichText::sanitizeTranslations($request->input('body', [])));

        // Only overwrite the slug when one is supplied; a blank value lets the
        // model keep (or, on create, generate) a stable slug.
        $slug = trim((string) $request->input('slug', ''));
        if ($slug !== '') {
            $page->slug = $slug;
        }

        // The page form no longer edits hierarchy and order (the public site
        // uses neither); keep stored values unless a client still sends them.
        if ($request->exists('parent_id')) {
            $page->parent_id = $request->input('parent_id') !== null ? (int) $request->input('parent_id') : null;
        }

        if ($request->exists('sort')) {
            $page->sort = (int) $request->input('sort', 0);
        }
    }

    private function runPublishAction(Page $page, PageRequest $request): void
    {
        // A live material was just updated in place: nothing to publish.
        if ($request->input('action') !== 'submit' || $page->getWorkflowStatus()->isPublic()) {
            return;
        }

        $user = $request->user();

        match ($request->input('publish_mode', 'review')) {
            'now' => $this->authorizeAndPublish($page, $user),
            default => $this->workflow->transition($page, ContentStatus::Review, $user),
        };
    }

    private function authorizeAndPublish(Page $page, ?User $user): void
    {
        if ($user && $user->can('publish', $page)) {
            $this->workflow->transition($page, ContentStatus::Published, $user);
        } else {
            $this->workflow->transition($page, ContentStatus::Review, $user);
        }
    }

    private function savedMessage(Page $page, PageRequest $request): string
    {
        return SaveOutcome::message($page, $request->input('action') === 'submit', [
            'published' => 'Страница опубликована.',
            'review' => 'Страница отправлена на согласование.',
        ]);
    }
}
