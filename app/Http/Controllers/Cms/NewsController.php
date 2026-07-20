<?php

namespace App\Http\Controllers\Cms;

use App\Enums\ContentStatus;
use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Http\Requests\News\NewsRequest;
use App\Http\Resources\NewsResource;
use App\Models\Category;
use App\Models\News;
use App\Models\Tag;
use App\Models\User;
use App\Services\WorkflowService;
use App\Support\RichText;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class NewsController extends Controller
{
    public function __construct(private readonly WorkflowService $workflow) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', News::class);

        $view = $request->string('view', 'all')->toString();
        $perPage = (int) $request->integer('per_page', 25);

        $query = News::query()->with(['category', 'author', 'media']);
        $this->applyView($query, $view, $request->user());
        $this->applyFilters($query, $request);
        $this->applySort($query, $request);

        $news = $query->paginate($perPage)->withQueryString();

        return Inertia::render('news/index', [
            'news' => NewsResource::collection($news->items())->resolve(),
            'meta' => [
                'from' => $news->firstItem(),
                'to' => $news->lastItem(),
                'total' => $news->total(),
                'per_page' => $news->perPage(),
                'current_page' => $news->currentPage(),
                'last_page' => $news->lastPage(),
                'prev' => $news->previousPageUrl(),
                'next' => $news->nextPageUrl(),
            ],
            'filters' => [
                'view' => $view,
                'search' => $request->string('search')->toString(),
                'status' => $request->string('status')->toString(),
                'category' => $request->string('category')->toString(),
                'sort' => $request->string('sort')->toString(),
                'dir' => $request->string('dir', 'desc')->toString(),
            ],
            'savedViews' => $this->savedViewCounts($request),
            'options' => $this->filterOptions(),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', News::class);

        return Inertia::render('news/form', [
            'news' => null,
            'reference' => $this->reference(),
        ]);
    }

    public function edit(News $news): Response
    {
        $this->authorize('update', $news);

        $news->load(['category', 'tags', 'author', 'media']);

        return Inertia::render('news/form', [
            'news' => $this->formPayload($news),
            'reference' => $this->reference(),
        ]);
    }

    public function store(NewsRequest $request): RedirectResponse
    {
        $this->authorize('create', News::class);

        $news = new News;
        $this->fill($news, $request);
        $news->author_id = $request->user()?->id;
        $news->status = ContentStatus::Draft;
        $news->save();

        $this->syncRelations($news, $request);
        $this->syncMedia($news, $request);
        $this->runPublishAction($news, $request);

        return $this->redirectAfterSave($news, $request);
    }

    public function update(NewsRequest $request, News $news): RedirectResponse
    {
        $this->authorize('update', $news);

        $this->fill($news, $request);
        $news->save();

        $this->syncRelations($news, $request);
        $this->syncMedia($news, $request);
        $this->runPublishAction($news, $request);

        return $this->redirectAfterSave($news, $request);
    }

    public function destroy(News $news): RedirectResponse
    {
        $this->authorize('delete', $news);
        $news->delete();

        return back()->with('success', 'Новость удалена.');
    }

    public function duplicate(News $news): RedirectResponse
    {
        $this->authorize('create', News::class);

        $copy = $news->replicate(['slug', 'published_at', 'scheduled_at', 'views_count']);
        $title = $news->getTranslations('title');
        $title['ru'] = ($title['ru'] ?? '').' (копия)';
        $copy->setTranslations('title', $title);
        $copy->slug = null; // regenerated on save
        $copy->status = ContentStatus::Draft;
        $copy->views_count = 0;
        $copy->author_id = request()->user()?->id;
        $copy->save();
        $copy->tags()->sync($news->tags->pluck('id'));

        return redirect('/news/'.$copy->id.'/edit')->with('success', 'Создана копия новости.');
    }

    public function publish(Request $request, News $news): RedirectResponse
    {
        $this->authorize('publish', $news);
        $this->workflow->transition($news, ContentStatus::Published, $request->user(), force: true);

        return back()->with('success', 'Новость опубликована.');
    }

    public function unpublish(Request $request, News $news): RedirectResponse
    {
        $this->authorize('publish', $news);
        $validated = $request->validate(['comment' => ['required', 'string', 'min:3']], [
            'comment.required' => 'Укажите причину снятия с публикации.',
        ]);
        $this->workflow->transition($news, ContentStatus::Archived, $request->user(), $validated['comment'], force: true);

        return back()->with('success', 'Новость снята с публикации.');
    }

    // ---------------------------------------------------------------- helpers

    /**
     * @param  Builder<News>  $query
     */
    private function applyView(Builder $query, string $view, ?User $user): void
    {
        match ($view) {
            'published' => $query->public(),
            'drafts' => $query->whereIn('status', [ContentStatus::Draft->value, ContentStatus::Returned->value]),
            'review' => $query->whereIn('status', [ContentStatus::Review->value, ContentStatus::TranslationCheck->value, ContentStatus::Approved->value]),
            'scheduled' => $query->where('status', ContentStatus::Scheduled->value),
            'mine' => $query->where('author_id', $user?->id),
            default => null,
        };
    }

    /**
     * @param  Builder<News>  $query
     */
    private function applyFilters(Builder $query, Request $request): void
    {
        if ($search = $request->string('search')->toString()) {
            $query->where(function (Builder $q) use ($search): void {
                $q->where('title->ru', 'like', "%{$search}%")
                    ->orWhere('title->tg', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }
        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }
        if ($category = $request->string('category')->toString()) {
            $query->where('category_id', $category);
        }
    }

    /**
     * @param  Builder<News>  $query
     */
    private function applySort(Builder $query, Request $request): void
    {
        $dir = $request->string('dir', 'desc')->toString() === 'asc' ? 'asc' : 'desc';

        match ($request->string('sort')->toString()) {
            'status' => $query->orderBy('status', $dir),
            'published' => $query->orderBy('published_at', $dir),
            'views' => $query->orderBy('views_count', $dir),
            default => $query->orderByDesc('updated_at'),
        };
    }

    /**
     * @return array<int, array{key: string, label: string, count: int}>
     */
    private function savedViewCounts(Request $request): array
    {
        $views = [
            ['key' => 'all', 'label' => 'Все материалы'],
            ['key' => 'published', 'label' => 'Опубликованные'],
            ['key' => 'review', 'label' => 'На согласовании'],
            ['key' => 'scheduled', 'label' => 'Запланированные'],
            ['key' => 'drafts', 'label' => 'Черновики'],
            ['key' => 'mine', 'label' => 'Мои материалы'],
        ];

        return array_map(function (array $v) use ($request): array {
            $q = News::query();
            $this->applyView($q, $v['key'], $request->user());
            $v['count'] = $q->count();

            return $v;
        }, $views);
    }

    /**
     * @return array<string, mixed>
     */
    private function filterOptions(): array
    {
        return [
            'statuses' => ContentStatus::options(),
            'categories' => $this->newsCategories(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function reference(): array
    {
        return [
            'categories' => $this->newsCategories(),
            'tags' => Tag::query()->orderBy('id')->get()->map(fn (Tag $t): array => [
                'value' => $t->id,
                'label' => $t->getTranslation('name', 'ru'),
            ])->all(),
            'authors' => User::query()->role([
                RoleName::Editor->value,
                RoleName::ChiefEditor->value,
                RoleName::Admin->value,
                RoleName::RegionalEditor->value,
            ])->get()->map(fn (User $u): array => ['value' => $u->id, 'label' => $u->name])->all(),
        ];
    }

    /**
     * @return array<int, array{value: int, label: string}>
     */
    private function newsCategories(): array
    {
        return Category::query()
            ->where('type', 'news')
            ->orderBy('sort')
            ->get()
            ->map(fn (Category $c): array => ['value' => $c->id, 'label' => $c->getTranslation('name', 'ru')])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function formPayload(News $news): array
    {
        return [
            'id' => $news->id,
            'title' => $news->getTranslations('title'),
            'summary' => $news->getTranslations('summary'),
            'body' => $news->getTranslations('body'),
            'slug' => $news->slug,
            'status' => $news->status->value,
            'category_id' => $news->category_id,
            'tags' => $news->tags->pluck('id')->all(),
            'cover_alt' => $news->cover_alt,
            'cover_url' => $news->getFirstMediaUrl('cover') ?: null,
            'is_pinned' => (bool) $news->is_pinned,
            'show_on_home' => (bool) $news->show_on_home,
            'seo' => $news->seo ?? ['title' => '', 'description' => ''],
            'scheduled_at' => $news->scheduled_at?->format('Y-m-d\TH:i'),
            'published_at' => $news->published_at?->toIso8601String(),
            'views_count' => (int) $news->views_count,
            'languages' => $news->languageCompleteness(),
        ];
    }

    private function fill(News $news, NewsRequest $request): void
    {
        $news->fill([
            'category_id' => $request->input('category_id'),
            'cover_alt' => $request->input('cover_alt'),
            'is_pinned' => $request->boolean('is_pinned'),
            'show_on_home' => $request->boolean('show_on_home'),
            'scheduled_at' => $request->input('scheduled_at'),
            'seo' => [
                'title' => (string) $request->input('seo.title', ''),
                'description' => (string) $request->input('seo.description', ''),
            ],
        ]);

        if ($request->filled('slug')) {
            $news->slug = $request->string('slug')->toString();
        }

        // Plain-text fields: keep non-empty locales verbatim.
        foreach (['title', 'summary'] as $field) {
            /** @var array<string, string|null> $values */
            $values = $request->input($field, []);
            $news->setTranslations($field, array_filter(
                $values,
                fn (?string $v): bool => $v !== null && trim($v) !== '',
            ));
        }

        $news->setTranslations('body', RichText::sanitizeTranslations($request->input('body', [])));
    }

    private function syncRelations(News $news, NewsRequest $request): void
    {
        $news->tags()->sync($request->input('tags', []));
    }

    private function syncMedia(News $news, NewsRequest $request): void
    {
        if ($request->boolean('cover_remove')) {
            $news->clearMediaCollection('cover');
        }

        if ($request->hasFile('cover')) {
            $news->clearMediaCollection('cover');
            $news->addMediaFromRequest('cover')->toMediaCollection('cover');
        } elseif ($request->filled('cover_media_id')) {
            // Cover chosen from the media library: copy the source file into the
            // news' own `cover` collection so it is independent of the library.
            $source = Media::find($request->integer('cover_media_id'));
            if ($source !== null) {
                $news->clearMediaCollection('cover');
                $source->copy($news, 'cover');
            }
        }
    }

    private function runPublishAction(News $news, NewsRequest $request): void
    {
        if ($request->input('action') !== 'submit') {
            return;
        }

        $mode = $request->input('publish_mode', 'review');
        $user = $request->user();

        match ($mode) {
            'now' => $this->authorizeAndPublish($news, $user),
            'schedule' => $news->scheduled_at
                ? $this->workflow->transition($news, ContentStatus::Scheduled, $user, force: true)
                : $this->workflow->transition($news, ContentStatus::Review, $user, force: true),
            default => $this->workflow->transition($news, ContentStatus::Review, $user, force: true),
        };
    }

    private function authorizeAndPublish(News $news, ?User $user): void
    {
        if ($user && $user->can('publish', $news)) {
            $this->workflow->transition($news, ContentStatus::Published, $user, force: true);
        } else {
            $this->workflow->transition($news, ContentStatus::Review, $user, force: true);
        }
    }

    /**
     * After a save, stay on the editor (Ctrl+S / `stay` flag) or return to the
     * list. A freshly created draft lands on its own edit page so subsequent
     * saves update it instead of creating duplicates.
     */
    private function redirectAfterSave(News $news, NewsRequest $request): RedirectResponse
    {
        $message = $this->savedMessage($request);

        return $request->boolean('stay')
            ? redirect("/news/{$news->id}/edit")->with('success', $message)
            : redirect('/news')->with('success', $message);
    }

    private function savedMessage(NewsRequest $request): string
    {
        if ($request->input('action') !== 'submit') {
            return 'Черновик сохранён.';
        }

        return match ($request->input('publish_mode')) {
            'now' => 'Новость опубликована.',
            'schedule' => $request->filled('scheduled_at')
                ? 'Новость запланирована к публикации.'
                : 'Новость отправлена на согласование.',
            default => 'Новость отправлена на согласование.',
        };
    }
}
