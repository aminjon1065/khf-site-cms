<?php

namespace App\Console\Commands;

use App\Services\TelegramAlerts;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * The acceptance check of audit J-5: a test alert reaches the duty chat.
 * Run after setting TELEGRAM_ALERTS_BOT_TOKEN and TELEGRAM_ALERTS_CHAT_ID.
 */
#[Signature('ops:alert-test')]
#[Description('Отправить пробное оповещение в Telegram — проверить, что критические события дойдут до дежурных')]
class AlertTest extends Command
{
    public function handle(TelegramAlerts $telegram): int
    {
        if (! $telegram->isConfigured()) {
            $this->components->error('Оповещения в Telegram не настроены: задайте TELEGRAM_ALERTS_BOT_TOKEN и TELEGRAM_ALERTS_CHAT_ID в .env.');

            return self::FAILURE;
        }

        $sent = $telegram->send(
            'Пробное оповещение: так будут приходить критические события CMS.',
            ['command' => 'ops:alert-test'],
            throttle: false,
        );

        if (! $sent) {
            $this->components->error('Telegram не принял сообщение. Проверьте токен бота, идентификатор чата и что бот добавлен в чат; подробности — в логе (telegram_alert_*).');

            return self::FAILURE;
        }

        $this->components->info('Пробное оповещение отправлено — проверьте чат дежурных.');

        return self::SUCCESS;
    }
}
