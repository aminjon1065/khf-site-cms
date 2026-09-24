<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Critical events of the CMS as Telegram messages (audit J-5): a failed
 * scheduled task, a failing queue, a site that stopped taking the
 * revalidation webhook — whoever is on duty learns it from the phone, not
 * from citizens. A bot of the Committee writes to a chat or channel; both are
 * set in .env (TELEGRAM_ALERTS_BOT_TOKEN, TELEGRAM_ALERTS_CHAT_ID). Without
 * them nothing is sent.
 *
 * Sent right away, not through the queue: the event is often the queue
 * failing. The same message goes out at most once in THROTTLE_MINUTES, so a
 * burst of identical failures doesn't flood the chat.
 */
class TelegramAlerts
{
    public const THROTTLE_MINUTES = 10;

    /** Telegram's limit is 4096 characters; the rest is cut. */
    private const MAX_LENGTH = 3500;

    public function isConfigured(): bool
    {
        return filled(config('services.telegram_alerts.bot_token'))
            && filled(config('services.telegram_alerts.chat_id'));
    }

    /**
     * @param  array<string, mixed>  $context
     * @return bool whether Telegram took the message
     */
    public function send(string $message, array $context = [], bool $throttle = true): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }

        $text = $this->text($message, $context);

        if ($throttle && ! $this->firstInWindow($text)) {
            return false;
        }

        try {
            $response = Http::timeout(5)
                ->asForm()
                ->post(
                    'https://api.telegram.org/bot'.config('services.telegram_alerts.bot_token').'/sendMessage',
                    [
                        'chat_id' => config('services.telegram_alerts.chat_id'),
                        'text' => $text,
                        'disable_web_page_preview' => 'true',
                    ],
                );

            if ($response->successful()) {
                return true;
            }

            // A warning, not critical: a critical here would be forwarded again.
            Log::warning('telegram_alert_rejected', ['status' => $response->status()]);
        } catch (Throwable $exception) {
            Log::warning('telegram_alert_failed', ['error' => $exception->getMessage()]);
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function text(string $message, array $context): string
    {
        $lines = [
            '🚨 '.config('app.name').' · '.app()->environment(),
            $message,
        ];

        foreach ($context as $key => $value) {
            if (is_scalar($value) && $key !== 'event') {
                $lines[] = "{$key}: {$value}";
            }
        }

        $lines[] = now()->timezone('Asia/Dushanbe')->format('d.m.Y H:i').' (Душанбе)';

        return mb_substr(implode("\n", $lines), 0, self::MAX_LENGTH);
    }

    private function firstInWindow(string $text): bool
    {
        // The time line changes every minute: throttle on the rest.
        $key = 'telegram-alert:'.sha1((string) preg_replace('/\n[^\n]*\(Душанбе\)$/u', '', $text));

        try {
            return Cache::add($key, true, now()->addMinutes(self::THROTTLE_MINUTES));
        } catch (Throwable) {
            // Better one message too many than a silent failure.
            return true;
        }
    }
}
