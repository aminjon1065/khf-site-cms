<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * Changes to a published material waiting for an approver (see
 * PendingChangeService).
 *
 * @property int $id
 * @property string $changeable_type
 * @property int $changeable_id
 * @property int|null $user_id
 * @property array<string, mixed> $changes
 * @property array<string, list<int>>|null $relations
 * @property Carbon|null $base_updated_at
 * @property string $status
 * @property string|null $comment
 * @property int|null $decided_by
 * @property Carbon|null $decided_at
 * @property Carbon|null $created_at
 */
class PendingChange extends Model
{
    public const PENDING = 'pending';

    public const APPLIED = 'applied';

    public const REJECTED = 'rejected';

    /** Replaced by a newer proposal for the same material. */
    public const SUPERSEDED = 'superseded';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'changeable_type',
        'changeable_id',
        'user_id',
        'changes',
        'relations',
        'base_updated_at',
        'status',
        'comment',
        'decided_by',
        'decided_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'relations' => 'array',
            'base_updated_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function changeable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /**
     * @param  Builder<PendingChange>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->where('status', self::PENDING);
    }

    public function isPending(): bool
    {
        return $this->status === self::PENDING;
    }
}
