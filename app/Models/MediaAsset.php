<?php

namespace App\Models;

use Database\Factories\MediaAssetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * A reusable media-library asset: a single uploaded file plus optional
 * title / alt-text, owned by the person who uploaded it.
 *
 * @property int $id
 * @property string|null $title
 * @property string|null $alt
 * @property int|null $uploaded_by
 */
class MediaAsset extends Model implements HasMedia
{
    /** @use HasFactory<MediaAssetFactory> */
    use HasFactory, InteractsWithMedia;

    /**
     * @var list<string>
     */
    protected $fillable = ['title', 'alt', 'uploaded_by'];

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
