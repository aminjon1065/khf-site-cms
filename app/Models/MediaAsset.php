<?php

namespace App\Models;

use App\Models\Concerns\HasResponsiveThumbnails;
use Database\Factories\MediaAssetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * A reusable media-library asset: a single uploaded file plus optional
 * title / alt-text, owned by the person who uploaded it.
 *
 * @property int $id
 * @property string|null $title
 * @property string|null $alt
 * @property string|null $caption
 * @property int|null $uploaded_by
 */
class MediaAsset extends Model implements HasMedia
{
    /** @use HasFactory<MediaAssetFactory> */
    use HasFactory, HasResponsiveThumbnails, InteractsWithMedia, LogsActivity {
        HasResponsiveThumbnails::registerMediaConversions insteadof InteractsWithMedia;
    }

    /**
     * @var list<string>
     */
    protected $fillable = ['title', 'alt', 'caption', 'uploaded_by'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['title', 'alt', 'caption', 'uploaded_by'])
            ->logOnlyDirty()
            ->useLogName('media_assets');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('asset')->singleFile();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
