<?php

namespace App\Models;

use App\Enums\ContentStatus;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

/**
 * @property int $id
 * @property array<string, string> $title
 * @property array<string, string> $body
 * @property string $slug
 * @property ContentStatus $status
 * @property int|null $parent_id
 * @property int $sort
 */
class Page extends Model
{
    use HasTranslations;

    /**
     * @var list<string>
     */
    public array $translatable = ['title', 'body'];

    /**
     * @var list<string>
     */
    protected $fillable = ['title', 'body', 'slug', 'status', 'parent_id', 'sort', 'author_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['status' => ContentStatus::class];
    }
}
