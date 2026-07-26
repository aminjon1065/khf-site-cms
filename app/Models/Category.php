<?php

namespace App\Models;

use App\Concerns\TracksTranslationCompleteness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Translatable\HasTranslations;

/**
 * @property int $id
 * @property string $type
 * @property array<string, string> $name
 * @property string $slug
 * @property int $sort
 */
class Category extends Model
{
    use HasTranslations, LogsActivity, TracksTranslationCompleteness;

    /**
     * @var list<string>
     */
    public array $translatable = ['name'];

    /**
     * @var list<string>
     */
    protected $fillable = ['type', 'name', 'slug', 'sort'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['type', 'name', 'slug', 'sort'])
            ->logOnlyDirty()
            ->useLogName('categories');
    }

    /**
     * D-2: CategoryController@index caches its response per (type, locale) —
     * narrower than FlushesPublicCache's locale-only keys, since a category
     * of one type can't go stale for a request filtered to another type.
     */
    protected static function booted(): void
    {
        static::saved(function (self $category): void {
            self::flushPublicCache($category->type);
        });
        static::deleted(function (self $category): void {
            self::flushPublicCache($category->type);
        });
    }

    private static function flushPublicCache(string $type): void
    {
        foreach (['tg', 'ru', 'en'] as $locale) {
            Cache::forget("public-api:categories:{$type}:{$locale}");
            // The untyped ("no ?type= filter") response includes every
            // category, so it's stale whenever any category changes too.
            Cache::forget("public-api:categories:_all:{$locale}");
        }
    }

    /**
     * @return HasMany<News, $this>
     */
    public function news(): HasMany
    {
        return $this->hasMany(News::class);
    }
}
