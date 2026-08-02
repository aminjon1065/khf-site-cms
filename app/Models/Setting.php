<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property int $id
 * @property string $group
 * @property string $key
 * @property mixed $value
 */
class Setting extends Model
{
    use LogsActivity;

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

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['group', 'key'])
            ->logOnlyDirty()
            ->useLogName('settings');
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
