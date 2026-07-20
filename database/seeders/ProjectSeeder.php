<?php

namespace Database\Seeders;

use App\Enums\ContentStatus;
use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class ProjectSeeder extends Seeder
{
    public function run(): void
    {
        $authorId = User::query()->where('email', 'f.nazarov@khf.tj')->value('id');

        $projects = [
            [
                'slug' => 'early-warning-system',
                'ru' => 'Модернизация системы раннего оповещения населения',
                'tg' => 'Навсозии низоми огоҳонии барвақтии аҳолӣ',
                'desc' => 'Сирены и ячейковое вещание в 47 селеопасных джамоатах, интеграция с гидрометеослужбой и службой 112.',
                'lifecycle' => ProjectStatus::Implementing, 'code' => 'Проект 01', 'years' => '2026–2030',
                'partner' => 'УСРБ ООН, Всемирный банк', 'budget' => '18,4 млн долл. США',
                'goals' => [
                    'Установка 180 сиренно-речевых установок в 47 селеопасных джамоатах.',
                    'Запуск ячеистого вещания (Cell Broadcast) совместно с операторами связи.',
                    'Интеграция датчиков гидрометеослужбы и сейсмостанций в единую платформу ЦУКС.',
                    'Обучение 2 000 ответственных лиц джамоатов и регулярные учения с населением.',
                ],
                'timeline' => [
                    ['date' => 'Июнь 2026', 'text' => 'Завершено проектирование первой очереди — 60 установок в Кулябской зоне.', 'tone' => 'success'],
                    ['date' => 'Апрель 2026', 'text' => 'Подписано соглашение о финансировании с Всемирным банком.', 'tone' => 'success'],
                    ['date' => 'IV квартал 2026 — план', 'text' => 'Монтаж первых 20 установок, пилотный запуск Cell Broadcast.', 'tone' => 'info'],
                ],
                'direction' => ['address' => 'г. Душанбе, ул. Лохути, 26, каб. 314', 'phone' => '+992 (37) 221-59-00', 'email' => 'ews@khf.tj'],
            ],
            [
                'slug' => 'panj-river-drr',
                'ru' => 'Снижение риска бедствий в бассейне реки Пяндж',
                'tg' => 'Коҳиши хатари офатҳо дар ҳавзаи дарёи Панҷ',
                'desc' => 'Берегоукрепление, селеотводные каналы и обучение добровольных команд реагирования в 60 кишлаках ГБАО и Хатлона.',
                'lifecycle' => ProjectStatus::Implementing, 'code' => 'Проект 02', 'years' => '2024–2028',
                'partner' => 'Фонд Ага Хана, Правительство Швейцарии', 'budget' => '9,6 млн долл. США',
                'goals' => ['Берегоукрепление и селеотводные каналы.', 'Обучение добровольных команд реагирования в 60 кишлаках.'],
                'timeline' => [['date' => 'Май 2026', 'text' => 'Завершены работы по берегоукреплению в Дарвазском районе.', 'tone' => 'success']],
                'direction' => ['address' => 'г. Хорог', 'phone' => '+992 (35) 222-00-00', 'email' => 'panj@khf.tj'],
            ],
            [
                'slug' => 'rescue-112-modernization',
                'ru' => 'Модернизация единой службы спасения 112',
                'tg' => 'Навсозии хадамоти ягонаи наҷоти 112',
                'desc' => 'Новый центр обработки вызовов, цифровая радиосвязь и аварийно-спасательный инструмент для региональных управлений.',
                'lifecycle' => ProjectStatus::Implementing, 'code' => 'Проект 03', 'years' => '2025–2027',
                'partner' => 'Европейский союз', 'budget' => '6,2 млн евро',
                'goals' => ['Новый центр обработки вызовов.', 'Цифровая радиосвязь для региональных управлений.'],
                'timeline' => [['date' => 'Март 2026', 'text' => 'Введён в эксплуатацию центр обработки вызовов в Душанбе.', 'tone' => 'success']],
                'direction' => ['address' => 'г. Душанбе', 'phone' => '112', 'email' => '112@khf.tj'],
            ],
            [
                'slug' => 'seismic-safety-schools',
                'ru' => 'Сейсмоустойчивость школ и больниц',
                'tg' => 'Устувории зилзилавии мактабҳо ва беморхонаҳо',
                'desc' => 'Обследование и усиление 120 социальных объектов в зонах сейсмичности 8–9 баллов, типовые проекты безопасных школ.',
                'lifecycle' => ProjectStatus::Preparation, 'code' => 'Проект 04', 'years' => '2027–2031',
                'partner' => 'Всемирный банк, JICA', 'budget' => 'оценка — 25 млн долл. США',
                'goals' => ['Обследование 120 социальных объектов.', 'Типовые проекты безопасных школ.'],
                'timeline' => [['date' => 'IV квартал 2026 — план', 'text' => 'Разработка технико-экономического обоснования.', 'tone' => 'info']],
                'direction' => ['address' => 'г. Душанбе', 'phone' => '+992 (37) 221-59-00', 'email' => 'schools@khf.tj'],
            ],
        ];

        foreach ($projects as $index => $p) {
            Project::updateOrCreate(
                ['slug' => $p['slug']],
                [
                    'title' => ['ru' => $p['ru'], 'tg' => $p['tg'], 'en' => ''],
                    'summary' => ['ru' => $p['desc'], 'tg' => $p['desc'], 'en' => ''],
                    'body' => ['ru' => $p['desc'], 'tg' => '', 'en' => ''],
                    'status' => ContentStatus::Published,
                    'published_at' => Carbon::parse('2026-06-01')->addDays($index),
                    'lifecycle_status' => $p['lifecycle'],
                    'code' => $p['code'],
                    'years' => $p['years'],
                    'customer' => 'КЧС Республики Таджикистан',
                    'partner' => $p['partner'],
                    'budget' => $p['budget'],
                    'goals' => ['ru' => $p['goals'], 'tg' => [], 'en' => []],
                    'timeline' => $p['timeline'],
                    'direction' => $p['direction'],
                    'sort' => $index,
                    'author_id' => $authorId,
                ],
            );
        }
    }
}
