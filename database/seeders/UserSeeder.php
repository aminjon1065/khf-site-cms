<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Models\Region;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        /** @var array<string, int> $regionIds */
        $regionIds = Region::query()->pluck('id', 'code')->all();

        $password = Hash::make('password');

        $users = [
            [
                'name' => 'Системный администратор', 'email' => 'admin@khf.tj',
                'position' => 'Администратор системы', 'department' => 'ИТ-отдел',
                'role' => RoleName::Superadmin, 'region' => null,
            ],
            [
                'name' => 'Фаридун Назаров', 'email' => 'f.nazarov@khf.tj',
                'position' => 'Главный редактор', 'department' => 'Пресс-служба',
                'role' => RoleName::ChiefEditor, 'region' => null,
            ],
            [
                'name' => 'Шухрат Каримов', 'email' => 'sh.karimov@khf.tj',
                'position' => 'Оператор', 'department' => 'Оперативная служба',
                'role' => RoleName::AlertOperator, 'region' => null,
            ],
            [
                'name' => 'Мижгона Раҳимова', 'email' => 'm.rahimova@khf.tj',
                'position' => 'Специалист', 'department' => 'Оперативная служба',
                'role' => RoleName::AlertOperator, 'region' => 'khatlon',
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
                'role' => RoleName::Translator, 'region' => null,
            ],
            [
                'name' => 'Рустам Шарипов', 'email' => 'r.sharipov@khf.tj',
                'position' => 'Руководитель пресс-службы', 'department' => 'Пресс-служба',
                'role' => RoleName::Approver, 'region' => null,
            ],
            [
                'name' => 'Нигина Одинаева', 'email' => 'n.odinaeva@khf.tj',
                'position' => 'Региональный редактор', 'department' => 'Согдийское управление',
                'role' => RoleName::RegionalEditor, 'region' => 'sughd',
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

            // 2FA не сеется: фейковый секрет делает TOTP-вход невозможным.
            // Роли с обязательной 2FA сами попадают на страницу настройки
            // при первом входе (RequireTwoFactor) — это рабочий флоу.
            $user = User::updateOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'password' => $password,
                    'position' => $data['position'],
                    'department' => $data['department'],
                    'region_id' => $region ? ($regionIds[$region] ?? null) : null,
                    'is_active' => true,
                    'email_verified_at' => now(),
                    'last_login_at' => now()->subHours(random_int(0, 48)),
                    'two_factor_secret' => null,
                    'two_factor_recovery_codes' => null,
                    'two_factor_confirmed_at' => null,
                ],
            );

            $user->syncRoles([$role->value]);
        }
    }
}
