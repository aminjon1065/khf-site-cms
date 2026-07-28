<?php

namespace App\Support;

use App\Models\Announcement;
use App\Models\Document;
use App\Models\Instruction;
use App\Models\News;
use App\Models\Page;
use App\Models\Project;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\URL;
use InvalidArgumentException;

class EditorialContent
{
    /**
     * @var array<string, class-string<Model>>
     */
    private const MODELS = [
        'news' => News::class,
        'pages' => Page::class,
        'projects' => Project::class,
        'instructions' => Instruction::class,
        'announcements' => Announcement::class,
        'documents' => Document::class,
    ];

    /**
     * Workflow metadata and uploaded files are deliberately excluded: restoring
     * editorial text must not silently publish, unpublish, or replace binaries.
     *
     * @var array<string, list<string>>
     */
    private const FIELDS = [
        'news' => ['title', 'summary', 'body', 'slug', 'category_id', 'cover_alt', 'is_pinned', 'show_on_home', 'seo', 'scheduled_at'],
        'pages' => ['title', 'body', 'seo_title', 'seo_description', 'slug', 'parent_id', 'sort'],
        'projects' => ['title', 'summary', 'body', 'slug', 'lifecycle_status', 'code', 'years', 'customer', 'partner', 'budget', 'goals', 'timeline', 'direction', 'sort'],
        'instructions' => ['name', 'summary', 'body', 'slug', 'hazard_type', 'is_priority', 'sort', 'sections'],
        'announcements' => ['title', 'body', 'slug', 'kind', 'org', 'deadline', 'application_url'],
        'documents' => ['name', 'doc_type', 'number', 'doc_date', 'section'],
    ];

    public function supports(Model $model): bool
    {
        return $this->typeFor($model) !== null;
    }

    /**
     * @return list<string>
     */
    public function types(): array
    {
        return array_keys(self::MODELS);
    }

    /**
     * @return class-string<Model>
     */
    public function modelClass(string $type): string
    {
        return self::MODELS[$type]
            ?? throw new InvalidArgumentException("Unsupported editorial content type [{$type}].");
    }

    public function resolve(string $type, int $id, bool $lockForUpdate = false): Model
    {
        $query = $this->modelClass($type)::query();

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        return $query->findOrFail($id);
    }

    /**
     * Запрос по корзине конкретного типа.
     *
     * Типы перечислены явно, а не через `class-string<Model>`: у базовой Model
     * нет `onlyTrashed()`, он приходит из трейта SoftDeletes. Так статический
     * анализ проверяет наличие корзины у каждого типа, тогда как рантайм-проверка
     * `class_uses_recursive` для анализатора ничего не доказывает.
     *
     * @return EloquentBuilder<covariant Model>
     */
    public function trashedQuery(string $type): EloquentBuilder
    {
        return match ($type) {
            'news' => News::onlyTrashed(),
            'pages' => Page::onlyTrashed(),
            'projects' => Project::onlyTrashed(),
            'instructions' => Instruction::onlyTrashed(),
            'announcements' => Announcement::onlyTrashed(),
            'documents' => Document::onlyTrashed(),
            default => throw new InvalidArgumentException(
                "Editorial content type [{$type}] does not support trash.",
            ),
        };
    }

    /**
     * Восстановление из корзины. Вызов `restore()` тоже живёт здесь, у известного
     * конкретного класса, — у `Model` этого метода нет.
     */
    public function restoreTrashed(string $type, int $id): void
    {
        $model = match ($type) {
            'news' => News::onlyTrashed()->lockForUpdate()->findOrFail($id),
            'pages' => Page::onlyTrashed()->lockForUpdate()->findOrFail($id),
            'projects' => Project::onlyTrashed()->lockForUpdate()->findOrFail($id),
            'instructions' => Instruction::onlyTrashed()->lockForUpdate()->findOrFail($id),
            'announcements' => Announcement::onlyTrashed()->lockForUpdate()->findOrFail($id),
            'documents' => Document::onlyTrashed()->lockForUpdate()->findOrFail($id),
            default => throw new InvalidArgumentException(
                "Editorial content type [{$type}] does not support trash.",
            ),
        };

        $model->restore();
    }

    public function resolveTrashed(string $type, int $id, bool $lockForUpdate = false): Model
    {
        $query = $this->trashedQuery($type);

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        return $query->findOrFail($id);
    }

    public function typeFor(Model $model): ?string
    {
        foreach (self::MODELS as $type => $modelClass) {
            if ($model instanceof $modelClass) {
                return $type;
            }
        }

        return null;
    }

    public function version(Model $model): ?string
    {
        $updatedAt = $model->getAttribute($model->getUpdatedAtColumn());

        return $updatedAt instanceof CarbonInterface
            ? $updatedAt->toIso8601String()
            : null;
    }

    public function previewUrl(Model $model): string
    {
        $type = $this->typeFor($model)
            ?? throw new InvalidArgumentException('Unsupported editorial model.');

        return URL::temporarySignedRoute(
            'editorial.preview',
            now()->addMinutes(15),
            [
                'contentType' => $type,
                'contentId' => $model->getKey(),
                'locale' => 'ru',
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(Model $model): array
    {
        $type = $this->typeFor($model)
            ?? throw new InvalidArgumentException('Unsupported editorial model.');

        return Arr::only($model->toArray(), self::FIELDS[$type]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function restore(Model $model, array $data): void
    {
        $type = $this->typeFor($model)
            ?? throw new InvalidArgumentException('Unsupported editorial model.');

        $model->forceFill(Arr::only($data, self::FIELDS[$type]));
    }
}
