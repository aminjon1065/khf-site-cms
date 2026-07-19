<?php

namespace Database\Seeders;

use App\Models\User;
use App\Notifications\WorkflowNotification;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class NotificationSeeder extends Seeder
{
    public function run(): void
    {
        $recipient = User::query()->where('email', 'f.nazarov@khf.tj')->first()
            ?? User::query()->where('email', 'admin@khf.tj')->first();

        if (! $recipient) {
            return;
        }

        $items = [
            ['tone' => 'danger', 'message' => 'Предупреждение «Повышение уровня воды на реке Вахш» ожидает вашего согласования', 'url' => '/approvals', 'created' => now()->subMinutes(15), 'read' => false],
            ['tone' => 'warn', 'message' => 'Перевод на английский не завершён: «Селевая опасность в предгорных районах» — 40%', 'url' => '/alerts', 'created' => now()->subHours(2), 'read' => false],
            ['tone' => 'accent', 'message' => 'Публикация запланирована: «Учения по гражданской обороне» — завтра, 08:00', 'url' => '/news', 'created' => now()->subHours(4), 'read' => false],
            ['tone' => 'neutral', 'message' => 'Д. Сатторов упомянул вас в комментарии к новости о спасательной операции', 'url' => '/news', 'created' => now()->subDay(), 'read' => true],
            ['tone' => 'neutral', 'message' => 'Предупреждение «Паводок на реке Зеравшан» завершено автоматически', 'url' => '/alerts', 'created' => now()->subDays(5), 'read' => true],
        ];

        foreach ($items as $item) {
            DB::table('notifications')->insert([
                'id' => (string) Str::uuid(),
                'type' => WorkflowNotification::class,
                'notifiable_type' => $recipient->getMorphClass(),
                'notifiable_id' => $recipient->getKey(),
                'data' => json_encode([
                    'title' => 'Уведомление',
                    'message' => $item['message'],
                    'tone' => $item['tone'],
                    'subject_type' => null,
                    'subject_id' => null,
                    'url' => $item['url'],
                ], JSON_UNESCAPED_UNICODE),
                'read_at' => $item['read'] ? $item['created'] : null,
                'created_at' => $item['created'],
                'updated_at' => $item['created'],
            ]);
        }
    }
}
