<?php

namespace App\Models;

use App\Concerns\FlushesPublicCache;
use App\Concerns\TracksTranslationCompleteness;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Translatable\HasTranslations;

/**
 * @property int $id
 * @property string $type
 * @property array<string, string> $title
 * @property bool $enabled
 * @property int $sort
 * @property array<string, mixed>|null $config
 */
class HomeBlock extends Model
{
    use FlushesPublicCache, HasTranslations, LogsActivity, TracksTranslationCompleteness;

    /**
     * D-2: HomeController@index caches its whole (locale-resolved)
     * response. Its news/alerts/etc. sub-content isn't invalidated by this
     * (only the block composition/config is) — bounded by the same 60s TTL
     * the existing ETag layer already tolerates via stale-while-revalidate.
     */
    protected static function publicCacheKey(string $locale): string
    {
        return "public-api:home:{$locale}";
    }

    /**
     * @var list<string>
     */
    public array $translatable = ['title'];

    /**
     * @var list<string>
     */
    protected $fillable = ['type', 'title', 'enabled', 'sort', 'config'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'config' => 'array',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['type', 'title', 'enabled', 'sort', 'config'])
            ->logOnlyDirty()
            ->useLogName('home_blocks');
    }
}
