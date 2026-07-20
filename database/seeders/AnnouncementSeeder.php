<?php

namespace Database\Seeders;

use App\Enums\AnnouncementKind;
use App\Enums\ContentStatus;
use App\Models\Announcement;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class AnnouncementSeeder extends Seeder
{
    public function run(): void
    {
        $authorId = User::query()->where('email', 'f.nazarov@khf.tj')->value('id');

        $items = [
            [
                'kind' => AnnouncementKind::Vacancy,
                'ru' => 'Спасатель аэромобильного отряда — г. Душанбе (2 должности)',
                'tg' => 'Наҷотдиҳандаи гурӯҳи аэромобилӣ — ш. Душанбе (2 вазифа)',
                'org' => 'Служба спасения, центральный аппарат',
                'desc' => 'Требования: возраст до 35 лет, физическая подготовка, готовность к командировкам. Обучение за счёт Комитета.',
                'deadline' => '2026-07-31',
            ],
            [
                'kind' => AnnouncementKind::Vacancy,
                'ru' => 'Инженер отдела гражданской обороны — Управление по Согдийской области',
                'tg' => 'Муҳандиси шӯъбаи мудофиаи граждани — Раёсати вилояти Суғд',
                'org' => 'Управление по Согдийской области, г. Худжанд',
                'desc' => 'Высшее техническое образование, опыт работы от 3 лет. Знание таджикского и русского языков обязательно.',
                'deadline' => '2026-07-25',
            ],
            [
                'kind' => AnnouncementKind::Vacancy,
                'ru' => 'Оператор службы 112 — ЦУКС (сменный график)',
                'tg' => 'Оператори хадамоти 112 — МИБ (ҷадвали басма)',
                'org' => 'Центр управления в кризисных ситуациях',
                'desc' => 'Приём и обработка экстренных вызовов. Владение таджикским и русским языками, стрессоустойчивость.',
                'deadline' => '2026-08-10',
            ],
            [
                'kind' => AnnouncementKind::Tender,
                'ru' => 'Закупка аварийно-спасательного инструмента для региональных управлений',
                'tg' => 'Хариди асбоби наҷотбахш барои раёсатҳои минтақавӣ',
                'org' => 'Проект «Модернизация службы 112»',
                'desc' => 'Гидравлический инструмент, осветительное оборудование. Условия участия — в тендерной документации.',
                'deadline' => '2026-08-05',
            ],
            [
                'kind' => AnnouncementKind::Tender,
                'ru' => 'Поставка ГСМ для автопарка Комитета на второе полугодие 2026 года',
                'tg' => 'Таъмини сӯзишворӣ барои нақлиёти Кумита барои нимсолаи дуюми соли 2026',
                'org' => 'Отдел государственных закупок',
                'desc' => 'Дизельное топливо и бензин АИ-92/95 с доставкой в региональные управления.',
                'deadline' => '2026-07-28',
            ],
            [
                'kind' => AnnouncementKind::Tender,
                'ru' => 'Капитальный ремонт учебного корпуса Учебного центра',
                'tg' => 'Таъмири асосии бинои таълимии Маркази таълимӣ',
                'org' => 'Отдел государственных закупок',
                'desc' => 'Итоги подведены. Победитель — ООО «Сохтмонсервис». Протокол опубликован в разделе документов.',
                'deadline' => '2026-06-30',
            ],
        ];

        foreach ($items as $it) {
            $announcement = Announcement::query()->where('title->ru', $it['ru'])->first() ?? new Announcement;

            $announcement->fill([
                'title' => ['ru' => $it['ru'], 'tg' => $it['tg'], 'en' => ''],
                'body' => ['ru' => $it['desc'], 'tg' => $it['desc'], 'en' => ''],
                'kind' => $it['kind'],
                'org' => $it['org'],
                'deadline' => Carbon::parse($it['deadline']),
                'status' => ContentStatus::Published,
                'published_at' => Carbon::parse('2026-07-10'),
                'author_id' => $authorId,
            ])->save();
        }
    }
}
