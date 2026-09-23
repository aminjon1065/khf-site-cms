<?php

use App\Models\Alert;
use App\Models\News;
use App\Models\Page;
use App\Models\Submission;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\UserSeeder;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    config()->set('seeding.source.path', base_path('tests/Fixtures/source'));
    config()->set('seeding.source.media', false);
    app()->detectEnvironment(fn (): string => 'production');
});

it('seeds only reference data in production — no demo accounts, no sample content', function (): void {
    // In production db:seed asks for confirmation; --force is what an
    // operator runs.
    $this->artisan('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true])->assertSuccessful();

    expect(Role::query()->where('name', 'superadmin')->exists())->toBeTrue()
        ->and(Page::query()->where('slug', 'about')->exists())->toBeTrue()
        ->and(User::query()->count())->toBe(0)
        ->and(Alert::query()->count())->toBe(0)
        ->and(News::query()->count())->toBe(0)
        ->and(Submission::query()->count())->toBe(0);
});

it('refuses to create the demo accounts in production', function (): void {
    expect(fn () => app(UserSeeder::class)->run())->toThrow(RuntimeException::class);

    expect(User::query()->count())->toBe(0);
});
