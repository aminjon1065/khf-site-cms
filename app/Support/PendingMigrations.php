<?php

namespace App\Support;

use Illuminate\Database\Migrations\Migrator;

/**
 * Detects drift between the migration files in the codebase and the
 * migrations recorded in the database. Deployed code with unapplied
 * migrations has caused silent runtime 500s (missing columns), so
 * this powers both the ops readiness check and a Control Center
 * warning banner.
 */
final class PendingMigrations
{
    /**
     * @return list<string> migration names present on disk but not in the repository table
     */
    public function names(): array
    {
        $migrator = app(Migrator::class);

        if (! $migrator->repositoryExists()) {
            return [];
        }

        $onDisk = array_keys($migrator->getMigrationFiles(database_path('migrations')));
        $ran = $migrator->getRepository()->getRan();

        return array_values(array_diff($onDisk, $ran));
    }

    public function count(): int
    {
        return count($this->names());
    }
}
