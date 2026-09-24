<?php

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;

beforeEach(function () {
    seed(RolePermissionSeeder::class);

    if (ini_parse_quantity((string) ini_get('post_max_size')) === 0) {
        test()->markTestSkipped('PHP has no post_max_size here: no save is too large.');
    }
});

/**
 * A save heavier than php.ini `post_max_size` (the body PHP threw away).
 *
 * @return array<string, string>
 */
function tooLargeSave(): array
{
    return ['CONTENT_LENGTH' => (string) (10 * 1024 * 1024 * 1024)];
}

function uploadEditor(): User
{
    $user = User::factory()->withTwoFactor()->create();
    $user->assignRole('editor');

    return $user;
}

it('keeps a too heavy save on the form with an error instead of an error page', function () {
    actingAs(uploadEditor())
        ->from('/news/create')
        ->call('POST', '/news', server: tooLargeSave())
        ->assertRedirect('/news/create')
        ->assertSessionHasErrors('upload');
});

it('answers a too heavy upload from the media picker with 413', function () {
    actingAs(uploadEditor())
        ->call('POST', '/media/library', server: [
            ...tooLargeSave(),
            'HTTP_ACCEPT' => 'application/json',
        ])
        ->assertStatus(413);
});
