<?php

namespace Database\Seeders;

use App\Models\MenuItem;
use Illuminate\Database\Seeder;

class MenuSeeder extends Seeder
{
    /**
     * Default menus with labels in every site language — the public site
     * shows the CMS label as is (the same wording as its built-in menu).
     *
     * @var array<string, list<array{0: string, 1: array{ru: string, tg: string, en: string}}>>
     */
    public const MENUS = [
        'main' => [
            ['/news', ['ru' => 'Новости', 'tg' => 'Хабарҳо', 'en' => 'News']],
            ['/guides', ['ru' => 'Безопасность', 'tg' => 'Бехатарӣ', 'en' => 'Safety']],
            ['/map', ['ru' => 'Карта рисков', 'tg' => 'Харитаи хатарҳо', 'en' => 'Risk map']],
            ['/documents', ['ru' => 'Документы', 'tg' => 'Ҳуҷҷатҳо', 'en' => 'Documents']],
            ['/projects', ['ru' => 'Проекты', 'tg' => 'Лоиҳаҳо', 'en' => 'Projects']],
            ['/announcements', ['ru' => 'Объявления', 'tg' => 'Эълонҳо', 'en' => 'Announcements']],
            ['/contacts', ['ru' => 'Контакты', 'tg' => 'Тамос', 'en' => 'Contacts']],
        ],
        'footer' => [
            ['/leadership', ['ru' => 'Руководство', 'tg' => 'Роҳбарият', 'en' => 'Leadership']],
            ['/structure', ['ru' => 'Структура', 'tg' => 'Сохтор', 'en' => 'Structure']],
            ['/sos', ['ru' => 'Приложение SOS', 'tg' => 'Барномаи SOS', 'en' => 'SOS app']],
            ['/news', ['ru' => 'Новости и заявления', 'tg' => 'Хабарҳо ва баёнияҳо', 'en' => 'News & statements']],
            ['/guides', ['ru' => 'Инструкции населению', 'tg' => 'Дастурҳо ба аҳолӣ', 'en' => 'Public safety guides']],
            ['/map', ['ru' => 'Карта рисков', 'tg' => 'Харитаи хатарҳо', 'en' => 'Risk map']],
            ['/documents', ['ru' => 'Документы', 'tg' => 'Ҳуҷҷатҳо', 'en' => 'Documents']],
            ['/projects', ['ru' => 'Проекты и программы', 'tg' => 'Лоиҳаҳо ва барномаҳо', 'en' => 'Projects & programmes']],
            ['/announcements', ['ru' => 'Вакансии и тендеры', 'tg' => 'Ҷойҳои холӣ ва тендерҳо', 'en' => 'Vacancies & tenders']],
            ['/contacts', ['ru' => 'Контакты и приёмная', 'tg' => 'Тамос ва қабулгоҳ', 'en' => 'Contacts & reception']],
        ],
    ];

    public function run(): void
    {
        foreach (self::MENUS as $location => $items) {
            foreach ($items as $sort => [$url, $label]) {
                MenuItem::updateOrCreate(
                    ['location' => $location, 'url' => $url],
                    [
                        'label' => $label,
                        'sort' => $sort,
                        'enabled' => true,
                    ],
                );
            }
        }
    }
}
