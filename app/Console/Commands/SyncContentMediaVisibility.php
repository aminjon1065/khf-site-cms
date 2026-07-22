<?php

namespace App\Console\Commands;

use App\Models\Alert;
use App\Models\Document;
use App\Models\Instruction;
use App\Models\News;
use App\Models\Project;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;

class SyncContentMediaVisibility extends Command
{
    protected $signature = 'media:sync-visibility {--dry-run : Report mismatches without moving files}';

    protected $description = 'Move workflow media between private and public disks according to content status';

    /** @var list<class-string<Model>> */
    private const MODELS = [News::class, Instruction::class, Document::class, Project::class, Alert::class];

    public function handle(): int
    {
        $mismatches = 0;
        $dryRun = (bool) $this->option('dry-run');

        foreach (self::MODELS as $modelClass) {
            $modelClass::query()->with('media')->chunkById(100, function ($models) use (&$mismatches, $dryRun): void {
                foreach ($models as $model) {
                    $target = $model->contentMediaDisk();
                    $count = $model->media->filter(
                        fn ($media): bool => $media->disk !== $target || $media->conversions_disk !== $target,
                    )->count();
                    $mismatches += $count;

                    if (! $dryRun && $count > 0) {
                        $model->syncContentMediaVisibility();
                    }
                }
            });
        }

        $action = $dryRun ? 'found' : 'synchronized';
        $this->info("Media visibility {$action}: {$mismatches} file(s).");

        return self::SUCCESS;
    }
}
