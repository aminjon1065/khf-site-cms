<?php

namespace App\Http\Controllers\Cms;

use App\Enums\ContentStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\EditorialContent;
use App\Support\EditorialTrashRow;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class EditorialTrashController extends Controller
{
    /**
     * @var array<string, array{label: string, title_attribute: string}>
     */
    private const TYPE_META = [
        'news' => ['label' => 'Новости', 'title_attribute' => 'title'],
        'pages' => ['label' => 'Страницы', 'title_attribute' => 'title'],
        'projects' => ['label' => 'Проекты', 'title_attribute' => 'title'],
        'instructions' => ['label' => 'Инструкции', 'title_attribute' => 'name'],
        'announcements' => ['label' => 'Объявления', 'title_attribute' => 'title'],
        'documents' => ['label' => 'Документы', 'title_attribute' => 'name'],
    ];

    public function __construct(private readonly EditorialContent $content) {}

    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $allowedTypes = array_values(array_filter(
            $this->content->types(),
            fn (string $type): bool => $user->can("{$type}.view"),
        ));

        abort_if($allowedTypes === [], 403);

        $requestedType = $request->string('type')->toString();
        $selectedTypes = $requestedType !== '' && in_array($requestedType, $allowedTypes, true)
            ? [$requestedType]
            : $allowedTypes;
        $selectedType = count($selectedTypes) === 1 ? $selectedTypes[0] : '';
        $paginator = $this->trashQuery($selectedTypes, $user)
            ->orderByDesc('deleted_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();
        $authors = User::query()
            ->whereIn('id', collect($paginator->items())->pluck('author_id')->filter()->unique())
            ->pluck('name', 'id');

        return Inertia::render('editorial/trash', [
            'items' => array_map(
                fn (object $item): array => $this->presentRow(
                    EditorialTrashRow::fromRow($item),
                    $authors,
                    $user,
                ),
                $paginator->items(),
            ),
            'meta' => $this->paginationMeta($paginator),
            'filters' => ['type' => $selectedType],
            'types' => array_map(
                fn (string $type): array => [
                    'value' => $type,
                    'label' => self::TYPE_META[$type]['label'],
                ],
                $allowedTypes,
            ),
        ]);
    }

    public function restore(Request $request, string $contentType, int $contentId): RedirectResponse
    {
        abort_unless(array_key_exists($contentType, self::TYPE_META), 404);

        $model = $this->content->resolveTrashed($contentType, $contentId);
        $this->authorize('delete', $model);

        DB::transaction(function () use ($contentType, $contentId): void {
            $this->content->restoreTrashed($contentType, $contentId);
        });

        return redirect()
            ->route("{$contentType}.edit", $contentId)
            ->with('success', 'Материал восстановлен из корзины.');
    }

    /**
     * @param  list<string>  $types
     */
    private function trashQuery(array $types, User $user): Builder
    {
        $queries = [];

        foreach ($types as $type) {
            $titleAttribute = self::TYPE_META[$type]['title_attribute'];
            $query = $this->content->trashedQuery($type)
                ->select(['id', 'status', 'deleted_at', 'author_id'])
                ->selectRaw('? as content_type', [$type])
                ->selectRaw("{$titleAttribute} as title_data");

            if ($user->hasRole('regional_editor')) {
                $query->where('author_id', $user->id);
            }

            $queries[] = $query->toBase();
        }

        $union = array_shift($queries);
        abort_if($union === null, 403);

        foreach ($queries as $query) {
            $union->unionAll($query);
        }

        return DB::query()->fromSub($union, 'editorial_trash');
    }

    /**
     * @param  Collection<int, string>  $authors
     * @return array<string, mixed>
     */
    private function presentRow(EditorialTrashRow $item, Collection $authors, User $user): array
    {
        $type = (string) $item->contentType;
        $model = $this->trashedModelFromRow($type, $item);

        return [
            'key' => "{$type}-{$item->id}",
            'id' => (int) $item->id,
            'type' => $type,
            'type_label' => self::TYPE_META[$type]['label'],
            'title' => $this->localizedTitle($item->titleData),
            'status' => $this->statusLabel((string) $item->status),
            'author' => $item->authorId === null
                ? '—'
                : $authors->get((int) $item->authorId, '—'),
            'deleted_at' => CarbonImmutable::parse((string) $item->deletedAt)->format('d.m.Y H:i'),
            'can_restore' => $user->can('delete', $model),
        ];
    }

    private function localizedTitle(mixed $rawTitle): string
    {
        $translations = is_string($rawTitle)
            ? json_decode($rawTitle, true)
            : $rawTitle;

        if (is_array($translations)) {
            foreach (['ru', 'tg', 'en'] as $locale) {
                $title = trim((string) ($translations[$locale] ?? ''));

                if ($title !== '') {
                    return $title;
                }
            }
        }

        return 'Без названия';
    }

    private function statusLabel(string $status): string
    {
        return ContentStatus::tryFrom($status)?->label() ?? $status;
    }

    private function trashedModelFromRow(string $type, EditorialTrashRow $item): Model
    {
        $modelClass = $this->content->modelClass($type);

        return (new $modelClass)->newInstance([
            'id' => (int) $item->id,
            'author_id' => $item->authorId !== null ? (int) $item->authorId : null,
            'deleted_at' => $item->deletedAt,
        ], true);
    }

    /**
     * @param  LengthAwarePaginator<int, object>  $paginator
     * @return array{from: int|null, to: int|null, total: int, prev: string|null, next: string|null}
     */
    private function paginationMeta(LengthAwarePaginator $paginator): array
    {
        return [
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
            'total' => $paginator->total(),
            'prev' => $paginator->previousPageUrl(),
            'next' => $paginator->nextPageUrl(),
        ];
    }
}
