<?php

use App\Support\PendingMigrations;

test('reports no pending migrations on a fully migrated database', function () {
    expect(app(PendingMigrations::class)->names())->toBe([])
        ->and(app(PendingMigrations::class)->count())->toBe(0);
});

test('detects a migration file that has not been run', function () {
    $file = database_path('migrations/'.now()->format('Y_m_d_His').'_unit_test_fake_migration.php');
    file_put_contents($file, '<?php return new class { public function up(): void {} };');

    try {
        expect(app(PendingMigrations::class)->names())->toContain(basename($file, '.php'));
    } finally {
        @unlink($file);
    }
});
