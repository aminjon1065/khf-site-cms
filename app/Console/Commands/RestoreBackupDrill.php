<?php

namespace App\Console\Commands;

use App\Services\OperationalBackup;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use RuntimeException;

#[Signature('ops:restore-drill {backup? : Backup directory containing manifest.json}')]
#[Description('Restore a backup into an isolated database and verify every checksum')]
class RestoreBackupDrill extends Command
{
    public function handle(OperationalBackup $backup): int
    {
        $directory = (string) ($this->argument('backup') ?: $this->latestBackup());
        $result = $backup->drill($directory);

        $this->components->info(
            "Restore drill passed: {$result['tables']} tables, {$result['originals']} originals.",
        );

        return self::SUCCESS;
    }

    private function latestBackup(): string
    {
        $root = (string) config('operations.backups.path');
        $directories = array_values(array_filter(
            File::directories($root),
            fn (string $directory): bool => File::exists($directory.'/manifest.json'),
        ));
        rsort($directories);

        return $directories[0] ?? throw new RuntimeException('No backup is available for a restore drill.');
    }
}
