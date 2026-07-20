<?php

namespace App\Models\Concerns;

use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Generates down-scaled JPEG variants (sm/md/lg) for uploaded images and builds
 * a `srcset` string from them, so the browser can pick the smallest sufficient
 * size — saving bandwidth. Variants are generated synchronously (`nonQueued`)
 * because this project runs no queue worker; the original is never up-scaled.
 */
trait HasResponsiveThumbnails
{
    /**
     * Variant name => target width (px). The original is intentionally left out
     * of the srcset so full-resolution files are never served to the browser.
     *
     * @var array<string, int>
     */
    protected array $thumbnailWidths = ['sm' => 480, 'md' => 960, 'lg' => 1600];

    public function registerMediaConversions(?Media $media = null): void
    {
        foreach ($this->thumbnailWidths as $name => $width) {
            $this->addMediaConversion($name)
                ->fit(Fit::Max, $width, $width * 4)
                ->format('jpg')
                ->nonQueued();
        }
    }

    /**
     * `srcset` for the first image of a collection: each generated variant with
     * its width descriptor. Null when the collection has no media.
     */
    public function thumbnailSrcset(string $collection): ?string
    {
        $media = $this->getFirstMedia($collection);
        if ($media === null) {
            return null;
        }

        return self::srcsetFromMedia($media, $this->thumbnailWidths);
    }

    /**
     * Build a `srcset` from a media item's generated variants.
     *
     * @param  array<string, int>  $widths
     */
    public static function srcsetFromMedia(Media $media, array $widths = ['sm' => 480, 'md' => 960, 'lg' => 1600]): ?string
    {
        $parts = [];
        foreach ($widths as $name => $width) {
            $url = $media->getUrl($name);
            if ($url !== '') {
                $parts[] = $url.' '.$width.'w';
            }
        }

        return $parts === [] ? null : implode(', ', $parts);
    }
}
