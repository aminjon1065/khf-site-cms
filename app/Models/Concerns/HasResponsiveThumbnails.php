<?php

namespace App\Models\Concerns;

use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Generates the public responsive matrix and the smaller CMS-only thumbnails.
 * All variants are generated on the dedicated media queue and never upscale the
 * original.
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
            $fallback = $this->addMediaConversion($name);
            $fallback->fit(Fit::Max, $width, $width * 4);
            $fallback->keepOriginalImageFormat();
            $fallback->quality(82);

            $this->addMediaConversion("{$name}-webp")
                ->fit(Fit::Max, $width, $width * 4)
                ->format('webp')
                ->quality(72);

            // The media job delegates these conversions to avifenc because GD
            // does not provide AVIF support on every deployment target.
            $this->addMediaConversion("{$name}-avif")
                ->fit(Fit::Max, $width, $width * 4)
                ->format('avif')
                ->quality(50);
        }

        foreach (['cms-192' => 192, 'cms-320' => 320] as $name => $width) {
            $this->addMediaConversion($name)
                ->fit(Fit::Max, $width, $width * 4)
                ->format('webp')
                ->quality(65);
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

    public static function cmsThumbnailSrcset(Media $media): ?string
    {
        return self::srcsetFromMedia($media, [
            'cms-192' => 192,
            'cms-320' => 320,
        ]);
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
            if (! $media->hasGeneratedConversion($name)) {
                continue;
            }

            $url = $media->getUrl($name);
            if ($url !== '') {
                $parts[] = $url.' '.$width.'w';
            }
        }

        return $parts === [] ? null : implode(', ', $parts);
    }
}
