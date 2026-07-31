<?php

use App\Models\MediaAsset;
use App\Services\OperationalBackup;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

it('backs up a file database and original media then restores them in isolation', function () {
    $root = sys_get_temp_dir().'/khf-backup-test-'.bin2hex(random_bytes(6));
    $database = $root.'/source.sqlite';
    $destination = $root.'/backups';
    File::ensureDirectoryExists($root);
    File::put($database, '');

    config()->set('database.connections.backup_source', [
        'driver' => 'sqlite',
        'database' => $database,
        'prefix' => '',
        'foreign_key_constraints' => true,
    ]);
    Artisan::call('migrate', [
        '--database' => 'backup_source',
        '--force' => true,
        '--no-interaction' => true,
    ]);

    Storage::fake('public');
    Queue::fake();
    $asset = MediaAsset::factory()->create();
    $media = $asset
        ->addMedia(UploadedFile::fake()->image('original.jpg', 64, 64))
        ->toMediaCollection('asset');
    $originalChecksum = hash_file('sha256', $media->getPath());

    try {
        $this->artisan('ops:backup', [
            '--connection' => 'backup_source',
            '--destination' => $destination,
        ])->assertSuccessful();

        $directories = File::directories($destination);
        expect($directories)->toHaveCount(1);

        $backup = $directories[0];
        $manifest = json_decode(File::get($backup.'/manifest.json'), true, flags: JSON_THROW_ON_ERROR);
        $original = $backup.'/'.$manifest['originals'][0]['backup_path'];

        expect($manifest['driver'])->toBe('sqlite')
            ->and($manifest['original_count'])->toBe(1)
            ->and(hash_file('sha256', $original))->toBe($originalChecksum);

        $this->artisan('ops:restore-drill', ['backup' => $backup])
            ->expectsOutputToContain('Restore drill passed:')
            ->assertSuccessful();

        File::append($original, 'corruption');

        expect(fn () => app(OperationalBackup::class)->drill($backup))
            ->toThrow(RuntimeException::class, "Backup checksum mismatch for media {$media->id}.");
    } finally {
        File::deleteDirectory($root);
    }
});
