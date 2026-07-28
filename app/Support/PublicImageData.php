<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * @phpstan-type ImageSource array{url: string, width: int, height: int|null, bytes: int}
 * @phpstan-type ImageData array{
 *     version: 2,
 *     id: int,
 *     uuid: string,
 *     alt: string,
 *     caption: string|null,
 *     width: int|null,
 *     height: int|null,
 *     aspect_ratio: float|null,
 *     bytes: int,
 *     mime_type: string,
 *     checksum: string|null,
 *     focal_point: array{x: float, y: float},
 *     status: string,
 *     placeholder: array{data_url: string, color: string}|null,
 *     sources: array{avif: list<ImageSource>, webp: list<ImageSource>, fallback: list<ImageSource>}
 * }
 */
class PublicImageData
{
    /**
     * @var array<string, int>
     */
    private const LEGACY_FALLBACK_WIDTHS = [
        'sm' => 480,
        'md' => 960,
        'lg' => 1600,
    ];

    /**
     * @return ImageData
     */
    public static function fromMedia(
        Media $media,
        string $alt,
        ?string $caption = null,
    ): array {
        $original = self::propertyArray($media, 'original');
        $width = self::positiveInt($original['width'] ?? null);
        $height = self::positiveInt($original['height'] ?? null);
        $sources = [
            'avif' => self::sources($media, 'avif'),
            'webp' => self::sources($media, 'webp'),
            'fallback' => self::sources($media, 'fallback'),
        ];

        if ($sources['fallback'] === []) {
            $sources['fallback'] = self::legacyFallbackSources($media, $width, $height);
        }

        $status = $media->getCustomProperty('conversion_status');
        if (! is_string($status)) {
            $hasSources = $sources['avif'] !== []
                || $sources['webp'] !== []
                || $sources['fallback'] !== [];
            $status = $hasSources ? 'ready' : 'pending';
        }

        return [
            'version' => 2,
            'id' => (int) $media->getKey(),
            'uuid' => (string) $media->uuid,
            'alt' => $alt,
            'caption' => $caption,
            'width' => $width,
            'height' => $height,
            'aspect_ratio' => $width !== null && $height !== null
                ? round($width / $height, 4)
                : null,
            'bytes' => (int) ($original['bytes'] ?? $media->size),
            'mime_type' => (string) ($original['mime_type'] ?? $media->mime_type),
            'checksum' => is_string($original['checksum'] ?? null)
                ? $original['checksum']
                : null,
            'focal_point' => self::focalPoint($media),
            'status' => $status,
            'placeholder' => self::placeholder($media),
            'sources' => $sources,
        ];
    }

    /**
     * @return list<ImageSource>
     */
    private static function sources(Media $media, string $format): array
    {
        $metadata = self::propertyArray($media, "derivatives.{$format}");
        $sources = [];

        foreach ($metadata as $item) {
            if (! is_array($item)) {
                continue;
            }

            $conversion = $item['conversion'] ?? null;
            $width = self::positiveInt($item['width'] ?? null);
            $bytes = self::positiveInt($item['bytes'] ?? null);

            if (! is_string($conversion) || $width === null || $bytes === null) {
                continue;
            }

            if (! $media->hasGeneratedConversion($conversion)) {
                continue;
            }

            $sources[] = [
                'url' => $media->getUrl($conversion),
                'width' => $width,
                'height' => self::positiveInt($item['height'] ?? null),
                'bytes' => $bytes,
            ];
        }

        usort($sources, fn (array $left, array $right): int => $left['width'] <=> $right['width']);

        return $sources;
    }

    /**
     * @return list<ImageSource>
     */
    private static function legacyFallbackSources(
        Media $media,
        ?int $originalWidth,
        ?int $originalHeight,
    ): array {
        $disk = Storage::disk($media->conversions_disk ?? $media->disk);
        $aspectRatio = $originalWidth !== null && $originalHeight !== null
            ? $originalWidth / $originalHeight
            : null;
        $sources = [];

        foreach (self::LEGACY_FALLBACK_WIDTHS as $conversion => $targetWidth) {
            if (! $media->hasGeneratedConversion($conversion)) {
                continue;
            }

            $relativePath = $media->getPathRelativeToRoot($conversion);
            if (! $disk->exists($relativePath)) {
                continue;
            }

            $width = $originalWidth !== null ? min($targetWidth, $originalWidth) : $targetWidth;
            $sources[] = [
                'url' => $media->getUrl($conversion),
                'width' => $width,
                'height' => $aspectRatio !== null ? (int) round($width / $aspectRatio) : null,
                'bytes' => $disk->size($relativePath),
            ];
        }

        return $sources;
    }

    /**
     * @return array{x: float, y: float}
     */
    private static function focalPoint(Media $media): array
    {
        $focalPoint = self::propertyArray($media, 'focal_point');

        return [
            'x' => self::unitFloat($focalPoint['x'] ?? 0.5),
            'y' => self::unitFloat($focalPoint['y'] ?? 0.5),
        ];
    }

    /**
     * @return array{data_url: string, color: string}|null
     */
    private static function placeholder(Media $media): ?array
    {
        $placeholder = self::propertyArray($media, 'placeholder');
        $dataUrl = $placeholder['data_url'] ?? null;
        $color = $placeholder['color'] ?? null;

        if (! is_string($dataUrl) || ! str_starts_with($dataUrl, 'data:image/')) {
            return null;
        }

        if (! is_string($color) || preg_match('/^#[0-9a-f]{6}$/i', $color) !== 1) {
            return null;
        }

        return ['data_url' => $dataUrl, 'color' => $color];
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function propertyArray(Media $media, string $property): array
    {
        $value = $media->getCustomProperty($property);

        return is_array($value) ? $value : [];
    }

    private static function positiveInt(mixed $value): ?int
    {
        $integer = filter_var($value, FILTER_VALIDATE_INT);

        return is_int($integer) && $integer > 0 ? $integer : null;
    }

    private static function unitFloat(mixed $value): float
    {
        return min(1.0, max(0.0, is_numeric($value) ? (float) $value : 0.5));
    }
}
