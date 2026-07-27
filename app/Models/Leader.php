<?php

namespace App\Models;

use App\Concerns\FlushesPublicCache;
use App\Concerns\TracksTranslationCompleteness;
use App\Models\Concerns\HasResponsiveThumbnails;
use Database\Factories\LeaderFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Translatable\HasTranslations;

/**
 * A member of the Committee's leadership roster (C-1a): the chairman (exactly
 * one, `is_chairman`) plus deputy chairmen, shown on the public "Leadership"
 * page. Unlike the editorial content types this has no workflow/status — a
 * saved row is immediately live, the same as `Region`.
 *
 * @property int $id
 * @property array<string, string> $role
 * @property array<string, string> $name
 * @property array<string, string>|null $meta
 * @property array<string, string>|null $bio
 * @property bool $is_chairman
 * @property int $sort
 */
class Leader extends Model implements HasMedia
{
    /** @use HasFactory<LeaderFactory> */
    use FlushesPublicCache, HasFactory, HasResponsiveThumbnails, HasTranslations, InteractsWithMedia, LogsActivity, TracksTranslationCompleteness {
        HasResponsiveThumbnails::registerMediaConversions insteadof InteractsWithMedia;
    }

    protected static function publicCacheKey(string $locale): string
    {
        return "public-api:leadership:{$locale}";
    }

    /**
     * @var list<string>
     */
    public array $translatable = ['role', 'name', 'meta', 'bio'];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'role',
        'name',
        'meta',
        'bio',
        'is_chairman',
        'sort',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_chairman' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['role', 'name', 'is_chairman', 'sort'])
            ->logOnlyDirty()
            ->useLogName('leaders');
    }

    /**
     * At most one chairman at a time: promoting a row demotes whichever row
     * currently holds the flag, so editors never have to remember to unset it
     * themselves first.
     */
    protected static function booted(): void
    {
        static::saving(function (Leader $leader): void {
            if ($leader->is_chairman) {
                self::query()
                    ->where('is_chairman', true)
                    ->when($leader->exists, fn (Builder $q) => $q->whereKeyNot($leader->getKey()))
                    ->update(['is_chairman' => false]);
            }
        });
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('photo')->singleFile();
    }

    /**
     * The chairman first, then deputies in their configured order.
     *
     * @param  Builder<Leader>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderByDesc('is_chairman')->orderBy('sort')->orderBy('id');
    }
}
