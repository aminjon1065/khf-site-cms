<?php

use App\Models\MediaAsset;
use App\Services\OperationalBackup;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
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

it('keeps only the configured number of backups', function () {
    // Копия включает оригиналы медиа, то есть растёт вместе с библиотекой.
    // Без обрезки диск заполняется, и первым падает как раз следующий бэкап —
    // резервное копирование ломает само себя, причём молча.
    $root = sys_get_temp_dir().'/khf-backup-retention-'.bin2hex(random_bytes(6));
    $destination = $root.'/backups';
    File::ensureDirectoryExists($destination);
    Storage::fake('public');
    Queue::fake();

    File::put($root.'/source.sqlite', '');
    config()->set([
        'operations.backups.keep' => 2,
        'database.connections.retention_source' => [
            'driver' => 'sqlite',
            'database' => $root.'/source.sqlite',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ],
    ]);

    try {
        $created = [];

        foreach (range(1, 4) as $index) {
            $this->travelTo(now()->addMinutes($index));
            $created[] = app(OperationalBackup::class)
                ->create('retention_source', $destination)['directory'];
        }

        $this->travelBack();

        $remaining = collect(File::directories($destination))->sort()->values()->all();

        // Остаются две последние, и это именно последние — а не две любые.
        expect($remaining)->toHaveCount(2)
            ->and($remaining)->toBe([$created[2], $created[3]]);
    } finally {
        File::deleteDirectory($root);
    }
});

it('never prunes a good backup when the new one fails', function () {
    // Порядок важен: обрезка идёт только после успешно созданной копии. Иначе
    // упавший ночной бэкап уносил бы с собой вчерашний — и резервной копии не
    // осталось бы вовсе именно в тот день, когда что-то уже пошло не так.
    $root = sys_get_temp_dir().'/khf-backup-order-'.bin2hex(random_bytes(6));
    $destination = $root.'/backups';
    File::ensureDirectoryExists($destination);
    File::put($root.'/source.sqlite', '');
    Storage::fake('public');
    Queue::fake();

    config()->set([
        'operations.backups.keep' => 1,
        'database.connections.order_source' => [
            'driver' => 'sqlite',
            'database' => $root.'/source.sqlite',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ],
    ]);

    try {
        $good = app(OperationalBackup::class)->create('order_source', $destination)['directory'];
        File::put($destination.'/not-a-backup.txt', 'посторонний файл рядом с копиями');

        // Источник исчезает — следующая копия обязана упасть.
        File::delete($root.'/source.sqlite');

        expect(fn () => app(OperationalBackup::class)->create('order_source', $destination))
            ->toThrow(RuntimeException::class);

        expect(File::exists($good.'/manifest.json'))->toBeTrue()
            // Каталог без манифеста обрезка не трогает: это не её копия.
            ->and(File::exists($destination.'/not-a-backup.txt'))->toBeTrue()
            // И неудачная попытка после себя мусора не оставляет.
            ->and(File::directories($destination))->toBe([$good]);
    } finally {
        File::deleteDirectory($root);
    }
});

it('backs up and drills the MySQL path that production actually uses', function () {
    // Прогон против SQLite доказывает логику, но в production стоит MySQL, и
    // именно его ветка (`mysqldump` → проверка контрольной суммы → загрузка в
    // изолированную схему) до сих пор не выполнялась ни разу. Тест работает в
    // MySQL-ноге CI; на SQLite он пропускается, а не притворяется зелёным.
    $root = sys_get_temp_dir().'/khf-backup-mysql-'.bin2hex(random_bytes(6));
    $destination = $root.'/backups';
    File::ensureDirectoryExists($destination);
    Storage::fake('public');
    Queue::fake();

    $asset = MediaAsset::factory()->create();
    $media = $asset
        ->addMedia(UploadedFile::fake()->image('original.jpg', 32, 32))
        ->toMediaCollection('asset');
    $checksum = hash_file('sha256', $media->getPath());

    try {
        $result = app(OperationalBackup::class)->create(config('database.default'), $destination);

        expect($result['manifest']['driver'])->toBe('mysql')
            ->and($result['manifest']['original_count'])->toBe(1)
            ->and(hash_file(
                'sha256',
                $result['directory'].'/'.$result['manifest']['originals'][0]['backup_path'],
            ))->toBe($checksum);

        $drill = app(OperationalBackup::class)->drill($result['directory']);

        expect($drill['tables'])->toBeGreaterThan(0)
            ->and($drill['originals'])->toBe(1);
    } finally {
        File::deleteDirectory($root);
    }
})->skip(
    fn (): bool => DB::connection()->getDriverName() !== 'mysql',
    'Ветка MySQL проверяется в соответствующей ноге CI.',
);

it('drives the MySQL dump and restore commands correctly', function () {
    // Настоящую совместимость с mysqldump проверяет нога CI с MySQL 8. Здесь
    // проверяется то, что принадлежит нам: как команда вызывается, что дамп
    // попадает в файл потоком, что контрольная сумма считается по нему, что
    // проверка восстановления создаёт временную схему, грузит дамп и убирает
    // её за собой. Для этого клиенты подменены заглушками — иначе эта логика
    // не выполнялась бы нигде, кроме production.
    $root = sys_get_temp_dir().'/khf-backup-stub-'.bin2hex(random_bytes(6));
    $bin = $root.'/bin';
    $log = $root.'/mysql-calls.log';
    File::ensureDirectoryExists($bin);
    File::put($bin.'/mysqldump', "#!/bin/sh\necho \"-- dump of \$*\"\necho \"CREATE TABLE news (id INT);\"\n");
    File::put(
        $bin.'/mysql',
        "#!/bin/sh\necho \"\$*\" >> {$log}\ncase \"\$*\" in *information_schema*) echo 7 ;; esac\nexit 0\n",
    );
    chmod($bin.'/mysqldump', 0755);
    chmod($bin.'/mysql', 0755);

    Storage::fake('public');
    Queue::fake();
    config()->set([
        'operations.backups.mysql_dump_binary' => $bin.'/mysqldump',
        'operations.backups.mysql_binary' => $bin.'/mysql',
        'database.connections.stub_mysql' => [
            'driver' => 'mysql',
            'host' => 'db.internal',
            'port' => 3306,
            'database' => 'khf_site_cms',
            'username' => 'khf',
            'password' => 'secret',
            'prefix' => '',
        ],
    ]);

    try {
        $result = app(OperationalBackup::class)->create('stub_mysql', $root.'/backups');
        $dump = $result['directory'].'/'.$result['manifest']['database_file'];

        expect($result['manifest']['driver'])->toBe('mysql')
            ->and(File::get($dump))->toContain('--single-transaction')
            ->and(File::get($dump))->toContain('--host=db.internal')
            // Контрольная сумма манифеста обязана относиться к тому же файлу.
            ->and($result['manifest']['database_sha256'])->toBe(hash_file('sha256', $dump));

        $drill = app(OperationalBackup::class)->drill($result['directory']);

        expect($drill['tables'])->toBe(7);

        $calls = File::get($log);

        expect($calls)->toContain('CREATE DATABASE')
            ->toContain('information_schema')
            // Временная схема обязана быть удалена, иначе проверка
            // восстановления засоряет сервер по одной базе в неделю.
            ->toContain('DROP DATABASE IF EXISTS');
    } finally {
        File::deleteDirectory($root);
    }
});
