<?php

namespace App\Models;

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
    use HasTranslations, LogsActivity;

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
