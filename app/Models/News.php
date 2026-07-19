<?php

namespace App\Models;

use App\Concerns\HasTags;
use App\Concerns\HasWorkflow;
use App\Concerns\TracksTranslationCompleteness;
use App\Contracts\Workflowable;
use App\Enums\ContentStatus;
use Database\Factories\NewsFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
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
    use HasFactory, HasTags, HasWorkflow, InteractsWithMedia, LogsActivity, SoftDeletes, TracksTranslationCompleteness;

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

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('cover')->singleFile();
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
}
