<?php

namespace Database\Seeders;

use App\Enums\ContentStatus;
use App\Models\Category;
use App\Models\News;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class NewsSeeder extends Seeder
{
    public function run(): void
    {
        /** @var array<string, int> $users */
        $users = User::query()->pluck('id', 'email')->all();
        /** @var array<string, int> $cats */
        $cats = Category::query()->where('type', 'news')->get()->mapWithKeys(
            fn (Category $c) => [$c->getTranslation('name', 'ru') => $c->id],
        )->all();

        $items = [
            [
                'ru' => 'Таджикистан и УСРБ ООН подписали программу снижения риска бедствий на 2026–2030 годы',
                'tg' => 'Тоҷикистон ва СММ барномаи коҳиши хатари офатҳоро барои солҳои 2026–2030 имзо карданд',
                'summary' => 'Документ определяет приоритеты сотрудничества в области предупреждения чрезвычайных ситуаций.',
                'cat' => 'Сотрудничество', 'status' => ContentStatus::Published, 'author' => 'd.sattorov@khf.tj',
                'published' => '2026-07-16 10:00', 'views' => 4218, 'en' => true,
            ],
            [
                'ru' => 'Спасатели эвакуировали группу альпинистов со склона пика Исмоили Сомони',
                'tg' => 'Наҷотдиҳандагон гурӯҳи кӯҳнавардонро аз домани қуллаи Исмоили Сомонӣ эвакуатсия карданд',
                'summary' => 'Операция проводилась на высоте более 6000 метров при сложных погодных условиях.',
                'cat' => 'Спасательные операции', 'status' => ContentStatus::Published, 'author' => 'd.sattorov@khf.tj',
                'published' => '2026-07-16 11:20', 'views' => 9640, 'en' => false,
            ],
            [
                'ru' => 'Учения по гражданской обороне в Согдийской области',
                'tg' => 'Машқҳои мудофиаи граждани дар вилояти Суғд',
                'summary' => 'С 21 по 25 июля пройдут плановые учения с участием региональных подразделений КЧС.',
                'cat' => 'Гражданская оборона', 'status' => ContentStatus::Scheduled, 'author' => 'z.nazarova@khf.tj',
                'scheduled' => '2026-07-19 08:00', 'views' => 0, 'en' => false,
            ],
            [
                'ru' => 'Региональные управления получили 18 единиц новой аварийно-спасательной техники',
                'tg' => 'Раёсатҳои минтақавӣ 18 адад техникаи наҷотбахши нав гирифтанд',
                'summary' => 'Техника распределена между наиболее подверженными рискам регионами.',
                'cat' => 'Техника', 'status' => ContentStatus::Published, 'author' => 'a.usmonov@khf.tj',
                'published' => '2026-07-12 09:30', 'views' => 2105, 'en' => true,
            ],
            [
                'ru' => 'Более 4 000 жителей ГБАО прошли обучение действиям при лавинной опасности',
                'tg' => 'Зиёда аз 4 000 сокини ВМКБ оид ба рафтор ҳангоми хатари тарма омӯзиш гузаштанд',
                'summary' => 'Занятия проводились в рамках подготовки к зимнему периоду.',
                'cat' => 'Обучение', 'status' => ContentStatus::Review, 'author' => 'z.nazarova@khf.tj',
                'views' => 0, 'en' => false,
            ],
            [
                'ru' => 'Итоги полугодия: 247 спасательных операций, 1 318 человек спасено',
                'tg' => 'Натиҷаҳои ним сол: 247 амалиёти наҷот, 1 318 нафар наҷот дода шуд',
                'summary' => 'Опубликованы предварительные итоги деятельности Комитета за первое полугодие.',
                'cat' => 'Отчёты', 'status' => ContentStatus::Draft, 'author' => 'f.nazarov@khf.tj',
                'views' => 0, 'en' => false,
            ],
        ];

        foreach ($items as $it) {
            $title = ['ru' => $it['ru'], 'tg' => $it['tg'], 'en' => $it['en'] ? $it['ru'] : ''];
            $summary = ['ru' => $it['summary'], 'tg' => $it['summary'], 'en' => $it['en'] ? $it['summary'] : ''];
            $body = [
                'ru' => $it['summary'].' '.fake('ru_RU')->paragraph(4),
                'tg' => $it['summary'],
                'en' => $it['en'] ? $it['summary'] : '',
            ];

            News::updateOrCreate(
                ['slug' => Str::slug($it['ru'], '-', 'ru') ?: Str::slug(Str::limit($it['ru'], 40, ''))],
                [
                    'title' => $title,
                    'summary' => $summary,
                    'body' => $body,
                    'category_id' => $cats[$it['cat']] ?? null,
                    'status' => $it['status'],
                    'show_on_home' => true,
                    'is_pinned' => false,
                    'views_count' => $it['views'],
                    'author_id' => $users[$it['author']] ?? null,
                    'published_at' => isset($it['published']) ? Carbon::parse($it['published']) : null,
                    'scheduled_at' => isset($it['scheduled']) ? Carbon::parse($it['scheduled']) : null,
                    'cover_alt' => $it['ru'],
                ],
            );
        }
    }
}
