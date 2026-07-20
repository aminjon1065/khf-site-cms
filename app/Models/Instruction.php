<?php

namespace App\Models;

use App\Concerns\HasWorkflow;
use App\Concerns\TracksTranslationCompleteness;
use App\Contracts\Workflowable;
use App\Enums\ContentStatus;
use App\Enums\HazardType;
use Database\Factories\InstructionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
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
 * @property bool $is_priority
 * @property int $sort
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
        'is_priority',
        'sort',
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
            'is_priority' => 'boolean',
            'sections' => 'array',
            'status' => ContentStatus::class,
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Instruction $instruction): void {
            if (blank($instruction->slug)) {
                $source = $instruction->getTranslation('name', 'ru', false)
                    ?: $instruction->getTranslation('name', 'tg', false);
                $instruction->slug = self::uniqueSlug($source, $instruction->getKey());
            }
        });
    }

    /**
     * A URL-safe slug unique across the table (including trashed rows).
     */
    public static function uniqueSlug(string $source, ?int $ignoreId = null): string
    {
        $base = Str::slug($source, '-', 'ru');

        if ($base === '') {
            $base = 'instruction-'.Str::lower(Str::random(6));
        }

        $slug = $base;
        $suffix = 2;

        while (self::slugExists($slug, $ignoreId)) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    private static function slugExists(string $slug, ?int $ignoreId): bool
    {
        return self::withTrashed()
            ->where('slug', $slug)
            ->when($ignoreId !== null, fn (Builder $q) => $q->whereKeyNot($ignoreId))
            ->exists();
    }

    /**
     * Publicly visible instructions (a public status), ordered for display.
     *
     * @param  Builder<Instruction>  $query
     */
    public function scopePublic(Builder $query): void
    {
        $public = array_map(
            fn (ContentStatus $s): string => $s->value,
            array_filter(ContentStatus::cases(), fn (ContentStatus $s): bool => $s->isPublic()),
        );

        $query->whereIn('status', $public);
    }

    /**
     * @param  Builder<Instruction>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderByDesc('is_priority')->orderBy('sort')->orderBy('id');
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
