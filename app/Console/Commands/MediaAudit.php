<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\Conversions\FileManipulator;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

#[Signature('media:audit {--regenerate : Queue regeneration for images with missing or invalid files}')]
#[Description('Audit image originals and derivatives, optionally queueing safe regeneration')]
class MediaAudit extends Command
{
    public function handle(FileManipulator $fileManipulator): int
    {
        $checked = 0;
        $broken = 0;
        $queued = 0;

        Media::query()
            ->where('mime_type', 'like', 'image/%')
            ->orderBy('id')
            ->chunkById(100, function ($mediaItems) use (
                $fileManipulator,
                &$checked,
                &$broken,
                &$queued,
            ): void {
                foreach ($mediaItems as $media) {
                    $checked++;
                    $issues = $this->issues($media);

                    if ($issues === []) {
                        continue;
                    }

                    $broken++;
                    $this->warn("Media {$media->getKey()}: ".implode('; ', $issues));

                    if ((bool) $this->option('regenerate') && $this->originalExists($media)) {
                        $fileManipulator->createDerivedFiles(
                            media: $media,
                            queueAll: true,
                        );
                        $queued++;
                    }
                }
            });

        $this->components->info(
            "Media audit: {$checked} checked, {$broken} broken, {$queued} queued.",
        );

        return $broken === 0 || ((bool) $this->option('regenerate') && $queued === $broken)
            ? self::SUCCESS
            : self::FAILURE;
    }

    /**
     * @return list<string>
     */
    private function issues(Media $media): array
    {
        if (! $this->originalExists($media)) {
            return ['original is missing'];
        }

        $issues = [];
        $disk = Storage::disk($media->conversions_disk ?? $media->disk);

        foreach ($media->getMediaConversionNames() as $conversion) {
            $path = $media->getPathRelativeToRoot($conversion);

            if (! $media->hasGeneratedConversion($conversion) || ! $disk->exists($path)) {
                $issues[] = "missing derivative {$conversion}";
            }
        }

        return $issues;
    }

    private function originalExists(Media $media): bool
    {
        return Storage::disk($media->disk)->exists($media->getPathRelativeToRoot());
    }
}
