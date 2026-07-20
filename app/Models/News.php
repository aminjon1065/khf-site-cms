<?php

namespace App\Models;

use App\Concerns\HasTags;
use App\Concerns\HasWorkflow;
use App\Concerns\TracksTranslationCompleteness;
use App\Contracts\Workflowable;
use App\Enums\ContentStatus;
use App\Models\Concerns\HasResponsiveThumbnails;
use Database\Factories\NewsFactory;
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
 * @property int|null $category_id
 * @property ContentStatus $status
 * @property string|null $cover_alt
 * @property bool $is_pinned
 * @property bool $show_on_home
 * @property int $views_count
 * @property array<string, mixed>|null $seo
 * @property Carbon|null $published_at
 * @property Carbon|null $scheduled_at
 * @property int|null $author_id
 */
class News extends Model implements HasMedia, Workflowable
{
    /** @use HasFactory<NewsFactory> */
    use HasFactory, HasResponsiveThumbnails, HasTags, HasWorkflow, InteractsWithMedia, LogsActivity, SoftDeletes, TracksTranslationCompleteness {
        HasResponsiveThumbnails::registerMediaConversions insteadof InteractsWithMedia;
    }

    use HasTranslations;

    protected $table = 'news';

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
        'category_id',
        'status',
        'cover_alt',
        'is_pinned',
        'show_on_home',
        'views_count',
        'seo',
        'published_at',
        'scheduled_at',
        'author_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ContentStatus::class,
            'is_pinned' => 'boolean',
            'show_on_home' => 'boolean',
            'seo' => 'array',
            'published_at' => 'datetime',
            'scheduled_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['title', 'status', 'category_id', 'published_at', 'is_pinned', 'show_on_home'])
            ->logOnlyDirty()
            ->useLogName('news');
    }

    protected static function booted(): void
    {
        // Guarantee a stable, unique slug. Generated once from the Russian
        // title on first save; existing slugs are never silently rewritten
        // (published URLs must stay stable — see redirects for renames).
        static::saving(function (News $news): void {
            if (blank($news->slug)) {
                $source = $news->getTranslation('title', 'ru', false)
                    ?: $news->getTranslation('title', 'tg', false);
                $news->slug = self::uniqueSlug($source, $news->getKey());
            }
        });
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('cover')->singleFile();
    }

    /**
     * A URL-safe slug unique across the table (including trashed rows), with a
     * numeric suffix on collision.
     */
    public static function uniqueSlug(string $source, ?int $ignoreId = null): string
    {
        $base = Str::slug($source, '-', 'ru');

        if ($base === '') {
            $base = 'news-'.Str::lower(Str::random(6));
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
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * @param  Builder<News>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', ContentStatus::Published->value);
    }

    /**
     * Publicly visible on the site: a public status and a publish date that is
     * not in the future. Used by the public API.
     *
     * @param  Builder<News>  $query
     */
    public function scopePublic(Builder $query): void
    {
        $public = array_map(
            fn (ContentStatus $s): string => $s->value,
            array_filter(ContentStatus::cases(), fn (ContentStatus $s): bool => $s->isPublic()),
        );

        $query->whereIn('status', $public)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }
}
