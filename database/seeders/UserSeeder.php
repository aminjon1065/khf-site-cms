<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Models\Region;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

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
                'role' => RoleName::Superadmin, 'region' => null, 'twoFactor' => true,
            ],
            [
                'name' => 'Фаридун Назаров', 'email' => 'f.nazarov@khf.tj',
                'position' => 'Главный редактор', 'department' => 'Пресс-служба',
                'role' => RoleName::ChiefEditor, 'region' => null, 'twoFactor' => true,
            ],
            [
                'name' => 'Шухрат Каримов', 'email' => 'sh.karimov@khf.tj',
                'position' => 'Оператор', 'department' => 'Оперативная служба',
                'role' => RoleName::AlertOperator, 'region' => null, 'twoFactor' => true,
            ],
            [
                'name' => 'Мижгона Раҳимова', 'email' => 'm.rahimova@khf.tj',
                'position' => 'Специалист', 'department' => 'Оперативная служба',
                'role' => RoleName::AlertOperator, 'region' => 'khatlon', 'twoFactor' => true,
            ],
            [
                'name' => 'Далер Сатторов', 'email' => 'd.sattorov@khf.tj',
                'position' => 'Корреспондент', 'department' => 'Пресс-служба',
                'role' => RoleName::Editor, 'region' => null, 'twoFactor' => false,
            ],
            [
                'name' => 'Зарина Назарова', 'email' => 'z.nazarova@khf.tj',
                'position' => 'Специалист', 'department' => 'Пресс-служба',
                'role' => RoleName::Editor, 'region' => 'sughd', 'twoFactor' => true,
            ],
            [
                'name' => 'Джамшед Холов', 'email' => 'j.kholov@khf.tj',
                'position' => 'Переводчик', 'department' => 'Отдел международных связей',
                'role' => RoleName::Translator, 'region' => null, 'twoFactor' => true,
            ],
            [
                'name' => 'Рустам Шарипов', 'email' => 'r.sharipov@khf.tj',
                'position' => 'Руководитель пресс-службы', 'department' => 'Пресс-служба',
                'role' => RoleName::Approver, 'region' => null, 'twoFactor' => true,
            ],
            [
                'name' => 'Нигина Одинаева', 'email' => 'n.odinaeva@khf.tj',
                'position' => 'Региональный редактор', 'department' => 'Согдийское управление',
                'role' => RoleName::RegionalEditor, 'region' => 'sughd', 'twoFactor' => true,
            ],
            [
                'name' => 'Азиз Усмонов', 'email' => 'a.usmonov@khf.tj',
                'position' => 'Специалист', 'department' => 'Отдел цифрового развития',
                'role' => RoleName::Editor, 'region' => null, 'twoFactor' => false,
            ],
        ];

        foreach ($users as $data) {
            $twoFactor = $data['twoFactor'];
            $region = $data['region'];
            $role = $data['role'];

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
                    'two_factor_secret' => $twoFactor ? encrypt(Str::random(32)) : null,
                    'two_factor_recovery_codes' => $twoFactor
                        ? encrypt(json_encode(collect(range(1, 8))->map(fn () => Str::random(10))->all()))
                        : null,
                    'two_factor_confirmed_at' => $twoFactor ? now() : null,
                ],
            );

            $user->syncRoles([$role->value]);
        }
    }
}
