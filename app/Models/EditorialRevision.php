<?php

namespace App\Models;

use Database\Factories\EditorialRevisionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $content_type
 * @property int|null $content_id
 * @property int|null $user_id
 * @property string $draft_key
 * @property array<string, mixed> $data
 * @property string|null $base_version
 * @property string $source
 */
class EditorialRevision extends Model
{
    /** @use HasFactory<EditorialRevisionFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'content_type',
        'content_id',
        'user_id',
        'draft_key',
        'data',
        'base_version',
        'source',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'data' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
