<?php

namespace App\Console\Commands;

use App\Models\MediaAsset;
use App\Services\MediaUsageService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Closes the media lifecycle at its last step. An asset moved to the trash
 * keeps its original and every derivative on disk indefinitely — that is what
 * makes restore possible, and also what makes deleted files immortal. After
 * the grace window the file is either gone for good or it was never really
 * deleted.
 *
 * Reporting is the default; deletion needs `--delete`, like the orphan
 * cleanup. Nothing is removed while any material still references it: the
 * usage check runs again at purge time, not only when the editor pressed
 * delete.
 */
#[Signature('media:purge-trashed {--delete : Permanently delete instead of reporting} {--grace-days= : Minimum days in the trash}')]
#[Description('Permanently remove library assets that have been in the trash beyond the grace window')]
class PurgeTrashedMedia extends Command
{
    public function handle(MediaUsageService $usages): int
    {
        $graceDays = max(
            0,
            (int) ($this->option('grace-days') ?? config('operations.media.trash_grace_days', 30)),
        );
        $cutoff = now()->subDays($graceDays);
        $delete = (bool) $this->option('delete');
        $purged = 0;
        $kept = 0;

        MediaAsset::onlyTrashed()
            ->where('deleted_at', '<=', $cutoff)
            ->orderBy('id')
            ->chunkById(50, function ($assets) use ($usages, $delete, &$purged, &$kept): void {
                foreach ($assets as $asset) {
                    $media = $asset->getFirstMedia('asset');
                    $inUse = $media !== null && $usages->usages($media) !== [];

                    if ($inUse) {
                        // Материал мог сослаться на файл уже после удаления —
                        // например, редактор восстановил старую ревизию.
                        $kept++;
                        $this->warn("Asset {$asset->getKey()}: остаётся, файл используется в материалах.");

                        continue;
                    }

                    $this->line(
                        "{$asset->getKey()}: удалён {$asset->deleted_at?->toDateString()}"
                        .($media !== null ? ", файл {$media->file_name}" : ', без файла'),
                    );

                    if ($delete) {
                        // forceDelete снимает и записи media, и файлы с диска:
                        // media-library удаляет их только при настоящем
                        // удалении модели, а не при мягком.
                        $asset->forceDelete();
                    }

                    $purged++;
                }
            }, 'id');

        $action = $delete ? 'deleted' : 'reported';
        $this->components->info(
            "Trashed media purge: {$purged} asset(s) {$action}, {$kept} kept because still referenced.",
        );

        return self::SUCCESS;
    }
}
