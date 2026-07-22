<?php

namespace App\Concerns;

use App\Enums\ContentStatus;

/**
 * Keeps workflow content media off the public disk until the material becomes
 * publicly visible. Models using this concern must expose the Media Library
 * `media()` relationship and cast `status` to ContentStatus.
 */
trait ProtectsUnpublishedMedia
{
    public function contentMediaDisk(): string
    {
        $status = $this->getAttribute('status');

        return $status instanceof ContentStatus && $status->isPublic()
            ? (string) config('media-library.disk_name', 'public')
            : (string) config('media-library.private_disk_name', 'content_private');
    }

    /**
     * Move every attached original and its conversions to the disk appropriate
     * for the current workflow status.
     */
    public function syncContentMediaVisibility(): int
    {
        $targetDisk = $this->contentMediaDisk();
        $moved = 0;

        foreach ($this->media()->get() as $media) {
            if ($media->disk === $targetDisk && $media->conversions_disk === $targetDisk) {
                continue;
            }

            $media->move($this, $media->collection_name, $targetDisk, $media->file_name);
            $moved++;
        }

        $this->unsetRelation('media');

        return $moved;
    }
}
