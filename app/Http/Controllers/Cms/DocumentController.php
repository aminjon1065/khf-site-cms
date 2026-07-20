<?php

namespace App\Http\Controllers\Cms;

use App\Enums\ContentStatus;
use App\Enums\DocType;
use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Document\DocumentRequest;
use App\Http\Resources\DocumentResource;
use App\Models\Document;
use App\Models\User;
use App\Services\WorkflowService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DocumentController extends Controller
{
    /**
     * @var list<string>
     */
    private const LOCALES = ['tg', 'ru', 'en'];

    public function __construct(private readonly WorkflowService $workflow) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Document::class);

        $view = $request->string('view', 'all')->toString();
        $perPage = (int) $request->integer('per_page', 25);

        $query = Document::query()->with(['author', 'media']);
        $this->applyView($query, $view, $request->user());
        $this->applyFilters($query, $request);
        $this->applySort($query, $request);

        $documents = $query->paginate($perPage)->withQueryString();

        return Inertia::render('documents/index', [
            'documents' => DocumentResource::collection($documents->items())->resolve(),
            'meta' => [
                'from' => $documents->firstItem(),
                'to' => $documents->lastItem(),
                'total' => $documents->total(),
                'per_page' => $documents->perPage(),
                'current_page' => $documents->currentPage(),
                'last_page' => $documents->lastPage(),
                'prev' => $documents->previousPageUrl(),
                'next' => $documents->nextPageUrl(),
            ],
            'filters' => [
                'view' => $view,
                'search' => $request->string('search')->toString(),
                'status' => $request->string('status')->toString(),
                'type' => $request->string('type')->toString(),
                'section' => $request->string('section')->toString(),
                'sort' => $request->string('sort')->toString(),
                'dir' => $request->string('dir', 'desc')->toString(),
            ],
            'savedViews' => $this->savedViewCounts($request),
            'options' => [
                'statuses' => ContentStatus::options(),
                'types' => DocType::options(),
                'sections' => $this->sections(),
            ],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Document::class);

        return Inertia::render('documents/form', [
            'document' => null,
            'reference' => $this->reference(),
        ]);
    }

    public function edit(Document $document): Response
    {
        $this->authorize('update', $document);

        $document->load(['author', 'media']);

        return Inertia::render('documents/form', [
            'document' => $this->formPayload($document),
            'reference' => $this->reference(),
        ]);
    }

    public function store(DocumentRequest $request): RedirectResponse
    {
        $this->authorize('create', Document::class);

        $document = new Document;
        $this->fill($document, $request);
        $document->author_id = $request->user()?->id;
        $document->status = ContentStatus::Draft;
        $document->save();

        $this->syncFiles($document, $request);
        $this->runPublishAction($document, $request);

        return redirect('/documents')->with('success', $this->savedMessage($request));
    }

    public function update(DocumentRequest $request, Document $document): RedirectResponse
    {
        $this->authorize('update', $document);

        $this->fill($document, $request);
        $document->save();

        $this->syncFiles($document, $request);
        $this->runPublishAction($document, $request);

        return redirect('/documents')->with('success', $this->savedMessage($request));
    }

    public function destroy(Document $document): RedirectResponse
    {
        $this->authorize('delete', $document);
        $document->delete();

        return back()->with('success', 'Документ удалён.');
    }

    public function duplicate(Document $document): RedirectResponse
    {
        $this->authorize('create', Document::class);

        $copy = $document->replicate(['published_at']);
        $name = $document->getTranslations('name');
        $name['ru'] = ($name['ru'] ?? '').' (копия)';
        $copy->setTranslations('name', $name);
        $copy->status = ContentStatus::Draft;
        $copy->author_id = request()->user()?->id;
        $copy->save();

        return redirect('/documents/'.$copy->id.'/edit')->with('success', 'Создана копия документа (файлы прикрепите заново).');
    }

    public function publish(Request $request, Document $document): RedirectResponse
    {
        $this->authorize('publish', $document);
        $this->workflow->transition($document, ContentStatus::Published, $request->user(), force: true);

        return back()->with('success', 'Документ опубликован.');
    }

    public function unpublish(Request $request, Document $document): RedirectResponse
    {
        $this->authorize('publish', $document);
        $validated = $request->validate(['comment' => ['required', 'string', 'min:3']], [
            'comment.required' => 'Укажите причину снятия с публикации.',
        ]);
        $this->workflow->transition($document, ContentStatus::Archived, $request->user(), $validated['comment'], force: true);

        return back()->with('success', 'Документ снят с публикации.');
    }

    // ---------------------------------------------------------------- helpers

    /**
     * @param  Builder<Document>  $query
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
     * @param  Builder<Document>  $query
     */
    private function applyFilters(Builder $query, Request $request): void
    {
        if ($search = $request->string('search')->toString()) {
            $query->where(function (Builder $q) use ($search): void {
                $q->where('name->ru', 'like', "%{$search}%")
                    ->orWhere('name->tg', 'like', "%{$search}%")
                    ->orWhere('number', 'like', "%{$search}%");
            });
        }
        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }
        if ($type = $request->string('type')->toString()) {
            $query->where('doc_type', $type);
        }
        if ($section = $request->string('section')->toString()) {
            $query->where('section', $section);
        }
    }

    /**
     * @param  Builder<Document>  $query
     */
    private function applySort(Builder $query, Request $request): void
    {
        $dir = $request->string('dir', 'desc')->toString() === 'asc' ? 'asc' : 'desc';

        match ($request->string('sort')->toString()) {
            'status' => $query->orderBy('status', $dir),
            'date' => $query->orderBy('doc_date', $dir),
            'type' => $query->orderBy('doc_type', $dir),
            default => $query->orderByDesc('updated_at'),
        };
    }

    /**
     * @return array<int, array{key: string, label: string, count: int}>
     */
    private function savedViewCounts(Request $request): array
    {
        $views = [
            ['key' => 'all', 'label' => 'Все документы'],
            ['key' => 'published', 'label' => 'Опубликованные'],
            ['key' => 'review', 'label' => 'На согласовании'],
            ['key' => 'drafts', 'label' => 'Черновики'],
            ['key' => 'mine', 'label' => 'Мои материалы'],
        ];

        return array_map(function (array $v) use ($request): array {
            $q = Document::query();
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
            'types' => DocType::options(),
            'sections' => $this->sections(),
            'authors' => User::query()->role([
                RoleName::Editor->value,
                RoleName::ChiefEditor->value,
                RoleName::Admin->value,
            ])->get()->map(fn (User $u): array => ['value' => $u->id, 'label' => $u->name])->all(),
        ];
    }

    /**
     * Distinct non-empty section names already in use (for filter + datalist).
     *
     * @return array<int, string>
     */
    private function sections(): array
    {
        return Document::query()
            ->whereNotNull('section')
            ->distinct()
            ->orderBy('section')
            ->pluck('section')
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function formPayload(Document $document): array
    {
        return [
            'id' => $document->id,
            'name' => $document->getTranslations('name'),
            'doc_type' => $document->doc_type->value,
            'number' => $document->number,
            'doc_date' => $document->doc_date?->toDateString(),
            'section' => $document->section,
            'status' => $document->status->value,
            'files' => $this->fileInfo($document),
            'published_at' => $document->published_at?->toIso8601String(),
        ];
    }

    /**
     * Per-locale attached-file info for the form.
     *
     * @return array<string, array{name: string, url: string}|null>
     */
    private function fileInfo(Document $document): array
    {
        $info = [];

        foreach (self::LOCALES as $locale) {
            $media = $document->getFirstMedia("file_{$locale}");
            $info[$locale] = $media
                ? ['name' => $media->file_name, 'url' => $media->getFullUrl()]
                : null;
        }

        return $info;
    }

    private function fill(Document $document, DocumentRequest $request): void
    {
        $document->fill([
            'doc_type' => $request->input('doc_type'),
            'number' => $request->input('number'),
            'doc_date' => $request->input('doc_date'),
            'section' => $request->input('section'),
        ]);

        /** @var array<string, string|null> $names */
        $names = $request->input('name', []);
        $document->setTranslations('name', array_filter(
            $names,
            fn (?string $v): bool => $v !== null && trim($v) !== '',
        ));
    }

    private function syncFiles(Document $document, DocumentRequest $request): void
    {
        foreach (self::LOCALES as $locale) {
            $collection = "file_{$locale}";

            if ($request->boolean("file_{$locale}_remove")) {
                $document->clearMediaCollection($collection);
            }

            if ($request->hasFile($collection)) {
                $document->clearMediaCollection($collection);
                $document->addMediaFromRequest($collection)->toMediaCollection($collection);
            }
        }
    }

    private function runPublishAction(Document $document, DocumentRequest $request): void
    {
        if ($request->input('action') !== 'submit') {
            return;
        }

        $user = $request->user();

        match ($request->input('publish_mode', 'review')) {
            'now' => $this->authorizeAndPublish($document, $user),
            default => $this->workflow->transition($document, ContentStatus::Review, $user, force: true),
        };
    }

    private function authorizeAndPublish(Document $document, ?User $user): void
    {
        if ($user && $user->can('publish', $document)) {
            $this->workflow->transition($document, ContentStatus::Published, $user, force: true);
        } else {
            $this->workflow->transition($document, ContentStatus::Review, $user, force: true);
        }
    }

    private function savedMessage(DocumentRequest $request): string
    {
        if ($request->input('action') !== 'submit') {
            return 'Черновик сохранён.';
        }

        return $request->input('publish_mode') === 'now'
            ? 'Документ опубликован.'
            : 'Документ отправлен на согласование.';
    }
}
