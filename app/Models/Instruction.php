<?php

namespace App\Models;

use App\Concerns\HasWorkflow;
use App\Concerns\TracksTranslationCompleteness;
use App\Contracts\Workflowable;
use App\Enums\ContentStatus;
use App\Enums\HazardType;
use Database\Factories\InstructionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Translatable\HasTranslations;

/**
 * @property int $id
 * @property array<string, string> $name
 * @property array<string, string> $summary
 * @property string|null $slug
 * @property HazardType|null $hazard_type
 * @property array<string, mixed>|null $sections
 * @property ContentStatus $status
 * @property Carbon|null $published_at
 * @property int|null $author_id
 */
class Instruction extends Model implements HasMedia, Workflowable
{
    /** @use HasFactory<InstructionFactory> */
    use HasFactory, HasWorkflow, InteractsWithMedia, LogsActivity, SoftDeletes, TracksTranslationCompleteness;

    use HasTranslations;

    /**
     * @var list<string>
     */
    public array $translatable = ['name', 'summary'];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'summary',
        'slug',
        'hazard_type',
        'sections',
        'status',
        'published_at',
        'author_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'hazard_type' => HazardType::class,
            'sections' => 'array',
            'status' => ContentStatus::class,
            'published_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'status', 'hazard_type', 'published_at'])
            ->logOnlyDirty()
            ->useLogName('instructions');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('image')->singleFile();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * @return BelongsToMany<Alert, $this>
     */
    public function alerts(): BelongsToMany
    {
        return $this->belongsToMany(Alert::class);
    }
}
