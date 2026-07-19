<?php

namespace App\Support;

use App\Contracts\Workflowable;
use App\Models\Alert;
use App\Models\Document;
use App\Models\Instruction;
use App\Models\News;
use Illuminate\Database\Eloquent\Model;

/**
 * Maps short type slugs to workflow content models. Shared by the approval
 * center, notifications and global search.
 */
class ContentTypes
{
    /**
     * @var array<string, class-string<Model>>
     */
    public const MAP = [
        'alert' => Alert::class,
        'news' => News::class,
        'instruction' => Instruction::class,
        'document' => Document::class,
    ];

    /**
     * @var array<string, array{module: string, label: string, route: string}>
     */
    public const META = [
        'alert' => ['module' => 'alerts', 'label' => 'Предупреждение', 'route' => '/alerts'],
        'news' => ['module' => 'news', 'label' => 'Новость', 'route' => '/news'],
        'instruction' => ['module' => 'instructions', 'label' => 'Инструкция', 'route' => '/instructions'],
        'document' => ['module' => 'documents', 'label' => 'Документ', 'route' => '/documents'],
    ];

    public static function slugFor(Model $model): ?string
    {
        return array_search($model::class, self::MAP, true) ?: null;
    }

    /**
     * @return (Model&Workflowable)|null
     */
    public static function resolve(string $type, int $id): ?Model
    {
        $class = self::MAP[$type] ?? null;

        if ($class === null) {
            return null;
        }

        $model = $class::query()->find($id);

        return $model instanceof Workflowable ? $model : null;
    }

    public static function label(string $type): string
    {
        return self::META[$type]['label'] ?? 'Материал';
    }

    public static function module(string $type): string
    {
        return self::META[$type]['module'] ?? $type;
    }
}
