<?php

namespace App\Models;

use App\Enums\SubmissionStatus;
use Database\Factories\SubmissionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property int $id
 * @property string|null $tracking_number
 * @property string $name
 * @property string $email
 * @property string|null $phone
 * @property string|null $topic
 * @property string $message
 * @property int|null $region_id
 * @property bool $consent
 * @property SubmissionStatus $status
 * @property int|null $assigned_to
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property Carbon|null $created_at
 * @property-read User|null $assignee
 */
class Submission extends Model
{
    /** @use HasFactory<SubmissionFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tracking_number',
        'name',
        'email',
        'phone',
        'topic',
        'message',
        'region_id',
        'consent',
        'status',
        'assigned_to',
        'ip_address',
        'user_agent',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'consent' => 'boolean',
            'status' => SubmissionStatus::class,
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'assigned_to'])
            ->logOnlyDirty()
            ->useLogName('submissions');
    }

    protected static function booted(): void
    {
        static::created(function (Submission $submission): void {
            if (blank($submission->tracking_number)) {
                $submission->tracking_number = 'КЧС-'.now()->format('Y').'-'.str_pad((string) $submission->id, 5, '0', STR_PAD_LEFT);
                $submission->saveQuietly();
            }
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * @return BelongsTo<Region, $this>
     */
    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    /**
     * @return HasMany<SubmissionComment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(SubmissionComment::class)->oldest();
    }

    /**
     * Open (not completed / rejected / spam) submissions.
     *
     * @param  Builder<Submission>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->whereNotIn('status', [
            SubmissionStatus::Completed->value,
            SubmissionStatus::Rejected->value,
            SubmissionStatus::Spam->value,
        ]);
    }
}
