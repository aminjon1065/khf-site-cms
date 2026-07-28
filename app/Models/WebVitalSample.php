<?php

namespace App\Models;

use Database\Factories\WebVitalSampleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $metric
 * @property float $value
 * @property string $rating
 * @property string $route
 * @property string $locale
 * @property string $device
 * @property string $navigation_type
 * @property string $sample_hash
 * @property Carbon|null $created_at
 */
class WebVitalSample extends Model
{
    /** @use HasFactory<WebVitalSampleFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'metric',
        'value',
        'rating',
        'route',
        'locale',
        'device',
        'navigation_type',
        'sample_hash',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'float',
        ];
    }
}
