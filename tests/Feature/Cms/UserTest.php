<?php

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;

beforeEach(function () {
    seed(RolePermissionSeeder::class);
});

function asRole(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

it('lets an admin open the users list', function () {
    actingAs(asRole('admin'))->get('/users')->assertOk();
});

it('lets a chief editor view users (view-only grant)', function () {
    actingAs(asRole('chief_editor'))->get('/users')->assertOk();
});

it('forbids a role without users access', function () {
    actingAs(asRole('editor'))->get('/users')->assertForbidden();
});

it('creates a user with a role and a hashed password', function () {
    actingAs(asRole('admin'))->post('/users', [
        'name' => 'Далер Сатторов',
        'email' => 'd.sattorov@khf.tj',
        'password' => 'Secret12345',
        'password_confirmation' => 'Secret12345',
        'role' => 'editor',
        'is_active' => true,
    ])->assertRedirect('/users');

    $user = User::query()->where('email', 'd.sattorov@khf.tj')->first();

    expect($user)->not->toBeNull()
        ->and($user->hasRole('editor'))->toBeTrue()
        ->and(Hash::check('Secret12345', $user->password))->toBeTrue();
});

it('forbids a view-only role from creating a user', function () {
    actingAs(asRole('chief_editor'))->post('/users', [
        'name' => 'X', 'email' => 'x@khf.tj',
        'password' => 'Secret12345', 'password_confirmation' => 'Secret12345',
        'role' => 'editor',
    ])->assertForbidden();
});

it('validates a mismatched password confirmation', function () {
    actingAs(asRole('admin'))->post('/users', [
        'name' => 'X', 'email' => 'x@khf.tj',
        'password' => 'Secret12345', 'password_confirmation' => 'nope',
        'role' => 'editor',
    ])->assertSessionHasErrors('password');
});

it('updates a user without changing the password when left blank', function () {
    $user = asRole('editor');
    $original = $user->password;

    actingAs(asRole('admin'))->put("/users/{$user->id}", [
        'name' => 'Обновлённое имя',
        'email' => $user->email,
        'password' => '',
        'role' => 'translator',
    ])->assertRedirect('/users');

    $user->refresh();

    expect($user->name)->toBe('Обновлённое имя')
        ->and($user->hasRole('translator'))->toBeTrue()
        ->and($user->password)->toBe($original);
});

it('forbids an admin from deleting their own account', function () {
    $admin = asRole('admin');

    actingAs($admin)->delete("/users/{$admin->id}")->assertRedirect();

    expect(User::query()->find($admin->id))->not->toBeNull();
});

it('forbids an admin from assigning the superadmin role', function () {
    actingAs(asRole('admin'))->post('/users', [
        'name' => 'Escalation', 'email' => 'esc@khf.tj',
        'password' => 'Secret12345', 'password_confirmation' => 'Secret12345',
        'role' => 'superadmin',
    ])->assertForbidden();

    expect(User::query()->where('email', 'esc@khf.tj')->exists())->toBeFalse();
});

it('forbids an admin from editing or deleting a superadmin', function () {
    $super = asRole('superadmin');

    actingAs(asRole('admin'))->get("/users/{$super->id}/edit")->assertForbidden();
    actingAs(asRole('admin'))->delete("/users/{$super->id}")->assertForbidden();

    expect(User::query()->find($super->id))->not->toBeNull();
});

it('lets a superadmin create another superadmin', function () {
    actingAs(asRole('superadmin'))->post('/users', [
        'name' => 'Second Super', 'email' => 'super2@khf.tj',
        'password' => 'Secret12345', 'password_confirmation' => 'Secret12345',
        'role' => 'superadmin',
    ])->assertRedirect('/users');

    expect(User::query()->where('email', 'super2@khf.tj')->first()?->hasRole('superadmin'))->toBeTrue();
});

it('clamps an excessive per_page on the users list', function () {
    actingAs(asRole('admin'))->get('/users?per_page=100000')
        ->assertInertia(fn ($page) => $page
            ->component('users/index')
            ->where('meta.per_page', 100),
        );
});

it('blocks an inactive user from logging in', function () {
    $user = User::factory()->inactive()->create();

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect();

    $this->assertGuest();
});

it('allows an active user to log in', function () {
    $user = User::factory()->create();

    $this->post('/login', ['email' => $user->email, 'password' => 'password']);

    $this->assertAuthenticated();
});
