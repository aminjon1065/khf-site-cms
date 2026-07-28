<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Editorial\StoreEditorialAutosaveRequest;
use App\Models\EditorialRevision;
use App\Models\User;
use App\Services\EditorialRevisionService;
use App\Support\EditorialContent;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EditorialAutosaveController extends Controller
{
    public function __construct(
        private readonly EditorialContent $content,
        private readonly EditorialRevisionService $revisions,
    ) {}

    public function store(StoreEditorialAutosaveRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $type = (string) $validated['content_type'];
        $contentId = isset($validated['content_id']) ? (int) $validated['content_id'] : null;
        $model = $this->authorizedModel($request, $type, $contentId);
        $serverVersion = $model ? $this->content->version($model) : null;
        $clientVersion = isset($validated['base_version']) ? (string) $validated['base_version'] : null;
        $latest = $this->latestAutosave(
            $type,
            $contentId,
            (string) $validated['draft_key'],
            $request->user(),
        );
        $openedAt = CarbonImmutable::parse((string) $validated['opened_at']);
        $revisionCursor = isset($validated['revision_cursor']) ? (int) $validated['revision_cursor'] : null;
        $hasConcurrentAutosave = $latest !== null
            && $latest->created_at->greaterThan($openedAt)
            && $latest->id !== $revisionCursor;
        $hasModelConflict = $model !== null
            && $clientVersion !== null
            && $clientVersion !== $serverVersion;

        if (($hasConcurrentAutosave || $hasModelConflict) && ! ($validated['force'] ?? false)) {
            $remote = $latest instanceof EditorialRevision
                ? $latest->data
                : ($model ? $this->content->snapshot($model) : []);

            return response()->json([
                'message' => 'Материал изменён в другой вкладке или другим сотрудником.',
                'remote' => $remote,
                'revision_id' => $latest?->id,
                'saved_by' => $latest?->user?->name,
                'saved_at' => $latest?->created_at?->toIso8601String(),
                'server_version' => $serverVersion,
            ], 409);
        }

        /** @var User $user */
        $user = $request->user();
        $revision = $this->revisions->autosave(
            $type,
            $contentId,
            $user,
            (string) $validated['draft_key'],
            $validated['data'],
            $serverVersion,
        );

        return response()->json([
            'revision_id' => $revision->id,
            'saved_at' => $revision->created_at->toIso8601String(),
            'server_version' => $serverVersion,
        ], 201);
    }

    public function index(Request $request, string $contentType, int $contentId): JsonResponse
    {
        $this->authorizedModel($request, $contentType, $contentId);

        $revisions = EditorialRevision::query()
            ->with('user:id,name')
            ->where('content_type', $contentType)
            ->where('content_id', $contentId)
            ->latest('id')
            ->limit(30)
            ->get()
            ->map(fn (EditorialRevision $revision): array => [
                'id' => $revision->id,
                'source' => $revision->source,
                'saved_by' => $revision->user?->name,
                'saved_at' => $revision->created_at->toIso8601String(),
            ]);

        return response()->json(['data' => $revisions]);
    }

    public function restore(Request $request, EditorialRevision $revision): JsonResponse
    {
        abort_if($revision->content_id === null, 422, 'Нельзя восстановить несвязанный черновик.');

        $model = $this->authorizedModel(
            $request,
            $revision->content_type,
            (int) $revision->content_id,
        );

        DB::transaction(function () use ($revision, $request): void {
            $lockedModel = $this->content->resolve(
                $revision->content_type,
                (int) $revision->content_id,
                lockForUpdate: true,
            );
            $this->revisions->capture($lockedModel, $request->user(), 'before_restore');
            $this->content->restore($lockedModel, $revision->data);
            $lockedModel->save();
        });

        $model->refresh();

        return response()->json([
            'data' => $this->content->snapshot($model),
            'server_version' => $this->content->version($model),
        ]);
    }

    private function authorizedModel(Request $request, string $type, ?int $contentId): ?Model
    {
        if ($contentId === null) {
            $this->authorize('create', $this->content->modelClass($type));

            return null;
        }

        $model = $this->content->resolve($type, $contentId);
        $this->authorize('update', $model);

        return $model;
    }

    private function latestAutosave(
        string $type,
        ?int $contentId,
        string $draftKey,
        ?User $user,
    ): ?EditorialRevision {
        return EditorialRevision::query()
            ->with('user:id,name')
            ->where('content_type', $type)
            ->where('source', 'autosave')
            ->when(
                $contentId === null,
                fn ($query) => $query
                    ->whereNull('content_id')
                    ->where('draft_key', $draftKey)
                    ->where('user_id', $user?->getKey()),
                fn ($query) => $query->where('content_id', $contentId),
            )
            ->latest('id')
            ->first();
    }
}
