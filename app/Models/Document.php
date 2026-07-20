<?php

namespace App\Models;

use App\Concerns\HasWorkflow;
use App\Contracts\Workflowable;
use App\Enums\ContentStatus;
use App\Enums\DocType;
use Database\Factories\DocumentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
 * @property DocType $doc_type
 * @property string|null $number
 * @property Carbon|null $doc_date
 * @property string|null $section
 * @property ContentStatus $status
 * @property Carbon|null $published_at
 * @property int|null $author_id
 */
class Document extends Model implements HasMedia, Workflowable
{
    /** @use HasFactory<DocumentFactory> */
    use HasFactory, HasWorkflow, InteractsWithMedia, LogsActivity, SoftDeletes;

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
        'doc_type',
        'number',
        'doc_date',
        'section',
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
            'doc_type' => DocType::class,
            'status' => ContentStatus::class,
            'doc_date' => 'date',
            'published_at' => 'datetime',
        ];
    }

    /**
     * Publicly visible documents (a public status), newest first.
     *
     * @param  Builder<Document>  $query
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
     * @param  Builder<Document>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderByDesc('doc_date')->orderByDesc('id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'doc_type', 'number', 'status'])
            ->logOnlyDirty()
            ->useLogName('documents');
    }

    /**
     * One file collection per language version.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('file_tg')->singleFile();
        $this->addMediaCollection('file_ru')->singleFile();
        $this->addMediaCollection('file_en')->singleFile();
    }

    /**
     * @return array<string, bool>
     */
    public function fileLanguages(): array
    {
        return [
            'tg' => $this->hasMedia('file_tg'),
            'ru' => $this->hasMedia('file_ru'),
            'en' => $this->hasMedia('file_en'),
        ];
    }

    public function hasAnyFile(): bool
    {
        return $this->hasMedia('file_tg') || $this->hasMedia('file_ru') || $this->hasMedia('file_en');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
