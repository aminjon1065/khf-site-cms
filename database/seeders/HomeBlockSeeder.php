<?php

namespace Database\Seeders;

use App\Models\HomeBlock;
use Illuminate\Database\Seeder;

class HomeBlockSeeder extends Seeder
{
    public function run(): void
    {
        $blocks = [
            ['type' => 'active_alerts', 'title' => ['tg' => 'Хулосаи оперативӣ', 'ru' => 'Оперативная сводка', 'en' => 'Operational overview'], 'enabled' => true, 'config' => ['limit' => 4]],
            ['type' => 'latest_news', 'title' => ['tg' => 'Хабарҳои охирин', 'ru' => 'Последние новости', 'en' => 'Latest news'], 'enabled' => true, 'config' => ['limit' => 6]],
            ['type' => 'instructions', 'title' => ['tg' => 'Дастурҳо ба аҳолӣ', 'ru' => 'Инструкции населению', 'en' => 'Public safety guides'], 'enabled' => true, 'config' => ['limit' => 6]],
            ['type' => 'documents', 'title' => ['tg' => 'Ҳуҷҷатҳои расмӣ', 'ru' => 'Официальные документы', 'en' => 'Official documents'], 'enabled' => true, 'config' => ['limit' => 3]],
            ['type' => 'announcements', 'title' => ['tg' => 'Эълонҳо', 'ru' => 'Объявления', 'en' => 'Announcements'], 'enabled' => true, 'config' => ['limit' => 3]],
            ['type' => 'projects', 'title' => ['tg' => 'Лоиҳаҳо', 'ru' => 'Проекты', 'en' => 'Projects'], 'enabled' => true, 'config' => ['limit' => 2]],
            ['type' => 'regions_map', 'title' => ['tg' => 'Вазъият аз рӯи минтақаҳо', 'ru' => 'Обстановка по регионам', 'en' => 'Regional situation'], 'enabled' => true, 'config' => []],
            ['type' => 'emergency_contacts', 'title' => ['tg' => 'Тамосҳои фаврӣ', 'ru' => 'Экстренные контакты', 'en' => 'Emergency contacts'], 'enabled' => false, 'config' => []],
        ];

        foreach ($blocks as $sort => $data) {
            HomeBlock::updateOrCreate(
                ['type' => $data['type']],
                array_merge($data, ['sort' => $sort]),
            );
        }
    }
}
