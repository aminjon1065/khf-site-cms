<?php

namespace Database\Seeders;

use App\Models\Activity;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class ActivitySeeder extends Seeder
{
    public function run(): void
    {
        /** @var array<string, int> $users */
        $users = User::query()->pluck('id', 'email')->all();

        $entries = [
            [
                'log' => 'alerts', 'email' => 'sh.karimov@khf.tj', 'event' => 'updated', 'critical' => true,
                'desc' => 'Обновил предупреждение «Селевая опасность в предгорных районах»',
                'new' => ['срок действия' => '20.07, 18:00'], 'old' => ['срок действия' => '19.07'],
                'ip' => '10.12.4.18', 'loc' => 'Душанбе', 'at' => '2026-07-18 14:00',
            ],
            [
                'log' => 'alerts', 'email' => 'm.rahimova@khf.tj', 'event' => 'workflow', 'critical' => false,
                'desc' => 'Отправила на согласование «Повышение уровня воды на реке Вахш»',
                'new' => ['статус' => 'на согласовании'], 'old' => ['статус' => 'черновик'],
                'ip' => '10.44.2.7', 'loc' => 'Бохтар', 'at' => '2026-07-18 15:42',
            ],
            [
                'log' => 'news', 'email' => 'd.sattorov@khf.tj', 'event' => 'workflow', 'critical' => true,
                'desc' => 'Опубликовал новость «Спасатели эвакуировали группу альпинистов»',
                'new' => ['статус' => 'опубликовано'], 'old' => ['статус' => 'одобрено'],
                'ip' => '10.12.4.31', 'loc' => 'Душанбе', 'at' => '2026-07-18 11:20',
            ],
            [
                'log' => 'users', 'email' => 'f.nazarov@khf.tj', 'event' => 'updated', 'critical' => true,
                'desc' => 'Изменил роль пользователя «Дж. Холов»',
                'new' => ['роль' => 'переводчик'], 'old' => ['роль' => 'редактор'],
                'ip' => '10.12.4.2', 'loc' => 'Душанбе', 'at' => '2026-07-17 16:48',
            ],
            [
                'log' => 'documents', 'email' => 'z.nazarova@khf.tj', 'event' => 'created', 'critical' => false,
                'desc' => 'Загрузила документ «Отчёт о деятельности за первое полугодие 2026»',
                'new' => ['файл' => '1,3 МБ'], 'old' => [],
                'ip' => '10.31.8.14', 'loc' => 'Худжанд', 'at' => '2026-07-17 17:05',
            ],
            [
                'log' => 'home', 'email' => 'a.usmonov@khf.tj', 'event' => 'updated', 'critical' => false,
                'desc' => 'Изменил блок главной страницы «Оперативная сводка»',
                'new' => ['количество элементов' => '4'], 'old' => ['количество элементов' => '3'],
                'ip' => '10.12.4.9', 'loc' => 'Душанбе', 'at' => '2026-07-17 12:30',
            ],
            [
                'log' => 'alerts', 'email' => null, 'event' => 'workflow', 'critical' => false,
                'desc' => 'Завершила предупреждение «Паводок на реке Зеравшан» (по сроку)',
                'new' => ['статус' => 'завершено'], 'old' => ['статус' => 'опубликовано'],
                'ip' => null, 'loc' => 'планировщик', 'at' => '2026-07-14 09:00',
            ],
        ];

        foreach ($entries as $entry) {
            $activity = new Activity;
            $activity->forceFill([
                'log_name' => $entry['log'],
                'description' => $entry['desc'],
                'event' => $entry['event'],
                'causer_type' => $entry['email'] ? User::class : null,
                'causer_id' => $entry['email'] ? ($users[$entry['email']] ?? null) : null,
                'properties' => ['attributes' => $entry['new'], 'old' => $entry['old']],
                'is_critical' => $entry['critical'],
                'ip_address' => $entry['ip'],
                'location' => $entry['loc'],
                'created_at' => Carbon::parse($entry['at']),
                'updated_at' => Carbon::parse($entry['at']),
            ]);
            $activity->save();
        }
    }
}
