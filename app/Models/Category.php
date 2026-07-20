<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
    use HasTranslations;

    /**
     * @var list<string>
     */
    public array $translatable = ['name'];

    /**
     * @var list<string>
     */
    protected $fillable = ['type', 'name', 'slug', 'sort'];

    /**
     * @return HasMany<News, $this>
     */
    public function news(): HasMany
    {
        return $this->hasMany(News::class);
    }
}
