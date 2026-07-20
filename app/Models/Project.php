<?php

namespace App\Models;

use App\Concerns\HasWorkflow;
use App\Concerns\TracksTranslationCompleteness;
use App\Contracts\Workflowable;
use App\Enums\ContentStatus;
use App\Enums\ProjectStatus;
use App\Models\Concerns\HasResponsiveThumbnails;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Translatable\HasTranslations;

/**
 * @property int $id
 * @property array<string, string> $title
 * @property array<string, string> $summary
 * @property array<string, string> $body
 * @property string|null $slug
 * @property ContentStatus $status
 * @property ProjectStatus $lifecycle_status
 * @property string|null $code
 * @property string|null $years
 * @property string|null $customer
 * @property string|null $partner
 * @property string|null $budget
 * @property array<string, mixed>|null $goals
 * @property array<int, mixed>|null $timeline
 * @property array<string, mixed>|null $direction
 * @property Carbon|null $published_at
 * @property int $sort
 * @property int|null $author_id
 */
class Project extends Model implements HasMedia, Workflowable
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory, HasResponsiveThumbnails, HasWorkflow, InteractsWithMedia, LogsActivity, SoftDeletes, TracksTranslationCompleteness {
        HasResponsiveThumbnails::registerMediaConversions insteadof InteractsWithMedia;
    }

    use HasTranslations;

    /**
     * @var list<string>
     */
    public array $translatable = ['title', 'summary', 'body'];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'summary',
        'body',
        'slug',
        'status',
        'lifecycle_status',
        'code',
        'years',
        'customer',
        'partner',
        'budget',
        'goals',
        'timeline',
        'direction',
        'published_at',
        'sort',
        'author_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ContentStatus::class,
            'lifecycle_status' => ProjectStatus::class,
            'goals' => 'array',
            'timeline' => 'array',
            'direction' => 'array',
            'published_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['title', 'status', 'lifecycle_status', 'published_at'])
            ->logOnlyDirty()
            ->useLogName('projects');
    }

    protected static function booted(): void
    {
        static::saving(function (Project $project): void {
            if (blank($project->slug)) {
                $source = $project->getTranslation('title', 'ru', false)
                    ?: $project->getTranslation('title', 'tg', false);
                $project->slug = self::uniqueSlug($source, $project->getKey());
            }
        });
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('cover')->singleFile();
    }

    /**
     * A URL-safe slug unique across the table (including trashed rows).
     */
    public static function uniqueSlug(string $source, ?int $ignoreId = null): string
    {
        $base = Str::slug($source, '-', 'ru');

        if ($base === '') {
            $base = 'project-'.Str::lower(Str::random(6));
        }

        $slug = $base;
        $suffix = 2;

        while (self::slugExists($slug, $ignoreId)) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    private static function slugExists(string $slug, ?int $ignoreId): bool
    {
        return self::withTrashed()
            ->where('slug', $slug)
            ->when($ignoreId !== null, fn (Builder $q) => $q->whereKeyNot($ignoreId))
            ->exists();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * Publicly visible projects (a public status), ordered for display.
     *
     * @param  Builder<Project>  $query
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
     * @param  Builder<Project>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort')->orderByDesc('published_at')->orderByDesc('id');
    }
}
