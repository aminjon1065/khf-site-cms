<?php

use App\Jobs\RevalidateFrontend;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;

beforeEach(function () {
    seed([RolePermissionSeeder::class, SettingSeeder::class]);
});

/**
 * Экран настроек лежит за `2fa.required`, а `SettingSeeder` включает
 * `security.require_2fa` с датой начала `2026-08-01`. Пока эта дата была в
 * будущем, middleware пропускал кого угодно, и тесты проходили с обычным
 * пользователем; после 2026-08-01 требование включилось и все они начали
 * получать 302 на настройку 2FA. Дата в сидере — осознанная политика, а не
 * опечатка, поэтому чиню не её, а тест: здесь проверяются права и сохранение
 * настроек, и для этого пользователь должен быть таким, каким он приходит на
 * этот экран в проде, — с подтверждённым вторым фактором.
 *
 * Саму политику 2FA (кого и с какой даты пускать) проверяет отдельный
 * TwoFactorPolicyTest, и он задаёт дату относительно `now()`, а не абсолютно.
 */
function settingUser(string $role): User
{
    $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
    giveRole($user, $role);

    return $user;
}

it('lets an admin open the settings screen', function () {
    actingAs(settingUser('admin'))->get('/settings')->assertOk();
});

it('forbids a role without settings access', function () {
    actingAs(settingUser('chief_editor'))->get('/settings')->assertForbidden();
});

it('persists whitelisted settings and ignores sensitive groups', function () {
    Queue::fake();

    actingAs(settingUser('admin'))->put('/settings', [
        'settings' => [
            'org' => ['short_name_ru' => 'КЧС ТЕСТ'],
            'security' => ['api_key' => 'injected-secret'],
        ],
    ])->assertRedirect();

    expect(Setting::query()->where('group', 'org')->where('key', 'short_name_ru')->value('value'))
        ->toBe('КЧС ТЕСТ')
        ->and(Setting::query()->where('group', 'security')->where('key', 'api_key')->exists())
        ->toBeFalse();

    Queue::assertPushed(
        RevalidateFrontend::class,
        fn (RevalidateFrontend $job): bool => $job->type === 'shell'
            && $job->id === null
            && $job->slug === null
            && $job->tags() === ['cms:shell:ru', 'cms:shell:tj', 'cms:shell:en']
            && $job->afterCommit === true,
    );
});

it('rejects unsafe social URLs', function () {
    actingAs(settingUser('admin'))->put('/settings', [
        'settings' => [
            'social' => ['telegram' => 'javascript:alert(1)'],
        ],
    ])->assertSessionHasErrors('settings.social.telegram');
});

it('forbids a non-admin from saving settings', function () {
    actingAs(settingUser('editor'))->put('/settings', [
        'settings' => ['org' => ['short_name_ru' => 'Взлом']],
    ])->assertForbidden();
});

it('lets the administrator choose how old the situation on the site may be', function () {
    actingAs(settingUser('admin'))->get('/settings')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('sections.5.group', 'situation')
            ->where('sections.5.fields.0.key', 'stale_after_minutes')
            ->where('sections.5.fields.0.type', 'select')
            // A day until the administrator chooses otherwise.
            ->where('sections.5.fields.0.value', '1440')
            ->has('sections.5.fields.0.options', 6)
            ->where('sections.5.fields.0.options.5.label', 'Сутки (по умолчанию)'));

    Queue::fake();

    actingAs(settingUser('admin'))->put('/settings', [
        'settings' => ['situation' => ['stale_after_minutes' => '180']],
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect(Setting::query()->where('group', 'situation')->where('key', 'stale_after_minutes')->value('value'))
        ->toBe('180');
});

it('takes only an age from the list', function () {
    actingAs(settingUser('admin'))->put('/settings', [
        'settings' => ['situation' => ['stale_after_minutes' => '5']],
    ])->assertSessionHasErrors(['settings.situation.stale_after_minutes' => 'Выберите срок из списка.']);

    expect(Setting::query()->where('group', 'situation')->exists())->toBeFalse();
});
