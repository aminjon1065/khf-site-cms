<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Tag;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TaxonomySeeder extends Seeder
{
    public function run(): void
    {
        $newsCategories = [
            ['ru' => 'Спасательные операции', 'tg' => 'Амалиётҳои наҷотбахшӣ'],
            ['ru' => 'Гражданская оборона', 'tg' => 'Мудофиаи граждани'],
            ['ru' => 'Сотрудничество', 'tg' => 'Ҳамкорӣ'],
            ['ru' => 'Обучение', 'tg' => 'Омӯзиш'],
            ['ru' => 'Техника', 'tg' => 'Техника'],
            ['ru' => 'Отчёты', 'tg' => 'Ҳисоботҳо'],
        ];

        foreach ($newsCategories as $sort => $name) {
            Category::updateOrCreate(
                ['type' => 'news', 'slug' => Str::slug($name['ru'], '-', 'ru') ?: 'cat-'.$sort],
                ['name' => $name + ['en' => ''], 'sort' => $sort],
            );
        }

        $tags = [
            'учения', 'гражданская оборона', 'Согдийская область', 'Хатлонская область',
            'спасатели', 'сель', 'лавина', 'обучение', 'международное сотрудничество', 'техника',
        ];

        foreach ($tags as $tag) {
            Tag::updateOrCreate(
                ['slug' => Str::slug($tag, '-', 'ru') ?: Str::slug($tag)],
                ['name' => ['ru' => $tag, 'tg' => $tag, 'en' => '']],
            );
        }
    }
}
