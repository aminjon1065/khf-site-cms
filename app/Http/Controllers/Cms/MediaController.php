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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * The media library: browse every file in the system (content covers, document
 * files and reusable library assets), upload new reusable assets, and remove
 * library-owned assets. Content media stays managed by its own editor.
 */
class MediaController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless((bool) $request->user()?->can('media.view'), 403);

        $kind = $request->string('kind')->toString();
        $search = $request->string('search')->toString();
        $perPage = max(1, min((int) $request->integer('per_page', 24), 100));

        $query = Media::query()->latest('id');

        if ($kind === 'image') {
            $query->where('mime_type', 'like', 'image/%');
        } elseif ($kind === 'file') {
            $query->where('mime_type', 'not like', 'image/%');
        }
        if ($search !== '') {
            $query->where(function (Builder $q) use ($search): void {
                $q->where('file_name', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
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
            'filters' => ['kind' => $kind, 'search' => $search],
            'stats' => [
                'total' => Media::query()->count(),
                'images' => Media::query()->where('mime_type', 'like', 'image/%')->count(),
                'library' => Media::query()->where('model_type', MediaAsset::class)->count(),
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
            $asset->alt = $request->input('alt');
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

        // Deleting the owner asset cascades its media file.
        $owner = $media->model;
        if ($owner !== null) {
            $owner->delete();
        } else {
            $media->delete();
        }

        return back()->with('success', 'Файл удалён.');
    }

    // ---------------------------------------------------------------- helpers

    /**
     * @return array<string, mixed>
     */
    private function present(Media $m): array
    {
        $mime = (string) $m->mime_type;

        return [
            'id' => $m->id,
            'url' => $m->getUrl(),
            'name' => $m->name,
            'file_name' => $m->file_name,
            'ext' => strtoupper(pathinfo($m->file_name, PATHINFO_EXTENSION) ?: 'FILE'),
            'mime' => $mime,
            'kind' => str_starts_with($mime, 'image/') ? 'image' : 'file',
            'size' => $this->humanSize((int) $m->size),
            'collection' => $m->collection_name,
            'usage' => $this->usageLabel($m->model_type),
            'owned' => $m->model_type === MediaAsset::class,
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
