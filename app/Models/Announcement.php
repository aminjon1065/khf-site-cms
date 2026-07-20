<?php

namespace App\Models;

use App\Concerns\HasWorkflow;
use App\Concerns\TracksTranslationCompleteness;
use App\Contracts\Workflowable;
use App\Enums\AnnouncementKind;
use App\Enums\ContentStatus;
use Database\Factories\AnnouncementFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Translatable\HasTranslations;

/**
 * @property int $id
 * @property array<string, string> $title
 * @property array<string, string> $body
 * @property AnnouncementKind $kind
 * @property string|null $org
 * @property Carbon|null $deadline
 * @property ContentStatus $status
 * @property Carbon|null $published_at
 * @property int|null $author_id
 */
class Announcement extends Model implements Workflowable
{
    /** @use HasFactory<AnnouncementFactory> */
    use HasFactory, HasWorkflow, LogsActivity, SoftDeletes, TracksTranslationCompleteness;

    use HasTranslations;

    /**
     * @var list<string>
     */
    public array $translatable = ['title', 'body'];

    /**
     * @var list<string>
     */
    protected $fillable = ['title', 'body', 'kind', 'org', 'deadline', 'status', 'published_at', 'author_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => AnnouncementKind::class,
            'status' => ContentStatus::class,
            'deadline' => 'date',
            'published_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['title', 'kind', 'status', 'deadline', 'published_at'])
            ->logOnlyDirty()
            ->useLogName('announcements');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * Accepting applications: no deadline, or a deadline that has not passed.
     */
    public function isOpen(): bool
    {
        return $this->deadline === null || $this->deadline->endOfDay()->isFuture();
    }

    /**
     * Publicly visible announcements (a public status), soonest deadline first.
     *
     * @param  Builder<Announcement>  $query
     */
    public function scopePublic(Builder $query): void
    {
        $public = array_map(
            fn (ContentStatus $s): string => $s->value,
            array_filter(ContentStatus::cases(), fn (ContentStatus $s): bool => $s->isPublic()),
        );

        $query->whereIn('status', $public);
    }

    /**
     * Open announcements first, then by soonest deadline.
     *
     * @param  Builder<Announcement>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderByRaw('(deadline IS NULL OR deadline >= CURRENT_DATE) DESC')
            ->orderByRaw('deadline IS NULL')
            ->orderBy('deadline')
            ->orderByDesc('id');
    }
}
