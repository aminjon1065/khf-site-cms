<?php

namespace App\Listeners;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Spatie\Image\Enums\ColorFormat;
use Spatie\Image\Image;
use Spatie\MediaLibrary\Conversions\Events\ConversionHasBeenCompletedEvent;
use Spatie\MediaLibrary\Support\TemporaryDirectory;
use Spatie\TemporaryDirectory\TemporaryDirectory as TemporaryDirectoryInstance;

class CaptureImageDerivativeMetadata
{
    public function handle(ConversionHasBeenCompletedEvent $event): void
    {
        $media = $event->media;
        $conversionName = $event->conversion->getName();
        $disk = Storage::disk($media->conversions_disk ?? $media->disk);
        $relativePath = $media->getPathRelativeToRoot($conversionName);
        $temporaryDirectory = TemporaryDirectory::create();
        $extension = $event->conversion->getResultExtension($media->extension);
        $temporaryPath = $temporaryDirectory->path(Str::random(32).'.'.$extension);

        try {
            $this->copyToLocalPath($disk, $relativePath, $temporaryPath);
            $image = Image::load($temporaryPath);
            $format = $this->sourceFormat($extension);

            $media->setCustomProperty("derivatives.{$format}.{$conversionName}", [
                'conversion' => $conversionName,
                'width' => $image->getWidth(),
                'height' => $image->getHeight(),
                'bytes' => $disk->size($relativePath),
            ]);

            if ($conversionName === 'sm') {
                $media->setCustomProperty(
                    'placeholder',
                    $this->placeholder($image, $temporaryDirectory),
                );
            }

            $media->saveQuietly();
        } finally {
            $temporaryDirectory->delete();
        }
    }

    private function copyToLocalPath(
        Filesystem $disk,
        string $relativePath,
        string $temporaryPath,
    ): void {
        $source = $disk->readStream($relativePath);

        if (! is_resource($source)) {
            throw new RuntimeException("Unable to read media derivative [{$relativePath}].");
        }

        $target = fopen($temporaryPath, 'wb');

        if ($target === false) {
            fclose($source);

            throw new RuntimeException("Unable to create a temporary copy for [{$relativePath}].");
        }

        try {
            stream_copy_to_stream($source, $target);
        } finally {
            fclose($source);
            fclose($target);
        }
    }

    private function sourceFormat(string $extension): string
    {
        return match (Str::lower($extension)) {
            'avif' => 'avif',
            'webp' => 'webp',
            default => 'fallback',
        };
    }

    /**
     * @return array{data_url: string, color: string}
     */
    private function placeholder(Image $image, TemporaryDirectoryInstance $temporaryDirectory): array
    {
        $image->width(24);
        $placeholderPath = $temporaryDirectory->path(Str::random(32).'.webp');
        $color = $this->averageColor($image);
        $image->format('webp')->quality(30)->save($placeholderPath);
        $contents = file_get_contents($placeholderPath);

        if ($contents === false) {
            throw new RuntimeException('Unable to read the generated image placeholder.');
        }

        return [
            'data_url' => 'data:image/webp;base64,'.base64_encode($contents),
            'color' => $color,
        ];
    }

    private function averageColor(Image $image): string
    {
        $width = $image->getWidth();
        $height = $image->getHeight();
        $xCoordinates = [0, intdiv($width - 1, 2), $width - 1];
        $yCoordinates = [0, intdiv($height - 1, 2), $height - 1];
        $red = 0;
        $green = 0;
        $blue = 0;
        $samples = 0;

        foreach ($xCoordinates as $x) {
            foreach ($yCoordinates as $y) {
                $color = $image->pickColor($x, $y, ColorFormat::Array);

                if (! is_array($color) || count($color) < 3) {
                    continue;
                }

                $red += (int) $color[0];
                $green += (int) $color[1];
                $blue += (int) $color[2];
                $samples++;
            }
        }

        if ($samples === 0) {
            return '#cccccc';
        }

        return sprintf(
            '#%02x%02x%02x',
            (int) round($red / $samples),
            (int) round($green / $samples),
            (int) round($blue / $samples),
        );
    }
}
