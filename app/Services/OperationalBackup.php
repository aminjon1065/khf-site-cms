<?php

namespace App\Services;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\Process\Process;

final class OperationalBackup
{
    /**
     * @return array{directory: string, manifest: array<string, mixed>}
     */
    public function create(string $connectionName, string $destination): array
    {
        $connection = DB::connection($connectionName);
        $driver = $connection->getDriverName();
        $directory = rtrim($destination, DIRECTORY_SEPARATOR)
            .DIRECTORY_SEPARATOR.now()->format('Ymd-His').'-'.bin2hex(random_bytes(4));

        File::ensureDirectoryExists($directory, 0700, true);

        try {
            $databaseFile = $driver === 'sqlite'
                ? $this->copySqliteDatabase($connection, $directory)
                : $this->dumpMysqlDatabase($connection, $directory);
            $originals = $this->copyOriginals($directory);
            $manifest = [
                'schema' => 1,
                'created_at' => now()->toIso8601String(),
                'connection' => $connectionName,
                'driver' => $driver,
                'database_file' => basename($databaseFile),
                'database_sha256' => $this->checksum($databaseFile),
                'original_count' => count($originals),
                'originals' => $originals,
            ];

            File::put(
                $directory.DIRECTORY_SEPARATOR.'manifest.json',
                json_encode($manifest, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
                true,
            );

            return ['directory' => $directory, 'manifest' => $manifest];
        } catch (\Throwable $exception) {
            File::deleteDirectory($directory);

            throw $exception;
        }
    }

    /**
     * @return array{tables: int, originals: int}
     */
    public function drill(string $directory): array
    {
        $manifest = $this->manifest($directory);
        $databasePath = $directory.DIRECTORY_SEPARATOR.$manifest['database_file'];

        $this->verifyFile($databasePath, $manifest['database_sha256'], 'database');

        foreach ($manifest['originals'] as $original) {
            $this->verifyFile(
                $directory.DIRECTORY_SEPARATOR.$original['backup_path'],
                $original['sha256'],
                "media {$original['media_id']}",
            );
        }

        $tables = $manifest['driver'] === 'sqlite'
            ? $this->drillSqlite($databasePath)
            : $this->drillMysql($databasePath);

        return ['tables' => $tables, 'originals' => count($manifest['originals'])];
    }

    private function copySqliteDatabase(Connection $connection, string $directory): string
    {
        $source = (string) $connection->getConfig('database');

        if ($source === '' || $source === ':memory:' || ! is_file($source)) {
            throw new RuntimeException('SQLite backup requires a file-backed database.');
        }

        $target = $directory.DIRECTORY_SEPARATOR.'database.sqlite';

        if (! File::copy($source, $target)) {
            throw new RuntimeException('Unable to copy the SQLite database.');
        }

        return $target;
    }

    private function dumpMysqlDatabase(Connection $connection, string $directory): string
    {
        $config = $connection->getConfig();
        $target = $directory.DIRECTORY_SEPARATOR.'database.sql';
        $stream = fopen($target, 'wb');

        if ($stream === false) {
            throw new RuntimeException('Unable to create the database dump file.');
        }

        $process = new Process([
            (string) config('operations.backups.mysql_dump_binary'),
            '--single-transaction',
            '--skip-lock-tables',
            '--routines',
            '--events',
            '--host='.(string) ($config['host'] ?? '127.0.0.1'),
            '--port='.(string) ($config['port'] ?? 3306),
            '--user='.(string) ($config['username'] ?? ''),
            (string) ($config['database'] ?? ''),
        ], env: ['MYSQL_PWD' => (string) ($config['password'] ?? '')]);
        $process->setTimeout(3600);

        try {
            $process->run(function (string $type, string $buffer) use ($stream): void {
                if ($type === Process::OUT) {
                    fwrite($stream, $buffer);
                }
            });
        } finally {
            fclose($stream);
        }

        if (! $process->isSuccessful()) {
            throw new RuntimeException('mysqldump failed: '.trim($process->getErrorOutput()));
        }

        return $target;
    }

    /**
     * @return list<array{media_id: int, disk: string, source_path: string, backup_path: string, sha256: string, bytes: int}>
     */
    private function copyOriginals(string $directory): array
    {
        $originals = [];

        Media::query()->select(['id', 'uuid', 'disk', 'file_name'])->orderBy('id')
            ->chunkById(100, function ($items) use ($directory, &$originals): void {
                foreach ($items as $media) {
                    $sourcePath = $media->getPathRelativeToRoot();
                    $source = Storage::disk($media->disk)->readStream($sourcePath);

                    if (! is_resource($source)) {
                        throw new RuntimeException("Unable to read media original {$media->id}.");
                    }

                    $backupPath = 'originals/'.$media->uuid.'/'.$media->file_name;
                    $targetPath = $directory.DIRECTORY_SEPARATOR.$backupPath;
                    File::ensureDirectoryExists(dirname($targetPath), 0700, true);
                    $target = fopen($targetPath, 'wb');

                    if ($target === false) {
                        fclose($source);
                        throw new RuntimeException("Unable to create backup for media {$media->id}.");
                    }

                    stream_copy_to_stream($source, $target);
                    fclose($source);
                    fclose($target);

                    $originals[] = [
                        'media_id' => (int) $media->id,
                        'disk' => (string) $media->disk,
                        'source_path' => $sourcePath,
                        'backup_path' => $backupPath,
                        'sha256' => $this->checksum($targetPath),
                        'bytes' => File::size($targetPath),
                    ];
                }
            });

        return $originals;
    }

    /**
     * @return array<string, mixed>
     */
    private function manifest(string $directory): array
    {
        $path = rtrim($directory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'manifest.json';

        if (! is_file($path)) {
            throw new RuntimeException('Backup manifest is missing.');
        }

        $manifest = json_decode((string) File::get($path), true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($manifest)
            || ($manifest['schema'] ?? null) !== 1
            || ! is_string($manifest['database_file'] ?? null)
            || ! is_string($manifest['database_sha256'] ?? null)
            || ! is_array($manifest['originals'] ?? null)) {
            throw new RuntimeException('Backup manifest is invalid.');
        }

        return $manifest;
    }

    private function verifyFile(string $path, string $checksum, string $label): void
    {
        if (! is_file($path) || ! hash_equals($checksum, $this->checksum($path))) {
            throw new RuntimeException("Backup checksum mismatch for {$label}.");
        }
    }

    private function drillSqlite(string $databasePath): int
    {
        $restored = tempnam(sys_get_temp_dir(), 'khf-restore-');

        if ($restored === false || ! File::copy($databasePath, $restored)) {
            throw new RuntimeException('Unable to create an isolated SQLite restore target.');
        }

        try {
            $pdo = new \PDO('sqlite:'.$restored);
            $integrityStatement = $pdo->query('PRAGMA integrity_check');
            $tableStatement = $pdo->query(
                "SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'",
            );

            if ($integrityStatement === false || $tableStatement === false) {
                throw new RuntimeException('Unable to inspect the restored SQLite database.');
            }

            $integrity = $integrityStatement->fetchColumn();
            $tables = (int) $tableStatement->fetchColumn();

            if ($integrity !== 'ok' || $tables < 1) {
                throw new RuntimeException('SQLite restore integrity check failed.');
            }

            return $tables;
        } finally {
            File::delete($restored);
        }
    }

    private function drillMysql(string $databasePath): int
    {
        $connection = DB::connection();
        $config = $connection->getConfig();
        $database = 'khf_restore_drill_'.strtolower(bin2hex(random_bytes(6)));
        $base = [
            (string) config('operations.backups.mysql_binary'),
            '--host='.(string) ($config['host'] ?? '127.0.0.1'),
            '--port='.(string) ($config['port'] ?? 3306),
            '--user='.(string) ($config['username'] ?? ''),
        ];
        $environment = ['MYSQL_PWD' => (string) ($config['password'] ?? '')];

        $this->runMysql([...$base, '--execute=CREATE DATABASE `'.$database.'`'], $environment);

        try {
            $stream = fopen($databasePath, 'rb');

            if ($stream === false) {
                throw new RuntimeException('Unable to read the MySQL backup.');
            }

            try {
                $this->runMysql([...$base, $database], $environment, $stream);
            } finally {
                fclose($stream);
            }

            $process = $this->runMysql([
                ...$base,
                '--batch',
                '--skip-column-names',
                '--execute=SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()',
                $database,
            ], $environment);
            $tables = (int) trim($process->getOutput());

            if ($tables < 1) {
                throw new RuntimeException('MySQL restore contains no tables.');
            }

            return $tables;
        } finally {
            $this->runMysql([...$base, '--execute=DROP DATABASE IF EXISTS `'.$database.'`'], $environment);
        }
    }

    /**
     * @param  list<string>  $command
     * @param  array<string, string>  $environment
     * @param  resource|null  $input
     */
    private function runMysql(array $command, array $environment, mixed $input = null): Process
    {
        $process = new Process($command, env: $environment, input: $input);
        $process->setTimeout(3600);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException('mysql failed: '.trim($process->getErrorOutput()));
        }

        return $process;
    }

    private function checksum(string $path): string
    {
        $checksum = hash_file('sha256', $path);

        if ($checksum === false) {
            throw new RuntimeException("Unable to checksum {$path}.");
        }

        return $checksum;
    }
}
