<?php

namespace App\Models;

use App\Concerns\HasWorkflow;
use App\Contracts\Workflowable;
use App\Enums\ContentStatus;
use App\Enums\HazardType;
use App\Enums\Severity;
use Database\Factories\AlertFactory;
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
 * @property string $internal_title
 * @property string|null $slug
 * @property array<string, string> $title
 * @property array<string, string> $summary
 * @property array<string, string> $body
 * @property array<string, string> $instructions
 * @property array<string, string> $contacts
 * @property HazardType $hazard_type
 * @property Severity $severity
 * @property ContentStatus $status
 * @property string $territory_type
 * @property string|null $territory_note
 * @property string|null $risk_category
 * @property string|null $source
 * @property array<int, string> $channels
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property Carbon|null $scheduled_at
 * @property Carbon|null $published_at
 * @property Carbon|null $expiry_notified_at
 * @property int|null $author_id
 * @property int|null $approver_id
 * @property-read User|null $author
 * @property-read User|null $approver
 */
class Alert extends Model implements HasMedia, Workflowable
{
    /** @use HasFactory<AlertFactory> */
    use HasFactory, HasTranslations, HasWorkflow, InteractsWithMedia, LogsActivity, SoftDeletes;

    /**
     * @var list<string>
     */
    public array $translatable = ['title', 'summary', 'body', 'instructions', 'contacts'];

    /**
     * Locales that count toward translation completeness.
     *
     * @var list<string>
     */
    public const LOCALES = ['tg', 'ru', 'en'];

    /**
     * Fields required for a locale to be 100% complete.
     *
     * @var list<string>
     */
    public const REQUIRED_CONTENT = ['title', 'summary', 'body', 'instructions'];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'internal_title',
        'slug',
        'title',
        'summary',
        'body',
        'instructions',
        'contacts',
        'hazard_type',
        'severity',
        'status',
        'territory_type',
        'territory_note',
        'risk_category',
        'source',
        'channels',
        'starts_at',
        'ends_at',
        'scheduled_at',
        'published_at',
        'expiry_notified_at',
        'author_id',
        'approver_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'hazard_type' => HazardType::class,
            'severity' => Severity::class,
            'status' => ContentStatus::class,
            'channels' => 'array',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'scheduled_at' => 'datetime',
            'published_at' => 'datetime',
            'expiry_notified_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['internal_title', 'severity', 'status', 'starts_at', 'ends_at', 'published_at'])
            ->logOnlyDirty()
            ->useLogName('alerts');
    }

    protected static function booted(): void
    {
        static::saving(function (Alert $alert): void {
            if (blank($alert->slug)) {
                $source = $alert->getTranslation('title', 'ru', false) ?: $alert->internal_title;
                $alert->slug = self::uniqueSlug($source, $alert->getKey());
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
            $base = 'alert-'.Str::lower(Str::random(6));
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
     * Publicly visible for a detail page: a public status (published/updated/
     * completed) — includes recently-ended alerts so links don't 404.
     *
     * @param  Builder<Alert>  $query
     */
    public function scopePublic(Builder $query): void
    {
        $public = array_map(
            fn (ContentStatus $s): string => $s->value,
            array_filter(ContentStatus::cases(), fn (ContentStatus $s): bool => $s->isPublic()),
        );

        $query->whereIn('status', $public);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('image')->singleFile();
        $this->addMediaCollection('documents');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    /**
     * @return BelongsToMany<Region, $this>
     */
    public function regions(): BelongsToMany
    {
        return $this->belongsToMany(Region::class);
    }

    /**
     * @return BelongsToMany<District, $this>
     */
    public function districts(): BelongsToMany
    {
        return $this->belongsToMany(District::class);
    }

    /**
     * @return BelongsToMany<Instruction, $this>
     */
    public function relatedInstructions(): BelongsToMany
    {
        return $this->belongsToMany(Instruction::class);
    }

    /**
     * Currently in effect on the public site.
     *
     * @param  Builder<Alert>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query
            ->whereIn('status', [ContentStatus::Published->value, ContentStatus::Updated->value])
            ->where(function (Builder $q): void {
                $q->whereNull('ends_at')->orWhere('ends_at', '>', now());
            });
    }

    public function isActive(): bool
    {
        return in_array($this->status, [ContentStatus::Published, ContentStatus::Updated], true)
            && ($this->ends_at === null || $this->ends_at->isFuture());
    }

    /**
     * Per-locale completeness percentage across the required content fields.
     *
     * @return array<string, int>
     */
    public function languageCompleteness(): array
    {
        $result = [];

        foreach (self::LOCALES as $locale) {
            $filled = 0;

            foreach (self::REQUIRED_CONTENT as $field) {
                if (trim((string) ($this->getTranslations($field)[$locale] ?? '')) !== '') {
                    $filled++;
                }
            }

            $result[$locale] = (int) round($filled / count(self::REQUIRED_CONTENT) * 100);
        }

        return $result;
    }
}
