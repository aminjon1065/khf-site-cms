<?php

use App\Models\Activity;
use App\Models\Role;
use App\Models\Setting;
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
    giveRole($user, $role);

    return $user;
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function roleForm(array $overrides = []): array
{
    return [
        'label' => 'Оператор предупреждений',
        'description' => 'Дежурные оперативной службы',
        'permissions' => ['alerts.create', 'alerts.edit', 'alerts.publish', 'alerts.approve'],
        ...$overrides,
    ];
}

it('shows the three roles and what each can do', function () {
    actingAs(roleUser('admin'))->get('/roles')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('roles/index')
            ->has('roles', 3)
            ->where('roles.0.value', 'admin')
            ->where('roles.1.value', 'chief_editor')
            ->where('roles.2.value', 'editor')
            ->where('roles.2.label', 'Редактор')
            ->has('modules', 16)
            ->has('actions', 6)
            ->where('can_manage', true),
        );
});

it('forbids a role without users.view from the roles screen', function () {
    actingAs(roleUser('editor'))->get('/roles')->assertForbidden();
});

it('reports the administrator role as having every right', function () {
    actingAs(roleUser('admin'))->get('/roles')
        ->assertInertia(fn ($page) => $page
            ->where('roles.0.is_administrator', true)
            ->where('roles.0.matrix.news.publish', true)
            ->where('roles.0.matrix.settings.edit', true)
            ->where('roles.0.needs_two_factor', true),
        );
});

it('shows only the rights that mean something for a section', function () {
    actingAs(roleUser('admin'))->get('/roles')
        ->assertInertia(fn ($page) => $page
            ->where('modules.8.value', 'taxonomy')
            ->where('modules.8.actions', ['view', 'create', 'edit', 'delete'])
            ->missing('roles.1.matrix.taxonomy.publish'),
        );
});

it('counts users assigned to each role', function () {
    roleUser('editor');
    roleUser('editor');

    actingAs(roleUser('admin'))->get('/roles')
        ->assertInertia(fn ($page) => $page
            ->where('roles.2.value', 'editor')
            ->where('roles.2.user_count', 2),
        );
});

it('starts the chief editor and the editor with the agreed rights', function () {
    $chief = roleUser('chief_editor');
    $editor = roleUser('editor');

    expect($chief->can('documents.approve'))->toBeTrue()
        ->and($chief->can('alerts.delete'))->toBeTrue()
        ->and($chief->can('users.view'))->toBeTrue()
        ->and($chief->can('users.edit'))->toBeFalse()
        ->and($chief->can('settings.view'))->toBeFalse()
        ->and($editor->can('news.publish'))->toBeTrue()
        ->and($editor->can('instructions.publish'))->toBeFalse()
        ->and($editor->can('news.delete'))->toBeFalse();
});

it('shows the roles to a chief editor without letting them change anything', function () {
    $chief = roleUser('chief_editor');
    $editor = Role::query()->where('name', 'editor')->sole();

    actingAs($chief)->get('/roles')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('can_manage', false));
    actingAs($chief)->get('/roles/create')->assertForbidden();
    actingAs($chief)->post('/roles', roleForm())->assertForbidden();
    actingAs($chief)->put("/roles/{$editor->id}", roleForm())->assertForbidden();
    actingAs($chief)->delete("/roles/{$editor->id}")->assertForbidden();

    expect(Role::query()->count())->toBe(3);
});

it('lets the administrator build a role, and records it', function () {
    actingAs(roleUser('admin'))->get('/roles/create')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('roles/form')
            ->where('templates.0.value', 'chief_editor')
            ->where('two_factor_rights', fn ($rights) => collect($rights)->contains('alerts.publish')
                && ! collect($rights)->contains('alerts.view')));

    actingAs(roleUser('admin'))->post('/roles', roleForm())
        ->assertRedirect('/roles')
        ->assertSessionHas('success', 'Роль «Оператор предупреждений» создана.');

    $role = Role::query()->where('label', 'Оператор предупреждений')->sole();
    $entry = Activity::query()->where('log_name', 'roles')->sole();

    expect($role->name)->toBe('operator_preduprezhdeniy')
        ->and($role->description)->toBe('Дежурные оперативной службы')
        // Seeing the section comes with any other right in it.
        ->and($role->permissions->pluck('name')->sort()->values()->all())
        ->toBe(['alerts.approve', 'alerts.create', 'alerts.edit', 'alerts.publish', 'alerts.view'])
        ->and($entry->description)->toBe('Создана роль «Оператор предупреждений»')
        ->and($entry->is_critical)->toBeTrue();
});

