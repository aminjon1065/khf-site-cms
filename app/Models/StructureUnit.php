<?php

namespace App\Models;

use App\Concerns\TracksTranslationCompleteness;
use Database\Factories\StructureUnitFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Translatable\HasTranslations;

/**
 * A unit of the Committee's structure shown on the public "Structure" page
 * (C-1b). Units nest to any depth — a main directorate, its directorates,
 * their departments — and `sort` orders a unit among its siblings.
 * Reorganisation-driven reference data — no workflow, a saved row is
 * immediately live, the same as `Region`/`Leader`.
 *
 * @property int $id
 * @property int|null $parent_id
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
    protected $fillable = ['parent_id', 'num', 'name', 'desc', 'sort'];

    /**
     * @return BelongsTo<StructureUnit, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<StructureUnit, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort')->orderBy('id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['parent_id', 'num', 'name', 'sort'])
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
