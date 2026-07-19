<?php

namespace App\Models;

use App\Enums\ContentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Spatie\Translatable\HasTranslations;

/**
 * @property int $id
 * @property array<string, string> $title
 * @property array<string, string> $body
 * @property string $kind
 * @property Carbon|null $deadline
 * @property ContentStatus $status
 * @property Carbon|null $published_at
 */
class Announcement extends Model
{
    use HasTranslations;

    /**
     * @var list<string>
     */
    public array $translatable = ['title', 'body'];

    /**
     * @var list<string>
     */
    protected $fillable = ['title', 'body', 'kind', 'deadline', 'status', 'published_at', 'author_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ContentStatus::class,
            'deadline' => 'date',
            'published_at' => 'datetime',
        ];
    }
}
