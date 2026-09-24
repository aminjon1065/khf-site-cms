<?php

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;

use function Pest\Laravel\seed;

it('creates the first administrator with a password only the operator knows', function () {
    seed(RolePermissionSeeder::class);

    $this->artisan('cms:create-admin', ['--email' => 'admin@khf.tj', '--name' => 'Администратор системы'])
        ->expectsQuestion('Пароль', 'Надёжный-пароль-2026')
        ->expectsQuestion('Повторите пароль', 'Надёжный-пароль-2026')
        ->assertSuccessful();

    $admin = User::query()->where('email', 'admin@khf.tj')->sole();

    expect($admin->hasRole('admin'))->toBeTrue()
        ->and($admin->is_active)->toBeTrue()
        ->and($admin->email_verified_at)->not->toBeNull()
        ->and(Hash::check('Надёжный-пароль-2026', $admin->password))->toBeTrue();
});

it('refuses to create an administrator before the roles exist', function () {
    $this->artisan('cms:create-admin', ['--email' => 'admin@khf.tj', '--name' => 'Администратор'])
        ->assertFailed();

    expect(User::query()->count())->toBe(0);
});

it('refuses an address that is already taken', function () {
    seed(RolePermissionSeeder::class);
    User::factory()->create(['email' => 'admin@khf.tj']);

    $this->artisan('cms:create-admin', ['--email' => 'admin@khf.tj', '--name' => 'Администратор'])
        ->assertFailed();

    expect(User::query()->count())->toBe(1);
});

it('does not create the account when the passwords differ', function () {
    seed(RolePermissionSeeder::class);

    $this->artisan('cms:create-admin', ['--email' => 'admin@khf.tj', '--name' => 'Администратор'])
        ->expectsQuestion('Пароль', 'Надёжный-пароль-2026')
        ->expectsQuestion('Повторите пароль', 'Другой-пароль-2026')
        ->assertFailed();

    expect(User::query()->count())->toBe(0);
});
