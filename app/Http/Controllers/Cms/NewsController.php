<?php

namespace App\Http\Controllers\Cms;

use App\Concerns\HandlesPendingChanges;
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
use App\Support\ContentTitle;
use App\Support\EditorialContent;
use App\Support\FileSize;
use App\Support\PublicSite;
use App\Support\RichText;
use App\Support\SaveOutcome;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class NewsController extends Controller
{
    use HandlesPendingChanges;

    /**
     * Form keys that upload, pick or remove photos and files.
     *
     * @var list<string>
     */
    private const MEDIA_INPUTS = [
        'cover', 'cover_media_id', 'cover_remove',
        'gallery', 'gallery_media_ids', 'gallery_remove',
        'attachments', 'attachments_remove',
    ];

    public function __construct(
        private readonly WorkflowService $workflow,
        private readonly EditorialContent $editorialContent,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', News::class);

        $view = $request->string('view', 'all')->toString();
        $perPage = min(max((int) $request->integer('per_page', 25), 1), 100);

        $query = News::query()->accessibleTo($request->user())->with(['category', 'author', 'media']);
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

    public function edit(Request $request, News $news): Response
    {
        $this->authorize('update', $news);

        $news->load(['category', 'tags', 'author', 'media']);
        $pending = $this->pendingChangeProps($news, $request->user());

        return Inertia::render('news/form', [
            'news' => $this->formPayload($news),
            'reference' => $this->reference(),
            ...$pending,
        ]);
    }

    public function store(NewsRequest $request): RedirectResponse
    {
        $this->authorize('create', News::class);

        $news = DB::transaction(function () use ($request): News {
            $news = new News;
            $this->fill($news, $request);
            $news->author_id = $request->user()?->id;
            $news->status = ContentStatus::Draft;
            $news->save();

            $this->syncRelations($news, $request);
            $this->syncMedia($news, $request);

            return $news;
        });

        $this->runPublishAction($news, $request, "/news/{$news->id}/edit");

        return $this->redirectAfterSave($news, $request);
    }

    public function update(NewsRequest $request, News $news): RedirectResponse
    {
        $this->authorize('update', $news);

        $proposal = $this->proposeInsteadOfSaving(
            $news,
            $request,
            fn () => $this->fill($news, $request),
            ['tags' => array_values(array_map('intval', (array) $request->input('tags', [])))],
            self::MEDIA_INPUTS,
            "/news/{$news->id}/edit",
        );

        if ($proposal !== null) {
            return $proposal;
        }

        DB::transaction(function () use ($news, $request): void {
            $this->fill($news, $request);
            $news->save();

            $this->syncRelations($news, $request);
            $this->syncMedia($news, $request);
        });

        $this->runPublishAction($news, $request);
        $this->refreshSiteIfLive($news);

        return $this->redirectAfterSave($news, $request);
    }

    /**
     * List «Quick edit». It never changes the workflow status: publishing,
     * unpublishing and approval go through WorkflowService (permissions,
     * checklist, history, site cache). The publication date decides when the
     * news appears on the site, so changing it needs the publish permission.
     * On a live news item, someone without that permission proposes the edit
     * for approval, as in the editor.
     */
    public function quickUpdate(Request $request, News $news): JsonResponse|RedirectResponse
    {
        $this->authorize('update', $news);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable', 'string', 'max:255', 'alpha_dash',
                Rule::unique('news', 'slug')->ignore($news->id),
            ],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'is_pinned' => ['nullable', 'boolean'],
            'show_on_home' => ['nullable', 'boolean'],
            'published_at' => ['nullable', 'date'],
        ]);

        if (array_key_exists('published_at', $validated) && ! $this->samePublicationMinute($news, $validated['published_at'])) {
            $this->authorize('publish', $news);
        }

        $fill = function () use ($news, $validated): void {
            // The list shows the title in the first language the news is
            // written in, so the edited title goes back into that language —
            // never into another one (a Tajik title must not become the
            // Russian version).
            $news->setTranslation('title', ContentTitle::firstLocale($news) ?? 'ru', $validated['title']);

            if (! empty($validated['slug'])) {
                $news->slug = $validated['slug'];
            }
            if (array_key_exists('category_id', $validated)) {
                $news->category_id = $validated['category_id'] ?: null;
            }
            if (array_key_exists('is_pinned', $validated)) {
                $news->is_pinned = (bool) $validated['is_pinned'];
            }
            if (array_key_exists('show_on_home', $validated)) {
                $news->show_on_home = (bool) $validated['show_on_home'];
            }
            if (array_key_exists('published_at', $validated)) {
                $news->published_at = $validated['published_at'] ? Carbon::parse($validated['published_at']) : null;
            }
        };

        if ($this->proposeQuickEditInsteadOfSaving($news, $request, $fill)) {
            $message = 'Изменения отправлены на согласование. На сайте пока прежняя версия.';

            return $request->wantsJson()
                ? response()->json(['success' => true, 'pending' => true, 'message' => $message])
                : back()->with('success', $message);
        }

        $fill();
        $news->save();
        $this->refreshSiteIfLive($news);
        $news->load('category');

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'news' => [
                    'id' => $news->id,
                    'title' => ContentTitle::of($news),
                    'slug' => $news->slug,
                    'public_url' => PublicSite::urlFor($news),
                    'status' => $news->status->value,
                    'category' => $news->category?->getTranslation('name', 'ru'),
                    'category_id' => $news->category_id,
                    'is_pinned' => (bool) $news->is_pinned,
                    'show_on_home' => (bool) $news->show_on_home,
                    'published_at' => $news->published_at?->toIso8601String(),
                    'updated_at' => $news->updated_at?->toIso8601String(),
                ],
            ]);
        }

        return back()->with('success', 'Новость успешно обновлена.');
    }

    public function destroy(News $news): RedirectResponse
    {
        $this->authorize('delete', $news);
        $news->delete();

        return back()->with('success', 'Новость удалена.');
    }

    public function duplicate(News $news): RedirectResponse
    {
        $this->authorize('view', $news);
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
        $this->workflow->transition($news, ContentStatus::Published, $request->user());

        return back()->with('success', 'Новость опубликована.');
    }

    public function unpublish(Request $request, News $news): RedirectResponse
    {
        $this->authorize('publish', $news);
        $validated = $request->validate(['comment' => ['required', 'string', 'min:3']], [
            'comment.required' => 'Укажите причину снятия с публикации.',
        ]);
        $this->workflow->transition($news, ContentStatus::Draft, $request->user(), $validated['comment']);

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
                    ->orWhere('title->en', 'like', "%{$search}%")
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
            $q = News::query()->accessibleTo($request->user());
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
            'cover_caption' => $news->cover_caption,
            'attachments' => $this->attachmentsPayload($news),
            'gallery' => $this->galleryPayload($news),
            'cover_url' => $news->getFirstMediaUrl('cover') ?: null,
            'is_pinned' => (bool) $news->is_pinned,
            'show_on_home' => (bool) $news->show_on_home,
            'seo' => $this->localizedSeo($news->seo),
            'scheduled_at' => $news->scheduled_at?->format('Y-m-d\TH:i'),
            'published_at' => $news->published_at?->toIso8601String(),
            'views_count' => (int) $news->views_count,
            'languages' => $news->languageCompleteness(),
            'updated_at' => $news->updated_at?->toIso8601String(),
            'preview_url' => $this->editorialContent->previewUrl($news),
        ];
    }

    private function fill(News $news, NewsRequest $request): void
    {
        $news->fill([
            'category_id' => $request->input('category_id'),
            'cover_alt' => $request->input('cover_alt'),
            'cover_caption' => $request->input('cover_caption'),
            'is_pinned' => $request->boolean('is_pinned'),
            'show_on_home' => $request->boolean('show_on_home'),
            'scheduled_at' => $request->input('scheduled_at'),
            'seo' => $this->localizedSeo($request->input('seo', [])),
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

    /**
     * @return array<string, array{title: string, description: string}>
     */
    private function localizedSeo(mixed $value): array
    {
        $value = is_array($value) ? $value : [];

        // Compatibility for rows created before SEO became locale-aware.
        if (! isset($value['ru'], $value['tg'], $value['en'])) {
            $value = [
                'ru' => [
                    'title' => (string) ($value['title'] ?? ''),
                    'description' => (string) ($value['description'] ?? ''),
                ],
            ];
        }

        $result = [];
        foreach (['ru', 'tg', 'en'] as $locale) {
            $entry = is_array($value[$locale] ?? null) ? $value[$locale] : [];
            $result[$locale] = [
                'title' => trim((string) ($entry['title'] ?? '')),
                'description' => trim((string) ($entry['description'] ?? '')),
            ];
        }

        return $result;
    }

    private function syncRelations(News $news, NewsRequest $request): void
    {
        $news->tags()->sync($request->input('tags', []));
    }

    private function syncMedia(News $news, NewsRequest $request): void
    {
        $this->syncAttachments($news, $request);
        $this->syncGallery($news, $request);

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
                $copy = $source->copy($news, 'cover');
                $copy
                    ->setCustomProperty('source_media_id', $source->getKey())
                    ->setCustomProperty('focal_point', $source->getCustomProperty('focal_point'))
                    ->saveQuietly();
            }
        }
    }

    /**
     * Фотогалерея. Удаление — по идентификаторам (как у вложений), добавление —
     * файлами или копиями из медиатеки; порядок определяется порядком добавления.
     */
    private function syncGallery(News $news, NewsRequest $request): void
    {
        /** @var list<int> $remove */
        $remove = array_map('intval', (array) $request->input('gallery_remove', []));

        if ($remove !== []) {
            $news->getMedia('gallery')
                ->whereIn('id', $remove)
                ->each(fn (Media $media) => $media->delete());
        }

        foreach ((array) $request->file('gallery', []) as $file) {
            if ($file instanceof UploadedFile) {
                $news->addMedia($file)
                    ->usingName(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME))
                    ->toMediaCollection('gallery');
            }
        }

        foreach ((array) $request->input('gallery_media_ids', []) as $mediaId) {
            $source = Media::find((int) $mediaId);

            if ($source !== null) {
                $copy = $source->copy($news, 'gallery');
                $copy
                    ->setCustomProperty('source_media_id', $source->getKey())
                    ->setCustomProperty('focal_point', $source->getCustomProperty('focal_point'))
                    ->saveQuietly();
            }
        }
    }

    /**
     * Вложения материала. Удаление — по идентификаторам, чтобы правка одного
     * файла не требовала перезагружать остальные.
     */
    private function syncAttachments(News $news, NewsRequest $request): void
    {
        /** @var list<int> $remove */
        $remove = array_map('intval', (array) $request->input('attachments_remove', []));

        if ($remove !== []) {
            $news->getMedia('attachments')
                ->whereIn('id', $remove)
                ->each(fn (Media $media) => $media->delete());
        }

        foreach ((array) $request->file('attachments', []) as $file) {
            if ($file instanceof UploadedFile) {
                $news->addMedia($file)
                    ->usingName(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME))
                    ->toMediaCollection('attachments');
            }
        }
    }

    private function runPublishAction(News $news, NewsRequest $request, ?string $errorRedirect = null): void
    {
        // A live news item was just updated in place: there is nothing to
        // publish or send for approval.
        if ($request->input('action') !== 'submit' || $news->getWorkflowStatus()->isPublic()) {
            return;
        }

        $mode = $request->input('publish_mode', 'review');
        $user = $request->user();

        try {
            match ($mode) {
                'now' => $this->authorizeAndPublish($news, $user),
                // Scheduling is a publication decision: without the publish
                // permission (or without a date) the news goes to approval.
                'schedule' => $news->scheduled_at && $user?->can('publish', $news)
                    ? $this->workflow->transition($news, ContentStatus::Scheduled, $user)
                    : $this->workflow->transition($news, ContentStatus::Review, $user),
                default => $this->workflow->transition($news, ContentStatus::Review, $user),
            };
        } catch (ValidationException $exception) {
            if ($errorRedirect !== null) {
                throw $exception->redirectTo($errorRedirect);
            }

            throw $exception;
        }
    }

    /**
     * Quick edit sends the date back at minute precision; an unchanged date
     * is not a publication decision.
     */
    private function samePublicationMinute(News $news, ?string $value): bool
    {
        if ($value === null || $value === '') {
            return $news->published_at === null;
        }

        return $news->published_at !== null
            && Carbon::parse($value)->format('Y-m-d H:i') === $news->published_at->format('Y-m-d H:i');
    }

    private function authorizeAndPublish(News $news, ?User $user): void
    {
        if ($user && $user->can('publish', $news)) {
            $this->workflow->transition($news, ContentStatus::Published, $user);
        } else {
            $this->workflow->transition($news, ContentStatus::Review, $user);
        }
    }

    /**
     * After a save, stay on the editor (Ctrl+S / `stay` flag) or return to the
     * list. A freshly created draft lands on its own edit page so subsequent
     * saves update it instead of creating duplicates.
     */
    private function redirectAfterSave(News $news, NewsRequest $request): RedirectResponse
    {
        $message = $this->savedMessage($news, $request);

        return $request->boolean('stay')
            ? redirect("/news/{$news->id}/edit")->with('success', $message)
            : redirect('/news')->with('success', $message);
    }

    private function savedMessage(News $news, NewsRequest $request): string
    {
        return SaveOutcome::message($news, $request->input('action') === 'submit', [
            'published' => 'Новость опубликована.',
            'review' => 'Новость отправлена на согласование.',
            'scheduled' => 'Новость запланирована к публикации.',
        ]);
    }

    /**
     * Вложения для формы редактора: тип файла и человекочитаемый размер, как
     * их увидит читатель на странице.
     *
     * @return list<array{id: int, title: string, ext: string, size: string}>
     */
    private function attachmentsPayload(HasMedia $model): array
    {
        return array_values(array_map(
            static function (Media $media): array {
                $title = trim((string) $media->name);

                return [
                    'id' => (int) $media->getKey(),
                    'title' => $title !== '' ? $title : $media->file_name,
                    'ext' => strtoupper(pathinfo($media->file_name, PATHINFO_EXTENSION) ?: 'FILE'),
                    'size' => FileSize::human((int) $media->size, 'ru'),
                ];
            },
            $model->getMedia('attachments')->all(),
        ));
    }

    /**
     * Снимки галереи для формы редактора: маленькое превью (cms-320, есть у
     * каждой картинки этого проекта) и имя — его же фронт берёт как alt.
     *
     * @return list<array{id: int, title: string, preview_url: string}>
     */
    private function galleryPayload(News $news): array
    {
        return array_values(array_map(
            static function (Media $media): array {
                $title = trim((string) ($media->getCustomProperty('alt') !== '' ? $media->getCustomProperty('alt') : $media->name));

                return [
                    'id' => (int) $media->getKey(),
                    'title' => $title !== '' ? $title : $media->file_name,
                    'preview_url' => $media->getUrl('cms-320'),
                ];
            },
            $news->getMedia('gallery')->all(),
        ));
    }
}
