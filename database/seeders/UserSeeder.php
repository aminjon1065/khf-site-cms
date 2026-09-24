<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Models\Region;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\RecoveryCode;
use ParagonIE\ConstantTime\Base32;
use RuntimeException;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Every demo account shares the password «password»; a stray
        // `db:seed --class=UserSeeder --force` must not open production.
        if (app()->isProduction()) {
            throw new RuntimeException('UserSeeder создаёт демо-учётки с известным паролем и в продакшене не запускается. Администратора создайте командой php artisan cms:create-admin.');
        }

        /** @var array<string, int> $regionIds */
        $regionIds = Region::query()->pluck('id', 'code')->all();

        $password = Hash::make('password');

        $users = [
            [
                'name' => 'Системный администратор', 'email' => 'admin@khf.tj',
                'position' => 'Администратор системы', 'department' => 'ИТ-отдел',
                'role' => RoleName::Admin, 'region' => null,
            ],
            [
                'name' => 'Фаридун Назаров', 'email' => 'f.nazarov@khf.tj',
                'position' => 'Главный редактор', 'department' => 'Пресс-служба',
                'role' => RoleName::ChiefEditor, 'region' => null,
            ],
            [
                'name' => 'Шухрат Каримов', 'email' => 'sh.karimov@khf.tj',
                'position' => 'Оператор', 'department' => 'Оперативная служба',
                'role' => RoleName::ChiefEditor, 'region' => null,
            ],
            [
                'name' => 'Мижгона Раҳимова', 'email' => 'm.rahimova@khf.tj',
                'position' => 'Специалист', 'department' => 'Оперативная служба',
                'role' => RoleName::Editor, 'region' => 'khatlon', 'limited' => true,
            ],
            [
                'name' => 'Далер Сатторов', 'email' => 'd.sattorov@khf.tj',
                'position' => 'Корреспондент', 'department' => 'Пресс-служба',
                'role' => RoleName::Editor, 'region' => null,
            ],
            [
                'name' => 'Зарина Назарова', 'email' => 'z.nazarova@khf.tj',
                'position' => 'Специалист', 'department' => 'Пресс-служба',
                'role' => RoleName::Editor, 'region' => 'sughd',
            ],
            [
                'name' => 'Джамшед Холов', 'email' => 'j.kholov@khf.tj',
                'position' => 'Переводчик', 'department' => 'Отдел международных связей',
                'role' => RoleName::Editor, 'region' => null,
            ],
            [
                'name' => 'Рустам Шарипов', 'email' => 'r.sharipov@khf.tj',
                'position' => 'Руководитель пресс-службы', 'department' => 'Пресс-служба',
                'role' => RoleName::ChiefEditor, 'region' => null,
            ],
            [
                'name' => 'Нигина Одинаева', 'email' => 'n.odinaeva@khf.tj',
                'position' => 'Региональный редактор', 'department' => 'Согдийское управление',
                'role' => RoleName::Editor, 'region' => 'sughd', 'limited' => true,
            ],
            [
                'name' => 'Азиз Усмонов', 'email' => 'a.usmonov@khf.tj',
                'position' => 'Специалист', 'department' => 'Отдел цифрового развития',
                'role' => RoleName::Editor, 'region' => null,
            ],
        ];

        foreach ($users as $data) {
            $region = $data['region'];
            $role = $data['role'];

            // Без DEMO_TWO_FACTOR_SECRET 2FA не сеется: роли с обязательной
            // 2FA сами попадают на страницу настройки при первом входе
            // (RequireTwoFactor) — это рабочий флоу. На e2e-стенде секрет
            // задан, и вход проходит по коду (tests/e2e/fixtures/login.ts).
            $user = User::updateOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'password' => $password,
                    'position' => $data['position'],
                    'department' => $data['department'],
                    'region_id' => $region ? ($regionIds[$region] ?? null) : null,
                    'limited_to_region' => $data['limited'] ?? false,
                    'is_active' => true,
                    'email_verified_at' => now(),
                    'last_login_at' => now()->subHours(random_int(0, 48)),
                    ...$this->twoFactorFor($data['email']),
                ],
            );

            $user->syncRoles([$role->value]);
        }
    }

    /**
     * Confirmed 2FA with a per-account secret derived from the demo secret
     * and the e-mail: codes differ between accounts, so Fortify's guard
     * against reusing a code doesn't trip when tests sign in as several
     * people. Nothing without DEMO_TWO_FACTOR_SECRET.
     *
     * @return array{two_factor_secret: string|null, two_factor_recovery_codes: string|null, two_factor_confirmed_at: CarbonInterface|null}
     */
    private function twoFactorFor(string $email): array
    {
        $demoSecret = config('app.demo_two_factor_secret');

        if (! is_string($demoSecret) || $demoSecret === '') {
            return ['two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null];
        }

        $secret = Base32::encodeUpperUnpadded(hash_hmac('sha1', $email, $demoSecret, true));

        return [
            'two_factor_secret' => Fortify::currentEncrypter()->encrypt($secret),
            'two_factor_recovery_codes' => Fortify::currentEncrypter()->encrypt(json_encode(
                Collection::times(8, fn (): string => RecoveryCode::generate())->all(),
            )),
            'two_factor_confirmed_at' => now(),
        ];
    }
}
