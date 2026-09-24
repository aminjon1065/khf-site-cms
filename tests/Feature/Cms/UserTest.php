<?php

use App\Enums\RegionType;
use App\Models\Activity;
use App\Models\Region;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;

beforeEach(function () {
    seed(RolePermissionSeeder::class);
});

function asRole(string $role): User
{
    $user = User::factory()->create();
    giveRole($user, $role);

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

it('rejects an interface locale that has no CMS translations', function () {
    actingAs(asRole('admin'))->post('/users', [
        'name' => 'English User',
        'email' => 'english@khf.tj',
        'password' => 'Secret12345',
        'password_confirmation' => 'Secret12345',
        'role' => 'editor',
        'interface_locale' => 'en',
        'is_active' => true,
    ])->assertSessionHasErrors('interface_locale');

    expect(User::query()->where('email', 'english@khf.tj')->exists())->toBeFalse();
});

it('updates a user without changing the password when left blank', function () {
    $user = asRole('editor');
    $original = $user->password;

    actingAs(asRole('admin'))->put("/users/{$user->id}", [
        'name' => 'Обновлённое имя',
        'email' => $user->email,
        'password' => '',
        'role' => 'chief_editor',
    ])->assertRedirect('/users');

    $user->refresh();

    expect($user->name)->toBe('Обновлённое имя')
        ->and($user->hasRole('chief_editor'))->toBeTrue()
        ->and($user->password)->toBe($original);
});

it('forbids an admin from deleting their own account', function () {
    $admin = asRole('admin');

    actingAs($admin)->delete("/users/{$admin->id}")->assertRedirect();

    expect(User::query()->find($admin->id))->not->toBeNull();
});

/**
 * Someone who manages accounts without being an administrator. The role
 * editor never grants these rights (PermissionMatrix::ADMINISTRATOR_ONLY);
 * the guards hold even if the database says otherwise.
 */
function accountManager(): User
{
    return asRole(customRole('account_manager', ['users.view', 'users.create', 'users.edit', 'users.delete']));
}

it('lets only an administrator make an administrator', function () {
    actingAs(accountManager())->post('/users', [
        'name' => 'Escalation', 'email' => 'esc@khf.tj',
        'password' => 'Secret12345', 'password_confirmation' => 'Secret12345',
        'role' => 'admin',
    ])->assertForbidden();

    expect(User::query()->where('email', 'esc@khf.tj')->exists())->toBeFalse();
});

it('keeps administrator accounts out of reach of anyone but an administrator', function () {
    $admin = asRole('admin');

    actingAs(accountManager())->get("/users/{$admin->id}/edit")->assertForbidden();
    actingAs(accountManager())->delete("/users/{$admin->id}")->assertForbidden();

    expect(User::query()->find($admin->id))->not->toBeNull();
});

it('lets an administrator make another administrator', function () {
    actingAs(asRole('admin'))->post('/users', [
        'name' => 'Second Admin', 'email' => 'admin2@khf.tj',
        'password' => 'Secret12345', 'password_confirmation' => 'Secret12345',
        'role' => 'admin',
    ])->assertRedirect('/users');

    expect(User::query()->where('email', 'admin2@khf.tj')->first()?->hasRole('admin'))->toBeTrue();
});

it('assigns a role the administrator built', function () {
    $translator = customRole('translator', customTestRoles()['translator']);

    actingAs(asRole('admin'))->post('/users', [
        'name' => 'Переводчик', 'email' => 'translator@khf.tj',
        'password' => 'Secret12345', 'password_confirmation' => 'Secret12345',
        'role' => $translator,
    ])->assertRedirect('/users');

    expect(User::query()->where('email', 'translator@khf.tj')->sole()->hasRole('translator'))->toBeTrue();
});

it('rejects a role that does not exist', function () {
    actingAs(asRole('admin'))->post('/users', [
        'name' => 'X', 'email' => 'x@khf.tj',
        'password' => 'Secret12345', 'password_confirmation' => 'Secret12345',
        'role' => 'superadmin',
    ])->assertSessionHasErrors(['role' => 'Такой роли нет — выберите из списка.']);
});

it('offers every role in the user form, the ones the administrator built included', function () {
    customRole('translator', customTestRoles()['translator']);

    actingAs(asRole('admin'))->get('/users/create')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('reference.roles.0.value', 'admin')
            ->where('reference.roles.1.value', 'chief_editor')
            ->where('reference.roles.2.value', 'editor')
            ->where('reference.roles.3.value', 'translator')
            ->where('reference.roles.2.label', 'Редактор'));
});

