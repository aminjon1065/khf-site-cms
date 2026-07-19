<?php

namespace App\Models;

use App\Enums\RegionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

/**
 * @property int $id
 * @property array<string, string> $name
 * @property string $code
 * @property RegionType $type
 * @property string|null $regional_center
 * @property string|null $phone
 * @property string|null $duty_phone
 * @property int $districts_count
 * @property string $status
 * @property int $sort
 */
class Region extends Model
{
    use HasTranslations;

    /**
     * @var list<string>
     */
    public array $translatable = ['name'];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'code',
        'type',
        'regional_center',
        'phone',
        'duty_phone',
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
