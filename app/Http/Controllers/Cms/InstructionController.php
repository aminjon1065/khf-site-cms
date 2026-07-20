<?php

namespace App\Http\Controllers\Cms;

use App\Enums\ContentStatus;
use App\Enums\HazardType;
use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Instruction\InstructionRequest;
use App\Http\Resources\InstructionResource;
use App\Models\Instruction;
use App\Models\User;
use App\Services\WorkflowService;
use App\Support\RichText;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class InstructionController extends Controller
{
    /**
     * @var list<string>
     */
    private const SECTIONS = ['before', 'during', 'after', 'prohibited'];

    /**
     * @var list<string>
     */
    private const LOCALES = ['ru', 'tg', 'en'];

    public function __construct(private readonly WorkflowService $workflow) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Instruction::class);

        $view = $request->string('view', 'all')->toString();
        $perPage = (int) $request->integer('per_page', 25);

        $query = Instruction::query()->with(['author', 'media']);
        $this->applyView($query, $view, $request->user());
        $this->applyFilters($query, $request);
        $this->applySort($query, $request);

        $instructions = $query->paginate($perPage)->withQueryString();

        return Inertia::render('instructions/index', [
            'instructions' => InstructionResource::collection($instructions->items())->resolve(),
            'meta' => [
                'from' => $instructions->firstItem(),
                'to' => $instructions->lastItem(),
                'total' => $instructions->total(),
                'per_page' => $instructions->perPage(),
                'current_page' => $instructions->currentPage(),
                'last_page' => $instructions->lastPage(),
                'prev' => $instructions->previousPageUrl(),
                'next' => $instructions->nextPageUrl(),
            ],
            'filters' => [
                'view' => $view,
                'search' => $request->string('search')->toString(),
                'status' => $request->string('status')->toString(),
                'hazard' => $request->string('hazard')->toString(),
                'sort' => $request->string('sort')->toString(),
                'dir' => $request->string('dir', 'desc')->toString(),
            ],
            'savedViews' => $this->savedViewCounts($request),
            'options' => ['statuses' => ContentStatus::options(), 'hazards' => HazardType::options()],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Instruction::class);

        return Inertia::render('instructions/form', [
            'instruction' => null,
            'reference' => $this->reference(),
        ]);
    }

    public function edit(Instruction $instruction): Response
    {
        $this->authorize('update', $instruction);

        $instruction->load(['author', 'media']);

        return Inertia::render('instructions/form', [
            'instruction' => $this->formPayload($instruction),
            'reference' => $this->reference(),
        ]);
    }

    public function store(InstructionRequest $request): RedirectResponse
    {
        $this->authorize('create', Instruction::class);

        $instruction = new Instruction;
        $this->fill($instruction, $request);
        $instruction->author_id = $request->user()?->id;
        $instruction->status = ContentStatus::Draft;
        $instruction->save();

        $this->syncMedia($instruction, $request);
        $this->runPublishAction($instruction, $request);

        return $this->redirectAfterSave($instruction, $request);
    }

    public function update(InstructionRequest $request, Instruction $instruction): RedirectResponse
    {
        $this->authorize('update', $instruction);

        $this->fill($instruction, $request);
        $instruction->save();

        $this->syncMedia($instruction, $request);
        $this->runPublishAction($instruction, $request);

        return $this->redirectAfterSave($instruction, $request);
    }

    public function destroy(Instruction $instruction): RedirectResponse
    {
        $this->authorize('delete', $instruction);
        $instruction->delete();

        return back()->with('success', 'Инструкция удалена.');
    }

    public function duplicate(Instruction $instruction): RedirectResponse
    {
        $this->authorize('create', Instruction::class);

        $copy = $instruction->replicate(['slug', 'published_at']);
        $name = $instruction->getTranslations('name');
        $name['ru'] = ($name['ru'] ?? '').' (копия)';
        $copy->setTranslations('name', $name);
        $copy->slug = null;
        $copy->status = ContentStatus::Draft;
        $copy->is_priority = false;
        $copy->author_id = request()->user()?->id;
        $copy->save();

        return redirect('/instructions/'.$copy->id.'/edit')->with('success', 'Создана копия инструкции.');
    }

    public function publish(Request $request, Instruction $instruction): RedirectResponse
    {
        $this->authorize('publish', $instruction);
        $this->workflow->transition($instruction, ContentStatus::Published, $request->user(), force: true);

        return back()->with('success', 'Инструкция опубликована.');
    }

    public function unpublish(Request $request, Instruction $instruction): RedirectResponse
    {
        $this->authorize('publish', $instruction);
        $validated = $request->validate(['comment' => ['required', 'string', 'min:3']], [
            'comment.required' => 'Укажите причину снятия с публикации.',
        ]);
        $this->workflow->transition($instruction, ContentStatus::Archived, $request->user(), $validated['comment'], force: true);

        return back()->with('success', 'Инструкция снята с публикации.');
    }

    // ---------------------------------------------------------------- helpers

    /**
     * @param  Builder<Instruction>  $query
     */
    private function applyView(Builder $query, string $view, ?User $user): void
    {
        match ($view) {
            'published' => $query->public(),
            'priority' => $query->where('is_priority', true),
            'drafts' => $query->whereIn('status', [ContentStatus::Draft->value, ContentStatus::Returned->value]),
            'review' => $query->whereIn('status', [ContentStatus::Review->value, ContentStatus::TranslationCheck->value, ContentStatus::Approved->value]),
            'mine' => $query->where('author_id', $user?->id),
            default => null,
        };
    }

    /**
     * @param  Builder<Instruction>  $query
     */
    private function applyFilters(Builder $query, Request $request): void
    {
        if ($search = $request->string('search')->toString()) {
            $query->where(function (Builder $q) use ($search): void {
                $q->where('name->ru', 'like', "%{$search}%")
                    ->orWhere('name->tg', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }
        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }
        if ($hazard = $request->string('hazard')->toString()) {
            $query->where('hazard_type', $hazard);
        }
    }

    /**
     * @param  Builder<Instruction>  $query
     */
    private function applySort(Builder $query, Request $request): void
    {
        $dir = $request->string('dir', 'desc')->toString() === 'asc' ? 'asc' : 'desc';

        match ($request->string('sort')->toString()) {
            'status' => $query->orderBy('status', $dir),
            'published' => $query->orderBy('published_at', $dir),
            'order' => $query->orderByDesc('is_priority')->orderBy('sort'),
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
            ['key' => 'priority', 'label' => 'Закреплённые'],
            ['key' => 'review', 'label' => 'На согласовании'],
            ['key' => 'drafts', 'label' => 'Черновики'],
            ['key' => 'mine', 'label' => 'Мои материалы'],
        ];

        return array_map(function (array $v) use ($request): array {
            $q = Instruction::query();
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
            'hazards' => HazardType::options(),
            'authors' => User::query()->role([
                RoleName::Editor->value,
                RoleName::ChiefEditor->value,
                RoleName::Admin->value,
            ])->get()->map(fn (User $u): array => ['value' => $u->id, 'label' => $u->name])->all(),
            'sectionKeys' => [
                ['key' => 'before', 'label' => 'До события'],
                ['key' => 'during', 'label' => 'Во время события'],
                ['key' => 'after', 'label' => 'После события'],
                ['key' => 'prohibited', 'label' => 'Чего делать нельзя'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formPayload(Instruction $instruction): array
    {
        return [
            'id' => $instruction->id,
            'name' => $instruction->getTranslations('name'),
            'summary' => $instruction->getTranslations('summary'),
            'body' => $instruction->getTranslations('body'),
            'slug' => $instruction->slug,
            'status' => $instruction->status->value,
            'hazard_type' => $instruction->hazard_type?->value,
            'is_priority' => (bool) $instruction->is_priority,
            'sort' => (int) $instruction->sort,
            'sections' => $this->normalizeSections($instruction->sections),
            'image_url' => $instruction->getFirstMediaUrl('image') ?: null,
            'published_at' => $instruction->published_at?->toIso8601String(),
            'languages' => $instruction->languageCompleteness(),
        ];
    }

    private function fill(Instruction $instruction, InstructionRequest $request): void
    {
        $instruction->fill([
            'hazard_type' => $request->input('hazard_type'),
            'is_priority' => $request->boolean('is_priority'),
            'sort' => (int) $request->integer('sort'),
            'sections' => $this->cleanSections($request->input('sections')),
        ]);

        if ($request->filled('slug')) {
            $instruction->slug = $request->string('slug')->toString();
        }

        foreach (['name', 'summary'] as $field) {
            /** @var array<string, string|null> $values */
            $values = $request->input($field, []);
            $instruction->setTranslations($field, array_filter(
                $values,
                fn (?string $v): bool => $v !== null && trim($v) !== '',
            ));
        }

        // Rich-text detail body: sanitise HTML from the Tiptap editor per locale.
        $instruction->setTranslations('body', RichText::sanitizeTranslations($request->input('body', [])));
    }

    /**
     * Keep only known sections/locales, trim and drop empty steps, reindex.
     *
     * @return array<string, array<string, list<string>>>
     */
    private function cleanSections(mixed $input): array
    {
        $input = is_array($input) ? $input : [];
        $result = [];

        foreach (self::SECTIONS as $section) {
            foreach (self::LOCALES as $locale) {
                $steps = $input[$section][$locale] ?? [];

                if (! is_array($steps)) {
                    continue;
                }

                $clean = array_values(array_filter(
                    array_map(fn ($s): string => is_string($s) ? trim($s) : '', $steps),
                    fn (string $s): bool => $s !== '',
                ));

                if ($clean !== []) {
                    $result[$section][$locale] = $clean;
                }
            }
        }

        return $result;
    }

    /**
     * Ensure every section/locale key exists (as an array) for the form.
     *
     * @param  array<string, mixed>|null  $sections
     * @return array<string, array<string, list<string>>>
     */
    private function normalizeSections(?array $sections): array
    {
        $sections ??= [];
        $result = [];

        foreach (self::SECTIONS as $section) {
            foreach (self::LOCALES as $locale) {
                $steps = $sections[$section][$locale] ?? [];
                $result[$section][$locale] = is_array($steps) ? array_values($steps) : [];
            }
        }

        return $result;
    }

    private function syncMedia(Instruction $instruction, InstructionRequest $request): void
    {
        if ($request->boolean('image_remove')) {
            $instruction->clearMediaCollection('image');
        }

        if ($request->hasFile('image')) {
            $instruction->clearMediaCollection('image');
            $instruction->addMediaFromRequest('image')->toMediaCollection('image');
        } elseif ($request->filled('image_media_id')) {
            // Image chosen from the media library: copy the source file into the
            // instruction's own `image` collection so it is independent of it.
            $source = Media::find($request->integer('image_media_id'));
            if ($source !== null) {
                $instruction->clearMediaCollection('image');
                $source->copy($instruction, 'image');
            }
        }
    }

    private function runPublishAction(Instruction $instruction, InstructionRequest $request): void
    {
        if ($request->input('action') !== 'submit') {
            return;
        }

        $user = $request->user();

        match ($request->input('publish_mode', 'review')) {
            'now' => $this->authorizeAndPublish($instruction, $user),
            default => $this->workflow->transition($instruction, ContentStatus::Review, $user, force: true),
        };
    }

    private function authorizeAndPublish(Instruction $instruction, ?User $user): void
    {
        if ($user && $user->can('publish', $instruction)) {
            $this->workflow->transition($instruction, ContentStatus::Published, $user, force: true);
        } else {
            $this->workflow->transition($instruction, ContentStatus::Review, $user, force: true);
        }
    }

    /**
     * After a save, stay on the editor (Ctrl+S / `stay` flag) or return to the
     * list. A freshly created draft lands on its own edit page so subsequent
     * saves update it instead of creating duplicates.
     */
    private function redirectAfterSave(Instruction $instruction, InstructionRequest $request): RedirectResponse
    {
        $message = $this->savedMessage($request);

        return $request->boolean('stay')
            ? redirect("/instructions/{$instruction->id}/edit")->with('success', $message)
            : redirect('/instructions')->with('success', $message);
    }

    private function savedMessage(InstructionRequest $request): string
    {
        if ($request->input('action') !== 'submit') {
            return 'Черновик сохранён.';
        }

        return $request->input('publish_mode') === 'now'
            ? 'Инструкция опубликована.'
            : 'Инструкция отправлена на согласование.';
    }
}
