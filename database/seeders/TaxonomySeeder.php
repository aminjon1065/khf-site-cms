<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Tag;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Editorial taxonomy for the news feed. The categories cover how the
 * Committee's own material actually splits up — rescue operations, warnings,
 * incidents, cooperation, training, equipment and reporting — so the public
 * category filter has a meaningful option behind every chip.
 *
 * @see SourceNewsSeeder::classify() which files imported material into these.
 */
class TaxonomySeeder extends Seeder
{
    public function run(): void
    {
        $newsCategories = [
            ['ru' => 'Новости', 'tg' => 'Ахбори рӯз', 'en' => 'News'],
            ['ru' => 'Спасательные операции', 'tg' => 'Амалиётҳои наҷотбахшӣ', 'en' => 'Rescue operations'],
            ['ru' => 'Предупреждения', 'tg' => 'Огоҳиномаҳо', 'en' => 'Warnings'],
            ['ru' => 'Происшествия', 'tg' => 'Ҳодисаҳо', 'en' => 'Incidents'],
            ['ru' => 'Гражданская оборона', 'tg' => 'Мудофиаи гражданӣ', 'en' => 'Civil defence'],
            ['ru' => 'Сотрудничество', 'tg' => 'Ҳамкорӣ', 'en' => 'Cooperation'],
            ['ru' => 'Обучение', 'tg' => 'Омӯзиш', 'en' => 'Training'],
            ['ru' => 'Техника', 'tg' => 'Техника', 'en' => 'Equipment'],
            ['ru' => 'Отчёты', 'tg' => 'Ҳисоботҳо', 'en' => 'Reports'],
        ];

        foreach ($newsCategories as $sort => $name) {
            Category::updateOrCreate(
                ['type' => 'news', 'slug' => Str::slug($name['ru'], '-', 'ru') ?: 'cat-'.$sort],
                ['name' => $name, 'sort' => $sort],
            );
        }

        // Cross-cutting labels, matching the vocabulary the source sites use on
        // their own material (regional directorates, hazard types, activities).
        $tags = [
            ['ru' => 'учения', 'tg' => 'машқҳо'],
            ['ru' => 'гражданская оборона', 'tg' => 'мудофиаи гражданӣ'],
            ['ru' => 'спасатели', 'tg' => 'наҷотдиҳандагон'],
            ['ru' => 'сель', 'tg' => 'сел'],
            ['ru' => 'лавина', 'tg' => 'тарма'],
            ['ru' => 'паводок', 'tg' => 'обхезӣ'],
            ['ru' => 'землетрясение', 'tg' => 'заминларза'],
            ['ru' => 'пожар', 'tg' => 'сӯхтор'],
            ['ru' => 'обучение', 'tg' => 'омӯзиш'],
            ['ru' => 'международное сотрудничество', 'tg' => 'ҳамкории байналмилалӣ'],
            ['ru' => 'техника', 'tg' => 'техника'],
            ['ru' => 'служба 112', 'tg' => 'хидмати 112'],
            ['ru' => 'Согдийская область', 'tg' => 'вилояти Суғд'],
            ['ru' => 'Хатлонская область', 'tg' => 'вилояти Хатлон'],
            ['ru' => 'ГБАО', 'tg' => 'ВМКБ'],
            ['ru' => 'город Душанбе', 'tg' => 'шаҳри Душанбе'],
            ['ru' => 'РРП', 'tg' => 'НТҶ'],
            ['ru' => 'Сарезское озеро', 'tg' => 'кӯли Сарез'],
        ];

        foreach ($tags as $tag) {
            Tag::updateOrCreate(
                ['slug' => Str::slug($tag['ru'], '-', 'ru') ?: Str::slug($tag['ru'])],
                ['name' => $tag + ['en' => '']],
            );
        }
    }
}
