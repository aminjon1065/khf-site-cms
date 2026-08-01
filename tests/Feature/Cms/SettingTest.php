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
    $user->assignRole($role);

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
