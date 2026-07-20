<?php

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;

beforeEach(function () {
    seed(RolePermissionSeeder::class);
});

function roleUser(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

it('shows the role matrix to an authorized user', function () {
    actingAs(roleUser('admin'))->get('/roles')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('roles/index')
            ->has('roles', 9)
            ->has('modules')
            ->has('actions', 6),
        );
});

it('forbids a role without users.view from the matrix', function () {
    actingAs(roleUser('editor'))->get('/roles')->assertForbidden();
});

it('reports the admin role as having a granted permission', function () {
    actingAs(roleUser('admin'))->get('/roles')
        ->assertInertia(fn ($page) => $page
            ->where('roles.1.value', 'admin')
            ->where('roles.1.matrix.news.view', true),
        );
});

it('counts users assigned to each role', function () {
    roleUser('viewer');
    roleUser('viewer');

    actingAs(roleUser('admin'))->get('/roles')
        ->assertInertia(fn ($page) => $page
            ->where('roles.8.value', 'viewer')
            ->where('roles.8.user_count', 2),
        );
});
