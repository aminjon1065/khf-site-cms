<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $group
 * @property string $key
 * @property mixed $value
 */
class Setting extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = ['group', 'key', 'value'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'array',
        ];
    }

    /**
     * Retrieve a grouped map of all settings: [group => [key => value]].
     *
     * @return array<string, array<string, mixed>>
     */
    public static function grouped(): array
    {
        return self::all()
            ->groupBy('group')
            ->map(fn ($items) => $items->pluck('value', 'key')->all())
            ->all();
    }
}
