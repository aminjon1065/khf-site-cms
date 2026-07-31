<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Media\MediaUploadRequest;
use App\Models\Alert;
use App\Models\Document;
use App\Models\Instruction;
use App\Models\MediaAsset;
use App\Models\News;
use App\Models\Project;
use App\Services\MediaUsageService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\MediaLibrary\Conversions\FileManipulator;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * The media library: browse every file in the system (content covers, document
 * files and reusable library assets), upload new reusable assets, and remove
 * library-owned assets. Content media stays managed by its own editor.
 */
class MediaController extends Controller
{
    public function __construct(private readonly MediaUsageService $mediaUsageService) {}

    public function index(Request $request): Response
    {
        abort_unless((bool) $request->user()?->can('media.view'), 403);

        $kind = $request->string('kind')->toString();
        $search = $request->string('search')->toString();
        $status = $request->string('status', 'active')->toString();
        $perPage = max(1, min((int) $request->integer('per_page', 24), 100));

        $query = Media::query()->with('model')->latest('id');

        if ($status === 'trash') {
            $query
                ->where('model_type', MediaAsset::class)
                ->whereIn('model_id', MediaAsset::onlyTrashed()->select('id'));
        } else {
            $status = 'active';
            $query->where(function (Builder $active): void {
                $active
                    ->where('model_type', '!=', MediaAsset::class)
                    ->orWhereNotIn('model_id', MediaAsset::onlyTrashed()->select('id'));
            });
        }

        if ($kind === 'image') {
            $query->where('mime_type', 'like', 'image/%');
        } elseif ($kind === 'file') {
            $query->where('mime_type', 'not like', 'image/%');
        }
        if ($search !== '') {
            $query->where(function (Builder $q) use ($search): void {
                $q->where('file_name', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere(function (Builder $assetQuery) use ($search): void {
                        $assetQuery
                            ->where('model_type', MediaAsset::class)
                            ->whereIn(
                                'model_id',
                                MediaAsset::withTrashed()
                                    ->where(function (Builder $metadata) use ($search): void {
                                        $metadata
                                            ->where('title', 'like', "%{$search}%")
                                            ->orWhere('alt', 'like', "%{$search}%")
                                            ->orWhere('caption', 'like', "%{$search}%");
                                    })
                                    ->select('id'),
                            );
                    });
            });
        }

        $media = $query->paginate($perPage)->withQueryString();

        return Inertia::render('media/index', [
            'items' => array_map(fn (Media $m): array => $this->present($m), $media->items()),
            'meta' => [
                'from' => $media->firstItem(),
                'to' => $media->lastItem(),
                'total' => $media->total(),
                'per_page' => $media->perPage(),
                'prev' => $media->previousPageUrl(),
                'next' => $media->nextPageUrl(),
            ],
            'filters' => ['kind' => $kind, 'search' => $search, 'status' => $status],
            'stats' => [
                'total' => Media::query()->count(),
                'images' => Media::query()->where('mime_type', 'like', 'image/%')->count(),
                'library' => MediaAsset::query()->count(),
                'trash' => MediaAsset::onlyTrashed()->count(),
            ],
        ]);
    }

    public function store(MediaUploadRequest $request): RedirectResponse
    {
        abort_unless((bool) $request->user()?->can('media.create'), 403);

        $userId = $request->user()->id;

        // Transactional so a failed file attach rolls the holder row back
        // instead of leaving an orphan media_assets record.
        DB::transaction(function () use ($request, $userId): void {
            $asset = new MediaAsset;
            $asset->title = $request->input('title');
            $asset->is_decorative = $request->boolean('is_decorative');
            $asset->alt = $asset->is_decorative ? null : $request->input('alt');
            $asset->uploaded_by = $userId;
            $asset->save();

            $asset->addMediaFromRequest('file')->toMediaCollection('asset');
        });

        return back()->with('success', 'Файл загружен в медиабиблиотеку.');
    }

    public function destroy(Request $request, Media $media): RedirectResponse
    {
        abort_unless((bool) $request->user()?->can('media.delete'), 403);

        // Only library-owned assets may be deleted here; content media (covers,
        // document files) must be managed from the content editor.
        if ($media->model_type !== MediaAsset::class) {
            return back()->with('error', 'Этот файл используется в материале — удалите его в редакторе материала.');
        }

        $usages = $this->mediaUsageService->usages($media);
        if ($usages !== []) {
            return back()->with(
                'error',
                'Файл используется в материалах ('.count($usages).'). Сначала замените его во всех местах.',
            );
        }

        $owner = MediaAsset::query()->find($media->model_id);
        if ($owner === null) {
            return back()->with('error', 'Владелец файла не найден.');
        }

        $owner->delete();

        return back()->with('success', 'Файл перемещён в корзину. Его можно восстановить.');
    }

    public function restore(Request $request, Media $media): RedirectResponse
    {
        abort_unless((bool) $request->user()?->can('media.delete'), 403);

        if ($media->model_type !== MediaAsset::class) {
            return back()->with('error', 'Восстановление доступно только для файлов медиабиблиотеки.');
        }

        $owner = MediaAsset::onlyTrashed()->find($media->model_id);
        if ($owner === null) {
            return back()->with('error', 'Файл не находится в корзине.');
        }

        $owner->restore();

        return back()->with('success', 'Файл восстановлен.');
    }

    public function usages(Request $request, Media $media): JsonResponse
    {
        abort_unless((bool) $request->user()?->can('media.view'), 403);

        return response()->json([
            'data' => $this->mediaUsageService->usages($media),
        ]);
    }

    /**
     * JSON list of library images for the in-editor media picker (paginated,
     * searchable). Only images are returned — the picker inserts <img> nodes.
     */
    public function library(Request $request): JsonResponse
    {
        abort_unless((bool) $request->user()?->can('media.view'), 403);

        $search = $request->string('search')->toString();
        $perPage = max(1, min((int) $request->integer('per_page', 24), 60));

        $query = Media::query()
            ->with('model')
            ->where('model_type', MediaAsset::class)
            ->whereIn('model_id', MediaAsset::query()->select('id'))
            ->where('mime_type', 'like', 'image/%')
            ->latest('id');
        if ($search !== '') {
            $query->where(function (Builder $q) use ($search): void {
                $q->where('file_name', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        }

        $media = $query->paginate($perPage);

        return response()->json([
            'data' => array_map(fn (Media $m): array => $this->present($m), $media->items()),
            'meta' => [
                'current_page' => $media->currentPage(),
                'last_page' => $media->lastPage(),
                'total' => $media->total(),
            ],
        ]);
    }

    /**
     * Upload a reusable image from the editor and return it as JSON so the
     * picker can insert it immediately without a full-page reload.
     */
    public function upload(MediaUploadRequest $request): JsonResponse
    {
        abort_unless((bool) $request->user()?->can('media.create'), 403);

        $userId = $request->user()->id;

        $media = DB::transaction(function () use ($request, $userId): Media {
            $asset = new MediaAsset;
            $asset->title = $request->input('title');
            $asset->is_decorative = $request->boolean('is_decorative');
            $asset->alt = $asset->is_decorative ? null : $request->input('alt');
            $asset->uploaded_by = $userId;
            $asset->save();

            return $asset->addMediaFromRequest('file')->toMediaCollection('asset');
        });

        return response()->json(['data' => $this->present($media)]);
    }

    /**
     * Edit metadata (display name, alt-text, caption) of a library-owned asset.
     * Alt and caption are used as defaults when the image is inserted into a body.
     */
    public function update(Request $request, Media $media): RedirectResponse
    {
        abort_unless((bool) $request->user()?->can('media.edit'), 403);

        $asset = $media->model;
        if ($media->model_type !== MediaAsset::class || ! $asset instanceof MediaAsset) {
            return back()->with('error', 'Метаданные можно менять только у файлов медиабиблиотеки.');
        }

        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'alt' => ['nullable', 'string', 'max:255'],
            'caption' => ['nullable', 'string', 'max:500'],
            'is_decorative' => ['sometimes', 'boolean'],
            'focal_x' => ['nullable', 'numeric', 'between:0,1'],
            'focal_y' => ['nullable', 'numeric', 'between:0,1'],
        ]);

        $name = $validated['name'] ?? null;
        $isImage = str_starts_with((string) $media->mime_type, 'image/');
        $isDecorative = array_key_exists('is_decorative', $validated)
            ? (bool) $validated['is_decorative']
            : $asset->is_decorative;
        $alt = $isDecorative ? null : ($validated['alt'] ?? null);

        if ($isImage && ! $isDecorative && blank($alt)) {
            throw ValidationException::withMessages([
                'alt' => 'Добавьте alt-текст или отметьте изображение как декоративное.',
            ]);
        }

        $asset->update([
            'title' => $name,
            'alt' => $alt,
            'caption' => $validated['caption'] ?? null,
            'is_decorative' => $isDecorative,
        ]);

        // Keep the displayed media name in sync with the title.
        if ($name !== null && $name !== '') {
            $media->name = $name;
            $media->save();
        }

        if ($isImage) {
            $media
                ->setCustomProperty('focal_point', [
                    'x' => (float) ($validated['focal_x'] ?? 0.5),
                    'y' => (float) ($validated['focal_y'] ?? 0.5),
                ])
                ->saveQuietly();
        }

        return back()->with('success', 'Метаданные обновлены.');
    }

    public function retryConversions(
        Request $request,
        Media $media,
        FileManipulator $fileManipulator,
    ): RedirectResponse {
        abort_unless((bool) $request->user()?->can('media.create'), 403);

        if (! str_starts_with((string) $media->mime_type, 'image/')) {
            return back()->with('error', 'Производные файлы создаются только для изображений.');
        }

        $fileManipulator->createDerivedFiles(
            media: $media,
            queueAll: true,
        );

        return back()->with('success', 'Изображение повторно поставлено в очередь обработки.');
    }

    // ---------------------------------------------------------------- helpers

    /**
     * @return array<string, mixed>
     */
    private function present(Media $m): array
    {
        $mime = (string) $m->mime_type;
        $asset = $m->model instanceof MediaAsset
            ? $m->model
            : ($m->model_type === MediaAsset::class
                ? MediaAsset::withTrashed()->find($m->model_id)
                : null);
        $isImage = str_starts_with($mime, 'image/');
        $conversionStatus = $m->getCustomProperty('conversion_status');
        $conversionError = $m->getCustomProperty('conversion_error');
        $focalPoint = $m->getCustomProperty('focal_point');
        $focalPoint = is_array($focalPoint) ? $focalPoint : [];

        return [
            'id' => $m->id,
            'url' => $m->getUrl(),
            'name' => $m->name,
            'file_name' => $m->file_name,
            'ext' => strtoupper(pathinfo($m->file_name, PATHINFO_EXTENSION) ?: 'FILE'),
            'mime' => $mime,
            'kind' => $isImage ? 'image' : 'file',
            'srcset' => $isImage ? MediaAsset::cmsThumbnailSrcset($m) : null,
            'conversion_status' => $isImage
                ? (is_string($conversionStatus) ? $conversionStatus : 'pending')
                : null,
            'conversion_error' => is_string($conversionError) ? $conversionError : null,
            'size' => $this->humanSize((int) $m->size),
            'collection' => $m->collection_name,
            'usage' => $this->usageLabel($m->model_type),
            'owned' => $m->model_type === MediaAsset::class,
            'title' => $asset?->title,
            'alt' => $asset?->alt,
            'caption' => $asset?->caption,
            'is_decorative' => $asset instanceof MediaAsset && $asset->is_decorative,
            'focal_point' => [
                'x' => max(0.0, min(1.0, (float) ($focalPoint['x'] ?? 0.5))),
                'y' => max(0.0, min(1.0, (float) ($focalPoint['y'] ?? 0.5))),
            ],
            'trashed' => $asset instanceof MediaAsset && $asset->trashed(),
            'uploaded_at' => $m->created_at?->toIso8601String(),
        ];
    }

    private function usageLabel(?string $modelType): string
    {
        return match ($modelType) {
            MediaAsset::class => 'Библиотека',
            News::class => 'Новость',
            Document::class => 'Документ',
            Alert::class => 'Предупреждение',
            Instruction::class => 'Инструкция',
            Project::class => 'Проект',
            default => 'Прочее',
        };
    }

    private function humanSize(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 Б';
        }

        $units = ['Б', 'КБ', 'МБ', 'ГБ'];
        $power = (int) min(floor(log($bytes, 1024)), count($units) - 1);
        $value = $bytes / (1024 ** $power);

        return ($power === 0 ? (string) $bytes : number_format($value, 1, '.', ' ')).' '.$units[$power];
    }
}
