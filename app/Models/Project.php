<?php

namespace App\Models;

use App\Enums\ContentStatus;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

/**
 * @property int $id
 * @property array<string, string> $title
 * @property array<string, string> $summary
 * @property array<string, string> $body
 * @property ContentStatus $status
 */
class Project extends Model
{
    use HasTranslations;

    /**
     * @var list<string>
     */
    public array $translatable = ['title', 'summary', 'body'];

    /**
     * @var list<string>
     */
    protected $fillable = ['title', 'summary', 'body', 'status', 'author_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['status' => ContentStatus::class];
    }
}
