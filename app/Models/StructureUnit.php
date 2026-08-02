<?php

namespace App\Models;

use App\Concerns\TracksTranslationCompleteness;
use Database\Factories\StructureUnitFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Translatable\HasTranslations;

/**
 * A specialised department shown on the public "Structure" page (C-1b).
 * Reorganisation-driven reference data — no workflow, a saved row is
 * immediately live, the same as `Region`/`Leader`.
 *
 * @property int $id
 * @property string $num
 * @property array<string, string> $name
 * @property array<string, string> $desc
 * @property int $sort
 */
class StructureUnit extends Model
{
    /** @use HasFactory<StructureUnitFactory> */
    use HasFactory, HasTranslations, LogsActivity, TracksTranslationCompleteness;

    /**
     * @var list<string>
     */
    public array $translatable = ['name', 'desc'];

    /**
     * @var list<string>
     */
    protected $fillable = ['num', 'name', 'desc', 'sort'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['num', 'name', 'sort'])
            ->logOnlyDirty()
            ->useLogName('structure_units');
    }

    /**
     * @param  Builder<StructureUnit>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort')->orderBy('id');
    }
}
