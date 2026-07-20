<?php

namespace App\Models;

use App\Concerns\HasWorkflow;
use App\Concerns\TracksTranslationCompleteness;
use App\Contracts\Workflowable;
use App\Enums\ContentStatus;
use Database\Factories\PageFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Translatable\HasTranslations;

/**
 * @property int $id
 * @property array<string, string> $title
 * @property array<string, string> $body
 * @property string $slug
 * @property ContentStatus $status
 * @property Carbon|null $published_at
 * @property int|null $parent_id
 * @property int $sort
 * @property int|null $author_id
 */
class Page extends Model implements Workflowable
{
    /** @use HasFactory<PageFactory> */
    use HasFactory, HasWorkflow, LogsActivity, SoftDeletes, TracksTranslationCompleteness;

    use HasTranslations;

    /**
     * @var list<string>
     */
    public array $translatable = ['title', 'body'];

    /**
     * @var list<string>
     */
    protected $fillable = ['title', 'body', 'slug', 'status', 'published_at', 'parent_id', 'sort', 'author_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ContentStatus::class,
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Generate a stable, unique slug once from the Russian title on first
        // save; existing slugs are never silently rewritten (published URLs
        // must stay stable).
        static::saving(function (Page $page): void {
            if (blank($page->slug)) {
                $source = $page->getTranslation('title', 'ru', false)
                    ?: $page->getTranslation('title', 'tg', false);
                $page->slug = self::uniqueSlug($source, $page->getKey());
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['title', 'slug', 'status', 'parent_id', 'published_at'])
            ->logOnlyDirty()
            ->useLogName('pages');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * @return BelongsTo<Page, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Page::class, 'parent_id');
    }

    /**
     * @return HasMany<Page, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(Page::class, 'parent_id')->orderBy('sort');
    }

    /**
     * Publicly visible pages (a public status).
     *
     * @param  Builder<Page>  $query
     */
    public function scopePublic(Builder $query): void
    {
        $public = array_map(
            fn (ContentStatus $s): string => $s->value,
            array_filter(ContentStatus::cases(), fn (ContentStatus $s): bool => $s->isPublic()),
        );

        $query->whereIn('status', $public);
    }

    public static function uniqueSlug(string $source, ?int $ignoreId = null): string
    {
        $base = Str::slug($source, '-', 'ru');

        if ($base === '') {
            $base = 'page-'.Str::lower(Str::random(6));
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
}
