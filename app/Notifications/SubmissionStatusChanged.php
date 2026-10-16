<?php

namespace App\Notifications;

use App\Models\Submission;
use App\Support\PublicApiLabels;
use App\Support\PublicSite;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Email to the citizen when their appeal moves to another stage (owner
 * decision, 2026-10-16: by email — SMS cost money the Committee doesn't
 * have). Only the stage and where to check it: no text of the appeal, no
 * check code (it isn't stored). In the language the appeal was written in.
 */
class SubmissionStatusChanged extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @var array<string, array<string, string>>
     */
    private const TEXT = [
        'subject' => [
            'ru' => 'Обращение :number: :stage',
            'tg' => 'Муроҷиати :number: :stage',
            'en' => 'Appeal :number: :stage',
        ],
        'greeting' => [
            'ru' => 'Здравствуйте, :name!',
            'tg' => 'Ассалому алайкум, :name!',
            'en' => 'Hello, :name!',
        ],
        'changed' => [
            'ru' => 'Статус вашего обращения :number изменился: «:stage».',
            'tg' => 'Вазъи муроҷиати шумо :number тағйир ёфт: «:stage».',
            'en' => 'The status of your appeal :number has changed: “:stage”.',
        ],
        'check' => [
            'ru' => 'Проверить статус можно на странице «Контакты» по номеру и коду проверки:',
            'tg' => 'Вазъро дар саҳифаи «Тамос» бо рақам ва рамзи санҷиш санҷидан мумкин аст:',
            'en' => 'You can check the status on the Contacts page with the number and the check code:',
        ],
        'automatic' => [
            'ru' => 'Письмо отправлено автоматически, отвечать на него не нужно. При угрозе жизни и здоровья звоните 112.',
            'tg' => 'Ин мактуб худкор фиристода шудааст, ба он ҷавоб додан лозим нест. Ҳангоми хатари ҷон ба 112 занг занед.',
            'en' => 'This email was sent automatically; there is no need to reply. If life or health is at risk, call 112.',
        ],
        'signature' => [
            'ru' => 'Комитет по чрезвычайным ситуациям и гражданской обороне при Правительстве Республики Таджикистан',
            'tg' => 'Кумитаи ҳолатҳои фавқулода ва мудофиаи граждании назди Ҳукумати Ҷумҳурии Тоҷикистон',
            'en' => 'Committee of Emergency Situations and Civil Defence under the Government of the Republic of Tajikistan',
        ],
    ];

    /**
     * What the stage means for the citizen — the same words as on the site.
     *
     * @var array<string, array<string, string>>
     */
    private const STAGES = [
        'in_review' => [
            'ru' => 'Обращение рассматривают. С вами свяжутся по указанному телефону или почте.',
            'tg' => 'Муроҷиат баррасӣ мешавад. Бо шумо тавассути телефон ё почтаи зикршуда тамос мегиранд.',
            'en' => 'The appeal is being reviewed. You will be contacted by the phone or email you gave.',
        ],
        'completed' => [
            'ru' => 'Рассмотрение завершено. Если ответ до вас не дошёл, позвоните по телефону доверия.',
            'tg' => 'Баррасӣ анҷом ёфт. Агар ҷавоб ба шумо нарасида бошад, ба телефони боварӣ занг занед.',
            'en' => 'The review is complete. If the reply has not reached you, call the trust line.',
        ],
        'rejected' => [
            'ru' => 'Обращение оставлено без рассмотрения. Подробности — по телефону доверия.',
            'tg' => 'Муроҷиат бе баррасӣ гузошта шуд. Тафсилот — тавассути телефони боварӣ.',
            'en' => 'The appeal was left without review. Details are available on the trust line.',
        ],
    ];

    public function __construct(public Submission $submission)
    {
        $this->onQueue((string) config('queue.names.notifications'));
        $this->afterCommit();
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $locale = in_array($this->submission->locale, ['ru', 'tg', 'en'], true) ? $this->submission->locale : 'ru';
        $stage = $this->submission->status->publicStage();
        $replace = [
            ':number' => (string) $this->submission->tracking_number,
            ':stage' => PublicApiLabels::get('submission_status', $stage, $locale),
            ':name' => $this->submission->name,
        ];
        $text = fn (string $key): string => strtr(self::TEXT[$key][$locale], $replace);

        return (new MailMessage)
            ->subject($text('subject'))
            ->markdown('mail.submission-status', [
                'siteUrl' => PublicSite::url('/', $locale),
                'checkUrl' => PublicSite::url('/contacts', $locale).'#status',
                'greeting' => $text('greeting'),
                'changed' => $text('changed'),
                'meaning' => self::STAGES[$stage][$locale] ?? '',
                'check' => $text('check'),
                'automatic' => $text('automatic'),
                'signature' => $text('signature'),
            ]);
    }
}
