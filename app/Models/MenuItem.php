<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
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
    use HasTranslations;

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
}
