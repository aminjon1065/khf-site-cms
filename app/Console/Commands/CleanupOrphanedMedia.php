<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

#[Signature('media:cleanup-orphans {--delete : Delete confirmed orphan derivatives} {--grace-hours= : Minimum file age}')]
#[Description('Report or delete derivative files that belong to no media record')]
class CleanupOrphanedMedia extends Command
{
    public function handle(): int
    {
        $expected = [];
        $disks = array_filter([
            (string) config('media-library.disk_name'),
            (string) config('media-pipeline.public_disk'),
            (string) config('media-pipeline.private_disk'),
        ]);

        Media::query()->orderBy('id')->chunkById(100, function ($items) use (&$expected, &$disks): void {
            foreach ($items as $media) {
                $disk = (string) ($media->conversions_disk ?: $media->disk);
                $disks[] = $disk;

                foreach ($media->getMediaConversionNames() as $conversion) {
                    $expected[$disk][$media->getPathRelativeToRoot($conversion)] = true;
                }
            }
        });

        $graceHours = max(
            0,
            (int) ($this->option('grace-hours') ?? config('operations.media.orphan_grace_hours', 24)),
        );
        $cutoff = now()->subHours($graceHours)->getTimestamp();
        $orphans = [];

        foreach (array_unique($disks) as $disk) {
            foreach (Storage::disk($disk)->allFiles() as $path) {
                if (! str_contains('/'.$path, '/conversions/')
                    || isset($expected[$disk][$path])
                    || Storage::disk($disk)->lastModified($path) > $cutoff) {
                    continue;
                }

                $orphans[] = ['disk' => $disk, 'path' => $path];
                $this->line("{$disk}:{$path}");
            }
        }

        if ((bool) $this->option('delete')) {
            foreach ($orphans as $orphan) {
                Storage::disk($orphan['disk'])->delete($orphan['path']);
            }
        }

        $action = (bool) $this->option('delete') ? 'deleted' : 'reported';
        $this->components->info(
            'Media orphan cleanup: '.count($orphans)." derivative(s) {$action}; originals untouched.",
        );

        return self::SUCCESS;
    }
}
