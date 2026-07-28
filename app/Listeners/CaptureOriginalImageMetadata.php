<?php

namespace App\Listeners;

use Illuminate\Support\Str;
use RuntimeException;
use Spatie\Image\Image;
use Spatie\MediaLibrary\MediaCollections\Events\MediaHasBeenAddedEvent;
use Spatie\MediaLibrary\MediaCollections\Filesystem;
use Spatie\MediaLibrary\Support\TemporaryDirectory;

class CaptureOriginalImageMetadata
{
    public function __construct(private Filesystem $filesystem) {}

    public function handle(MediaHasBeenAddedEvent $event): void
    {
        $media = $event->media;

        if (! Str::startsWith((string) $media->mime_type, 'image/')) {
            return;
        }

        $temporaryDirectory = TemporaryDirectory::create();
        $temporaryPath = $temporaryDirectory->path(Str::random(32).'.'.$media->extension);

        try {
            $this->filesystem->copyFromMediaLibrary($media, $temporaryPath);
            $image = Image::load($temporaryPath);
            $checksum = hash_file('sha256', $temporaryPath);

            if ($checksum === false) {
                throw new RuntimeException('Unable to checksum the original media file.');
            }

            $media
                ->setCustomProperty('original', [
                    'width' => $image->getWidth(),
                    'height' => $image->getHeight(),
                    'bytes' => (int) $media->size,
                    'mime_type' => (string) $media->mime_type,
                    'checksum' => $checksum,
                ])
                ->setCustomProperty('focal_point', ['x' => 0.5, 'y' => 0.5])
                ->setCustomProperty('conversion_status', 'pending')
                ->saveQuietly();
        } finally {
            $temporaryDirectory->delete();
        }
    }
}