it('keeps accounts and settings with the administrator', function (string $right) {
    actingAs(roleUser('admin'))->post('/roles', roleForm(['permissions' => ['news.view', $right]]))
        ->assertSessionHasErrors(['permissions.1' => 'Управлять пользователями и настройками может только администратор.']);

    expect(Role::query()->count())->toBe(3);
})->with(['users.create', 'users.edit', 'users.delete', 'settings.view', 'settings.edit']);

it('refuses a right that means nothing for its section', function () {
    actingAs(roleUser('admin'))->post('/roles', roleForm(['permissions' => ['taxonomy.publish']]))
        ->assertSessionHasErrors('permissions.0');
});

it('refuses a second role with the same name', function () {
    actingAs(roleUser('admin'))->post('/roles', roleForm(['label' => 'Редактор']))
        ->assertSessionHasErrors(['label' => 'Роль с таким названием уже есть.']);
});

it('keeps the key of a role when it is renamed', function () {
    $admin = roleUser('admin');
    actingAs($admin)->post('/roles', roleForm(['label' => 'Переводчик']));
    $role = Role::query()->where('label', 'Переводчик')->sole();

    actingAs($admin)->put("/roles/{$role->id}", roleForm(['label' => 'Переводчики']))->assertRedirect('/roles');

    expect($role->fresh()->name)->toBe('perevodchik')
        ->and($role->fresh()->label)->toBe('Переводчики');
});

it('changes the rights of a built-in role for everyone who holds it', function () {
    $editor = roleUser('editor');
    $role = Role::query()->where('name', 'editor')->sole();
    $rights = array_values(array_diff($role->permissions->pluck('name')->all(), ['news.publish']));

    actingAs(roleUser('admin'))->put("/roles/{$role->id}", [
        'label' => 'Редактор',
        'description' => $role->description,
        'permissions' => $rights,
    ])->assertRedirect('/roles');

    $entry = Activity::query()->where('log_name', 'roles')->sole();

    expect($editor->fresh()->can('news.publish'))->toBeFalse()
        ->and($editor->fresh()->can('news.edit'))->toBeTrue()
        ->and($entry->description)->toBe('Изменена роль «Редактор»')
        ->and($entry->properties['attributes'])->toBe(['убраны права' => 'Новости и заявления: публикация']);
});

it('never changes the administrator role', function () {
    $admin = roleUser('admin');
    $role = Role::query()->where('name', 'admin')->sole();
    $rights = $role->permissions()->count();

    actingAs($admin)->get("/roles/{$role->id}/edit")
        ->assertRedirect('/roles')
        ->assertSessionHas('error');
    actingAs($admin)->put("/roles/{$role->id}", roleForm(['label' => 'Суперадмин', 'permissions' => []]))
        ->assertRedirect('/roles');
    actingAs($admin)->delete("/roles/{$role->id}")->assertRedirect('/roles');

    expect($role->fresh())->not->toBeNull()
        ->and($role->fresh()->label)->toBe('Администратор')
        ->and($role->fresh()->permissions()->count())->toBe($rights);
});

it('deletes a role only when nobody holds it', function () {
    $admin = roleUser('admin');
    $translator = roleUser('translator');
    $role = Role::query()->where('name', 'translator')->sole();

    actingAs($admin)->delete("/roles/{$role->id}")
        ->assertRedirect()
        ->assertSessionHas('error', 'У роли «translator» есть сотрудники (1). Сначала назначьте им другую роль.');
    expect($role->fresh())->not->toBeNull();

    $translator->syncRoles(['editor']);

    actingAs($admin)->delete("/roles/{$role->id}")->assertRedirect('/roles');

    expect(Role::query()->where('name', 'translator')->exists())->toBeFalse()
        ->and(Activity::query()->where('log_name', 'roles')->where('event', 'deleted')->exists())->toBeTrue();
});

it('asks the people of a role for a code as soon as the role may publish', function () {
    Setting::query()->updateOrCreate(['group' => 'security', 'key' => 'require_2fa'], ['value' => true]);
    Setting::query()->updateOrCreate(['group' => 'security', 'key' => 'require_2fa_from'], ['value' => now()->subDay()->toDateString()]);

    $translator = roleUser('translator');
    $role = Role::query()->where('name', 'translator')->sole();

    actingAs($translator)->get('/dashboard')->assertOk();

    actingAs(roleUser('admin')->forceFill(['two_factor_confirmed_at' => now()]))->put("/roles/{$role->id}", [
        'label' => 'Переводчик',
        'permissions' => [...$role->permissions->pluck('name')->all(), 'news.publish'],
    ])->assertRedirect('/roles');

    actingAs($translator->fresh())->get('/dashboard')->assertRedirect('/profile/security');
});
