<?php

namespace App\Models;

use App\Enums\RegionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Translatable\HasTranslations;

/**
 * @property int $id
 * @property array<string, string> $name
 * @property array<string, string>|null $head
 * @property string $code
 * @property RegionType $type
 * @property string|null $regional_center
 * @property array<string, string>|null $address
 * @property string|null $phone
 * @property string|null $duty_phone
 * @property string|null $email
 * @property int $districts_count
 * @property string $status
 * @property int $sort
 */
class Region extends Model
{
    use HasTranslations, LogsActivity;

    /**
     * @var list<string>
     */
    public array $translatable = ['name', 'head', 'address'];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'head',
        'code',
        'type',
        'regional_center',
        'address',
        'phone',
        'duty_phone',
        'email',
        'districts_count',
        'status',
        'sort',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => RegionType::class,
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'code', 'type', 'regional_center', 'phone', 'duty_phone', 'email', 'districts_count', 'sort'])
            ->logOnlyDirty()
            ->useLogName('regions');
    }

    /**
     * @return HasMany<District, $this>
     */
    public function districts(): HasMany
    {
        return $this->hasMany(District::class)->orderBy('sort');
    }

    /**
     * @return BelongsToMany<Alert, $this>
     */
    public function alerts(): BelongsToMany
    {
        return $this->belongsToMany(Alert::class);
    }
}
