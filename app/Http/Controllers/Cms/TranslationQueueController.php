<?php

namespace App\Http\Controllers\Cms;

use App\Enums\ContentStatus;
use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Document;
use App\Models\Instruction;
use App\Models\News;
use App\Models\Page;
use App\Models\Project;
use App\Models\User;
use App\Support\EditorialContent;
use App\Support\TranslationQueueRow;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class TranslationQueueController extends Controller
{
    /**
     * @var array<string, array{
     *     label: string,
     *     title_attribute: string,
     *     fields: list<string>
     * }>
     */
    private const TYPE_META = [
        'news' => [
            'label' => 'Новости',
            'title_attribute' => 'title',
            'fields' => ['title', 'summary', 'body', 'seo.title', 'seo.description'],
        ],
        'pages' => [
            'label' => 'Страницы',
            'title_attribute' => 'title',
            'fields' => ['title', 'body', 'seo_title', 'seo_description'],
        ],
        'projects' => [
            'label' => 'Проекты',
            'title_attribute' => 'title',
            'fields' => ['title', 'summary', 'body'],
        ],
        'instructions' => [
            'label' => 'Инструкции',
            'title_attribute' => 'name',
            'fields' => ['name', 'summary', 'body'],
        ],
        'announcements' => [
            'label' => 'Объявления',
            'title_attribute' => 'title',
            'fields' => ['title', 'body'],
        ],
        'documents' => [
            'label' => 'Документы',
            'title_attribute' => 'name',
            'fields' => ['name'],
        ],
    ];

    /**
     * @var list<string>
     */
    private const LOCALES = ['tg', 'ru', 'en'];

    public function __construct(private readonly EditorialContent $content) {}

    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $allowedTypes = array_values(array_filter(
            $this->content->types(),
            fn (string $type): bool => $user->can("{$type}.edit"),
        ));

        abort_if($allowedTypes === [], 403);

        $requestedType = $request->string('type')->toString();
        $selectedTypes = $requestedType !== '' && in_array($requestedType, $allowedTypes, true)
            ? [$requestedType]
            : $allowedTypes;
        $selectedType = count($selectedTypes) === 1 ? $selectedTypes[0] : '';
        $requestedLocale = $request->string('locale')->toString();
        $selectedLocale = in_array($requestedLocale, self::LOCALES, true)
            ? $requestedLocale
            : '';
        $locales = $selectedLocale === '' ? self::LOCALES : [$selectedLocale];
        $paginator = $this->queueQuery($selectedTypes, $locales, $user)
            ->orderByRaw("CASE status WHEN 'translation_check' THEN 0 WHEN 'returned' THEN 1 WHEN 'review' THEN 2 ELSE 3 END")
            ->orderBy('updated_at')
            ->orderBy('id')
            ->paginate(25)
            ->withQueryString();
        $rows = array_values(array_map(
            fn (object $item): TranslationQueueRow => TranslationQueueRow::fromRow($item),
            $paginator->items(),
        ));
        $models = $this->loadModels($rows);

        return Inertia::render('editorial/translations', [
            'items' => array_map(
                fn (TranslationQueueRow $item): array => $this->presentRow($item, $models, $user),
                $rows,
            ),
            'meta' => $this->paginationMeta($paginator),
            'filters' => [
                'type' => $selectedType,
                'locale' => $selectedLocale,
            ],
            'types' => array_map(
                fn (string $type): array => [
                    'value' => $type,
                    'label' => self::TYPE_META[$type]['label'],
                ],
                $allowedTypes,
            ),
            'locales' => [
                ['value' => 'tg', 'label' => 'Тоҷикӣ'],
                ['value' => 'ru', 'label' => 'Русский'],
                ['value' => 'en', 'label' => 'English'],
            ],
        ]);
    }

    /**
     * @param  list<string>  $types
     * @param  list<string>  $locales
     */
    private function queueQuery(array $types, array $locales, User $user): Builder
    {
        $queries = [];

        foreach ($types as $type) {
            $meta = self::TYPE_META[$type];
            $query = $this->modelQuery($type, $user)
                ->select(['id', 'status', 'updated_at', 'author_id'])
                ->selectRaw('? as content_type', [$type])
                ->selectRaw("{$meta['title_attribute']} as title_data");

            $query->where(function (EloquentBuilder $missing) use ($meta, $locales): void {
                foreach ($locales as $locale) {
                    foreach ($meta['fields'] as $field) {
                        $path = $this->databaseJsonPath($field, $locale);
                        $missing->orWhereNull($path)->orWhere($path, '');
                    }
                }
            });

            $queries[] = $query->toBase();
        }

        $union = array_shift($queries);
        abort_if($union === null, 403);

        foreach ($queries as $query) {
            $union->unionAll($query);
        }

        return DB::query()->fromSub($union, 'translation_queue');
    }

    /**
     * @return EloquentBuilder<covariant Model>
     */
    private function modelQuery(string $type, User $user): EloquentBuilder
    {
        return match ($type) {
            'news' => News::query()->accessibleTo($user),
            'pages' => Page::query()->accessibleTo($user),
            'projects' => Project::query()->accessibleTo($user),
            'instructions' => Instruction::query()->accessibleTo($user),
            'announcements' => Announcement::query()->accessibleTo($user),
            'documents' => Document::query()->accessibleTo($user),
            default => abort(404),
        };
    }

    private function databaseJsonPath(string $field, string $locale): string
    {
        if (str_starts_with($field, 'seo.')) {
            return 'seo->'.$locale.'->'.str($field)->after('.')->toString();
        }

        return "{$field}->{$locale}";
    }

    /**
     * @param  list<TranslationQueueRow>  $rows
     * @return Collection<string, Model>
     */
    private function loadModels(array $rows): Collection
    {
        $grouped = collect($rows)->groupBy(
            fn (TranslationQueueRow $row): string => $row->contentType,
        );
        $models = collect();

        foreach ($grouped as $type => $typeRows) {
            $modelClass = $this->content->modelClass((string) $type);
            $modelClass::query()
                ->whereKey($typeRows->map(fn (TranslationQueueRow $row): int => $row->id))
                ->get()
                ->each(function (Model $model) use ($models, $type): void {
                    $models->put("{$type}-{$model->getKey()}", $model);
                });
        }

        return $models;
    }

    /**
     * @param  Collection<string, Model>  $models
     * @return array<string, mixed>
     */
    private function presentRow(TranslationQueueRow $item, Collection $models, User $user): array
    {
        $type = $item->contentType;
        $model = $models->get("{$type}-{$item->id}");

        abort_unless($model instanceof Model && $user->can('update', $model), 403);

        $languages = $this->languageCompleteness($type, $model);
        $updatedAt = $model->getAttribute($model->getUpdatedAtColumn());

        return [
            'key' => "{$type}-{$item->id}",
            'id' => $item->id,
            'type' => $type,
            'type_label' => self::TYPE_META[$type]['label'],
            'title' => $this->localizedTitle($item->titleData),
            'status' => ContentStatus::tryFrom($item->status)?->label() ?? $item->status,
            'languages' => $languages,
            'missing_locales' => array_keys(array_filter(
                $languages,
                fn (int $percent): bool => $percent < 100,
            )),
            'updated_at' => $updatedAt instanceof CarbonInterface
                ? $updatedAt->format('d.m.Y H:i')
                : null,
            'edit_url' => route("{$type}.edit", $model, false),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function languageCompleteness(string $type, Model $model): array
    {
        return match (true) {
            $model instanceof News => $model->languageCompleteness(),
            $model instanceof Page => $model->languageCompleteness(),
            $model instanceof Project => $model->languageCompleteness(),
            $model instanceof Instruction => $model->languageCompleteness(),
            $model instanceof Announcement => $model->languageCompleteness(),
            $model instanceof Document => $this->calculateCompleteness($type, $model),
            default => [],
        };
    }

    /**
     * @return array<string, int>
     */
    private function calculateCompleteness(string $type, Model $model): array
    {
        $fields = self::TYPE_META[$type]['fields'];
        $result = [];

        foreach (self::LOCALES as $locale) {
            $filled = count(array_filter(
                $fields,
                fn (string $field): bool => trim($this->translationValue($model, $field, $locale)) !== '',
            ));
            $result[$locale] = (int) round($filled / count($fields) * 100);
        }

        return $result;
    }

    private function translationValue(Model $model, string $field, string $locale): string
    {
        if (str_starts_with($field, 'seo.')) {
            return (string) data_get($model->getAttribute('seo'), $locale.'.'.str($field)->after('.'));
        }

        return (string) match (true) {
            $model instanceof News,
            $model instanceof Page,
            $model instanceof Project,
            $model instanceof Instruction,
            $model instanceof Announcement,
            $model instanceof Document => $model->getTranslation($field, $locale, false),
            default => '',
        };
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
