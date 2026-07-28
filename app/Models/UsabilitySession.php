<?php

namespace App\Models;

use Database\Factories\UsabilitySessionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $participant_code
 * @property string $role
 * @property string $experience_level
 * @property array<string, array{completed: bool, assisted: bool, duration_seconds: int, irreversible_error: bool}> $tasks
 * @property list<int> $sus_responses
 * @property float $sus_score
 * @property string|null $notes
 * @property int|null $facilitator_id
 * @property Carbon $started_at
 * @property Carbon $completed_at
 * @property Carbon|null $created_at
 * @property-read User|null $facilitator
 */
class UsabilitySession extends Model
{
    /** @use HasFactory<UsabilitySessionFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'participant_code',
        'role',
        'experience_level',
        'tasks',
        'sus_responses',
        'sus_score',
        'notes',
        'facilitator_id',
        'started_at',
        'completed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tasks' => 'array',
            'sus_responses' => 'array',
            'sus_score' => 'float',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function facilitator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'facilitator_id');
    }
}
