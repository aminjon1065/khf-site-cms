<?php

namespace App\Services;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Spatie\MediaLibrary\Conversions\Conversion;
use Spatie\MediaLibrary\Conversions\ConversionCollection;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\TemporaryDirectory;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class AvifDerivativeGenerator
{
    /**
     * @param  ConversionCollection<array-key, Conversion>  $conversions
     */
    public function generate(
        Media $media,
        ConversionCollection $conversions,
        bool $onlyMissing = false,
    ): void {
        $disk = Storage::disk($media->conversions_disk ?? $media->disk);

        foreach ($conversions as $conversion) {
            $this->generateConversion($media, $conversion, $disk, $onlyMissing);
        }
    }

    public function handles(Conversion $conversion): bool
    {
        return Str::endsWith($conversion->getName(), '-avif');
    }

    private function generateConversion(
        Media $media,
        Conversion $conversion,
        Filesystem $disk,
        bool $onlyMissing,
    ): void {
        $conversionName = $conversion->getName();
        $sourceConversion = Str::beforeLast($conversionName, '-avif');
        $sourcePath = $media->getPathRelativeToRoot($sourceConversion);
        $targetPath = $media->getPathRelativeToRoot($conversionName);

        if ($onlyMissing && $media->hasGeneratedConversion($conversionName) && $disk->exists($targetPath)) {
            return;
        }

        if (! $disk->exists($sourcePath)) {
            throw new RuntimeException("Missing fallback derivative [{$sourcePath}] for AVIF generation.");
        }

        $temporaryDirectory = TemporaryDirectory::create();
        $inputPath = $temporaryDirectory->path(
            'input.'.pathinfo($sourcePath, PATHINFO_EXTENSION),
        );
        $outputPath = $temporaryDirectory->path('output.avif');

        try {
            $this->copyToLocalPath($disk, $sourcePath, $inputPath);
            $process = new Process([
                $this->binary(),
                '--qcolor',
                (string) config('media-pipeline.avif_quality', 50),
                '--qalpha',
                (string) config('media-pipeline.avif_alpha_quality', 80),
                '--speed',
                (string) config('media-pipeline.avif_speed', 6),
                '--jobs',
                '1',
                $inputPath,
                $outputPath,
            ]);
            $process->setTimeout(90);
            $process->mustRun();

            $output = fopen($outputPath, 'rb');
            if ($output === false) {
                throw new RuntimeException("Unable to read AVIF derivative [{$conversionName}].");
            }

            try {
                if (! $disk->writeStream($targetPath, $output)) {
                    throw new RuntimeException("Unable to store AVIF derivative [{$targetPath}].");
                }
            } finally {
                fclose($output);
            }

            $dimensions = getimagesize($inputPath);
            $media->setCustomProperty("derivatives.avif.{$conversionName}", [
                'conversion' => $conversionName,
                'width' => is_array($dimensions) ? $dimensions[0] : null,
                'height' => is_array($dimensions) ? $dimensions[1] : null,
                'bytes' => $disk->size($targetPath),
            ]);
            $media->markAsConversionGenerated($conversionName);
        } finally {
            $temporaryDirectory->delete();
        }
    }

    private function binary(): string
    {
        $configured = (string) config('media-pipeline.avifenc_binary', 'avifenc');
        $binary = (new ExecutableFinder)->find(
            $configured,
            null,
            ['/opt/homebrew/bin', '/usr/local/bin', '/usr/bin'],
        );

        if ($binary === null) {
            throw new RuntimeException(
                "AVIF encoder [{$configured}] was not found. Configure AVIFENC_BINARY for the media worker.",
            );
        }

        return $binary;
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

            throw new RuntimeException("Unable to create temporary derivative [{$temporaryPath}].");
        }

        try {
            stream_copy_to_stream($source, $target);
        } finally {
            fclose($source);
            fclose($target);
        }
    }
}
