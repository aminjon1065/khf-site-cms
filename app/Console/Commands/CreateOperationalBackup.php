<?php

namespace App\Console\Commands;

use App\Services\OperationalBackup;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('ops:backup {--connection= : Database connection} {--destination= : Backup root directory}')]
#[Description('Create a checksummed database and immutable-media-original backup')]
class CreateOperationalBackup extends Command
{
    public function handle(OperationalBackup $backup): int
    {
        $connection = (string) ($this->option('connection') ?: config('database.default'));
        $destination = (string) ($this->option('destination') ?: config('operations.backups.path'));
        $result = $backup->create($connection, $destination);

        $this->components->info(
            "Backup created: {$result['directory']} ({$result['manifest']['original_count']} originals).",
        );

        return self::SUCCESS;
    }
}
