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
 * @property array<string, string> $label
 * @property string|null $url
 * @property string $location
 * @property int|null $parent_id
 * @property int $sort
 * @property bool $enabled
 */
class MenuItem extends Model
{
    use FlushesPublicCache, HasTranslations, LogsActivity, TracksTranslationCompleteness;

    /**
     * D-2: MenuController@index caches its whole (locale-resolved) tree —
     * any item could feed it, so any save/delete flushes every locale.
     */
    protected static function publicCacheKey(string $locale): string
    {
        return "public-api:menu:{$locale}";
    }

    /**
     * @var list<string>
     */
    public array $translatable = ['label'];

    /**
     * @var list<string>
     */
    protected $fillable = ['label', 'url', 'location', 'parent_id', 'sort', 'enabled'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['enabled' => 'boolean'];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['label', 'url', 'location', 'parent_id', 'sort', 'enabled'])
            ->logOnlyDirty()
            ->useLogName('menu_items');
    }
}