it('records every role change in the journal as a critical event', function () {
    $user = asRole('editor');

    actingAs(asRole('admin'))->put("/users/{$user->id}", [
        'name' => $user->name,
        'email' => $user->email,
        'role' => 'chief_editor',
    ])->assertRedirect('/users');

    $entry = Activity::query()->where('event', 'role_changed')->sole();

    expect($user->fresh()->hasRole('chief_editor'))->toBeTrue()
        ->and($entry->description)->toBe('Роль изменена: «Редактор» → «Главный редактор»')
        ->and($entry->is_critical)->toBeTrue()
        ->and($entry->subject_id)->toBe($user->id);
});

it('does not log a role change when the role stays the same', function () {
    $user = asRole('editor');

    actingAs(asRole('admin'))->put("/users/{$user->id}", [
        'name' => 'Новое имя',
        'email' => $user->email,
        'role' => 'editor',
    ])->assertRedirect('/users');

    expect(Activity::query()->where('event', 'role_changed')->exists())->toBeFalse();
});

it('limits an account to its region only when a region is chosen', function () {
    $region = Region::query()->create([
        'name' => ['ru' => 'Согдийская область', 'tg' => 'Вилояти Суғд', 'en' => 'Sughd'],
        'code' => 'user-test-region',
        'type' => RegionType::Oblast,
        'districts_count' => 1,
        'sort' => 1,
    ]);
    $admin = asRole('admin');
    $account = fn (string $email, array $extra): array => [
        'name' => 'Сотрудник', 'email' => $email,
        'password' => 'Secret12345', 'password_confirmation' => 'Secret12345',
        'role' => 'editor', ...$extra,
    ];

    actingAs($admin)->post('/users', $account('noregion@khf.tj', ['limited_to_region' => true]))
        ->assertSessionHasErrors(['region_id' => 'Чтобы ограничить сотрудника регионом, выберите регион.']);

    actingAs($admin)->post('/users', $account('regional@khf.tj', ['limited_to_region' => true, 'region_id' => $region->id]))
        ->assertRedirect('/users');

    actingAs($admin)->post('/users', $account('admin3@khf.tj', ['limited_to_region' => true, 'region_id' => $region->id, 'role' => 'admin']))
        ->assertRedirect('/users');

    expect(User::query()->where('email', 'regional@khf.tj')->sole()->isLimitedToRegion())->toBeTrue()
        // The administrator works with everything.
        ->and(User::query()->where('email', 'admin3@khf.tj')->sole()->isLimitedToRegion())->toBeFalse();
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

it('explains standard validation errors in Russian, even when APP_LOCALE is English', function () {
    config(['app.locale' => 'en']);

    actingAs(asRole('admin'))
        ->post('/users', [
            'name' => 'Сотрудник',
            'email' => 'staff@khf.tj',
            'password' => 'Secret12345',
            'password_confirmation' => 'Secret12345',
            'role' => 'editor',
            'position' => str_repeat('д', 300),
        ])
        ->assertSessionHasErrors([
            'position' => 'Поле «Должность» не может быть длиннее 255 символов.',
        ]);
});

it('lets an administrator reset a colleague\'s two-factor authentication', function () {
    $admin = asRole('admin');
    $editor = asRole('editor');
    $editor->forceFill([
        'two_factor_secret' => encrypt('secret'),
        'two_factor_recovery_codes' => encrypt(json_encode(['code'])),
        'two_factor_confirmed_at' => now(),
    ])->save();
    DB::table('sessions')->insert([
        'id' => 'editor-session', 'user_id' => $editor->id, 'ip_address' => '127.0.0.1',
        'user_agent' => 'test', 'payload' => '', 'last_activity' => now()->timestamp,
    ]);

    actingAs($admin)
        ->post("/users/{$editor->id}/two-factor/reset")
        ->assertRedirect()
        ->assertSessionHas('success');

    $editor->refresh();

    expect($editor->two_factor_secret)->toBeNull()
        ->and($editor->two_factor_confirmed_at)->toBeNull()
        ->and(DB::table('sessions')->where('user_id', $editor->id)->exists())->toBeFalse()
        ->and(Activity::query()->where('event', 'two_factor_reset')->sole()->is_critical)->toBeTrue();
});

it('does not let an editor reset anyone\'s two-factor authentication', function () {
    $target = asRole('editor');

    actingAs(asRole('editor'))
        ->post("/users/{$target->id}/two-factor/reset")
        ->assertForbidden();
});

it('sends administrators to their profile to change their own two-factor authentication', function () {
    $admin = asRole('admin');

    actingAs($admin)
        ->post("/users/{$admin->id}/two-factor/reset")
        ->assertRedirect()
        ->assertSessionHas('error');
});
