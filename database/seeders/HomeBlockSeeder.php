<?php

namespace Database\Seeders;

use App\Models\HomeBlock;
use Illuminate\Database\Seeder;

class HomeBlockSeeder extends Seeder
{
    public function run(): void
    {
        $blocks = [
            ['type' => 'active_alerts', 'title' => ['ru' => 'Оперативная сводка', 'tg' => 'Хулосаи оперативӣ'], 'enabled' => true, 'config' => ['limit' => 4]],
            ['type' => 'latest_news', 'title' => ['ru' => 'Последние новости', 'tg' => 'Хабарҳои охирин'], 'enabled' => true, 'config' => ['limit' => 6]],
            ['type' => 'instructions', 'title' => ['ru' => 'Инструкции населению', 'tg' => 'Дастурҳо ба аҳолӣ'], 'enabled' => true, 'config' => ['limit' => 6]],
            ['type' => 'documents', 'title' => ['ru' => 'Официальные документы', 'tg' => 'Ҳуҷҷатҳои расмӣ'], 'enabled' => true, 'config' => ['limit' => 5]],
            ['type' => 'regions_map', 'title' => ['ru' => 'Обстановка по регионам', 'tg' => 'Вазъият аз рӯи минтақаҳо'], 'enabled' => true, 'config' => []],
            ['type' => 'emergency_contacts', 'title' => ['ru' => 'Экстренные контакты', 'tg' => 'Тамосҳои фаврӣ'], 'enabled' => false, 'config' => []],
        ];

        foreach ($blocks as $sort => $data) {
            HomeBlock::updateOrCreate(
                ['type' => $data['type']],
                array_merge($data, ['sort' => $sort]),
            );
        }
    }
}
