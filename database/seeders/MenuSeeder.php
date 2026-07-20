<?php

namespace Database\Seeders;

use App\Models\MenuItem;
use Illuminate\Database\Seeder;

class MenuSeeder extends Seeder
{
    public function run(): void
    {
        $menus = [
            'main' => [
                ['Новости', '/news'],
                ['Безопасность', '/guides'],
                ['Карта рисков', '/map'],
                ['Документы', '/documents'],
                ['Проекты', '/projects'],
                ['Объявления', '/announcements'],
                ['Контакты', '/contacts'],
            ],
            'footer' => [
                ['Руководство', '/leadership'],
                ['Структура', '/structure'],
                ['Приложение SOS', '/sos'],
                ['Новости и заявления', '/news'],
                ['Инструкции населению', '/guides'],
                ['Карта рисков', '/map'],
                ['Документы', '/documents'],
                ['Проекты и программы', '/projects'],
                ['Вакансии и тендеры', '/announcements'],
                ['Контакты и приёмная', '/contacts'],
            ],
        ];

        foreach ($menus as $location => $items) {
            foreach ($items as $sort => [$label, $url]) {
                MenuItem::updateOrCreate(
                    ['location' => $location, 'url' => $url],
                    [
                        'label' => ['ru' => $label, 'tg' => $label, 'en' => ''],
                        'sort' => $sort,
                        'enabled' => true,
                    ],
                );
            }
        }
    }
}
