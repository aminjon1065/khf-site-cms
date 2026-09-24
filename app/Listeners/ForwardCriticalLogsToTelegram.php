<?php

namespace App\Listeners;

use App\Services\TelegramAlerts;
use Illuminate\Log\Events\MessageLogged;

/**
 * Every critical (and worse) log entry goes to Telegram, whatever channel the
 * log itself is written to — production logs straight to daily files
 * (LOG_CHANNEL=daily), so a log channel of its own would never see them.
 */
class ForwardCriticalLogsToTelegram
{
    private const LEVELS = ['critical', 'alert', 'emergency'];

    public function __construct(private readonly TelegramAlerts $telegram) {}

    public function handle(MessageLogged $event): void
    {
        if (in_array($event->level, self::LEVELS, true)) {
            $this->telegram->send($event->message, $event->context);
        }
    }
}
