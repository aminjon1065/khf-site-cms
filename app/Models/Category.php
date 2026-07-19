<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
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
}
