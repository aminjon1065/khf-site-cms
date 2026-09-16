<?php

namespace Database\Seeders;

use App\Enums\SubmissionStatus;
use App\Models\Region;
use App\Models\Submission;
use App\Models\SubmissionComment;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Citizen appeals for the electronic reception module: enough of them, in every
 * status, for the inbox to have working filters, an assignment queue and a
 * comment thread to read — which an empty table cannot show.
 *
 * The people are invented; the subjects are the ones the Committee's reception
 * actually deals with (mudflow damage, assistance after a disaster, training
 * requests, false 112 calls, document requests).
 */
class SubmissionSeeder extends Seeder
{
    public function run(): void
    {
        /** @var array<string, int> $users */
        $users = User::query()->pluck('id', 'email')->all();
        /** @var array<string, int> $regions */
        $regions = Region::query()->pluck('id', 'code')->all();

        foreach ($this->appeals() as $index => $appeal) {
            $submission = Submission::query()->updateOrCreate(
                ['email' => $appeal['email'], 'topic' => $appeal['topic']],
                [
                    'name' => $appeal['name'],
                    'phone' => $appeal['phone'],
                    'message' => $appeal['message'],
                    'region_id' => $regions[$appeal['region']] ?? null,
                    'consent' => true,
                    'status' => $appeal['status'],
                    'assigned_to' => $appeal['assignee'] === null ? null : ($users[$appeal['assignee']] ?? null),
                    'ip_address' => '10.0.'.intdiv($index, 250).'.'.($index % 250),
                    'user_agent' => $appeal['user_agent'],
                    'created_at' => now()->subDays($appeal['days_ago'])->setTime(9 + ($index % 8), ($index * 7) % 60),
                ],
            );

            $this->assignTrackingNumber($submission);
            $this->seedComments($submission, $appeal['comments'], $users);
        }
    }

    /**
     * The model assigns the tracking number in a `created` hook, but
     * `DatabaseSeeder` runs with model events muted — under a full `db:seed`
     * that hook never fires and appeals would land without the number citizens
     * use to follow them up. Assign it the same way, explicitly.
     */
    private function assignTrackingNumber(Submission $submission): void
    {
        if (filled($submission->tracking_number)) {
            return;
        }

        $submission->tracking_number = 'КЧС-'
            .($submission->created_at?->format('Y') ?? now()->format('Y'))
            .'-'.str_pad((string) $submission->getKey(), 5, '0', STR_PAD_LEFT);
        $submission->saveQuietly();
    }

    /**
     * @param  array<int, array{author: string, body: string}>  $comments
     * @param  array<string, int>  $users
     */
    private function seedComments(Submission $submission, array $comments, array $users): void
    {
        if ($submission->comments()->exists()) {
            return;
        }

        foreach ($comments as $offset => $comment) {
            SubmissionComment::query()->create([
                'submission_id' => $submission->getKey(),
                'user_id' => $users[$comment['author']] ?? null,
                'body' => $comment['body'],
                'created_at' => $submission->created_at?->copy()->addHours(3 * ($offset + 1)),
            ]);
        }
    }

    /**
     * @return array<int, array{
     *     name: string,
     *     email: string,
     *     phone: string|null,
     *     topic: string,
     *     message: string,
     *     region: string,
     *     status: SubmissionStatus,
     *     assignee: string|null,
     *     days_ago: int,
     *     user_agent: string,
     *     comments: array<int, array{author: string, body: string}>,
     * }>
     */
    private function appeals(): array
    {
        $desktop = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0 Safari/537.36';
        $mobile = 'Mozilla/5.0 (Linux; Android 14; SM-A546E) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0 Mobile Safari/537.36';
        $safari = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1';

        return [
            [
                'name' => 'Каримов Фируз Саидович',
                'email' => 'f.karimov@example.tj',
                'phone' => '+992 93 112-45-67',
                'topic' => 'Материальная помощь после селя',
                'message' => 'После схода селя 12 июня подтоплен дом в джамоате Зархок. Комиссия осмотрела дом, но акт до сих пор не выдан. Прошу разъяснить порядок получения единовременной материальной помощи и сроки рассмотрения.',
                'region' => 'khatlon',
                'status' => SubmissionStatus::InProgress,
                'assignee' => 'sh.karimov@khf.tj',
                'days_ago' => 3,
                'user_agent' => $mobile,
                'comments' => [
                    ['author' => 'sh.karimov@khf.tj', 'body' => 'Запрос направлен в Управление по Хатлонской области, ответ ожидается в течение 5 рабочих дней.'],
                    ['author' => 'f.nazarov@khf.tj', 'body' => 'Держим на контроле: обращение связано с уже открытой сводкой по селю в Дангаре.'],
                ],
            ],
            [
                'name' => 'Назарова Мехрангез',
                'email' => 'm.nazarova@example.tj',
                'phone' => '+992 92 555-10-22',
                'topic' => 'Обучение сотрудников организации',
                'message' => 'Наша организация (48 сотрудников) хотела бы пройти обучение по действиям при землетрясении. Подскажите, как подать заявку в Республиканский учебно-методический центр и есть ли выездные занятия.',
                'region' => 'dushanbe',
                'status' => SubmissionStatus::Completed,
                'assignee' => 'a.usmonov@khf.tj',
                'days_ago' => 21,
                'user_agent' => $desktop,
                'comments' => [
                    ['author' => 'a.usmonov@khf.tj', 'body' => 'Направлена форма заявки и график занятий на IV квартал. Обращение закрыто.'],
                ],
            ],
            [
                'name' => 'Рахмонов Далер',
                'email' => 'd.rahmonov@example.tj',
                'phone' => '+992 90 333-77-01',
                'topic' => 'Опасный склон над дорогой',
                'message' => 'На участке дороги Душанбе — Хорог, примерно 214-й километр, после дождей регулярно падают камни. Прошу обследовать склон и установить предупреждающие знаки.',
                'region' => 'gbao',
                'status' => SubmissionStatus::Reviewed,
                'assignee' => 'sh.karimov@khf.tj',
                'days_ago' => 8,
                'user_agent' => $safari,
                'comments' => [
                    ['author' => 'sh.karimov@khf.tj', 'body' => 'Передано в Управление по ГБАО для выездного обследования совместно с дорожной службой.'],
                ],
            ],
            [
                'name' => 'Шарипова Зулфия',
                'email' => 'z.sharipova@example.tj',
                'phone' => null,
                'topic' => 'Копия акта об ущербе',
                'message' => 'Прошу выдать копию акта обследования дома, пострадавшего при паводке в 2025 году. Оригинал утерян, документ нужен для подачи в органы социальной защиты.',
                'region' => 'sughd',
                'status' => SubmissionStatus::Awaiting,
                'assignee' => 'n.odinaeva@khf.tj',
                'days_ago' => 12,
                'user_agent' => $desktop,
                'comments' => [
                    ['author' => 'n.odinaeva@khf.tj', 'body' => 'Запрошены уточняющие данные: адрес объекта и номер акта. Ожидаем ответ заявителя.'],
                ],
            ],
            [
                'name' => 'Ёров Сухроб',
                'email' => 's.yorov@example.tj',
                'phone' => '+992 98 400-12-90',
                'topic' => 'Ложные вызовы на номер 112',
                'message' => 'С соседнего двора дети регулярно звонят на 112. Как сообщить об этом, чтобы служба не тратила время на ложные вызовы?',
                'region' => 'dushanbe',
                'status' => SubmissionStatus::New,
                'assignee' => null,
                'days_ago' => 1,
                'user_agent' => $mobile,
                'comments' => [],
            ],
            [
                'name' => 'Одинаев Джамшед',
                'email' => 'j.odinaev@example.tj',
                'phone' => '+992 91 202-08-08',
                'topic' => 'Вопрос по вакансии спасателя',
                'message' => 'Подавал документы на конкурс по вакансии спасателя аэромобильного отряда. Уточните, пожалуйста, дату проведения физических нормативов.',
                'region' => 'dushanbe',
                'status' => SubmissionStatus::InProgress,
                'assignee' => 'n.odinaeva@khf.tj',
                'days_ago' => 5,
                'user_agent' => $desktop,
                'comments' => [
                    ['author' => 'n.odinaeva@khf.tj', 'body' => 'Направлен запрос в Управление кадров, ответ будет передан заявителю по телефону.'],
                ],
            ],
            [
                'name' => 'Мирзоев Аброр',
                'email' => 'a.mirzoev@example.tj',
                'phone' => '+992 93 818-44-19',
                'topic' => 'Защитное сооружение в махалле',
                'message' => 'В нашей махалле есть подвальное помещение, которое раньше числилось убежищем. Сейчас оно затоплено. Кто отвечает за его содержание и куда обращаться?',
                'region' => 'rrp',
                'status' => SubmissionStatus::Reviewed,
                'assignee' => 'j.kholov@khf.tj',
                'days_ago' => 15,
                'user_agent' => $mobile,
                'comments' => [
                    ['author' => 'j.kholov@khf.tj', 'body' => 'Обращение перенаправлено в Главное управление гражданской обороны для проверки статуса объекта.'],
                ],
            ],
            [
                'name' => 'Холов Бехруз',
                'email' => 'b.kholov@example.tj',
                'phone' => '+992 92 777-30-15',
                'topic' => 'Ошибка в опубликованном документе',
                'message' => 'В разделе документов в названии постановления № 833 указан неверный год. Прошу проверить и исправить.',
                'region' => 'dushanbe',
                'status' => SubmissionStatus::Completed,
                'assignee' => 'z.nazarova@khf.tj',
                'days_ago' => 30,
                'user_agent' => $desktop,
                'comments' => [
                    ['author' => 'z.nazarova@khf.tj', 'body' => 'Дата сверена с источником и исправлена. Спасибо за замечание.'],
                ],
            ],
            [
                'name' => 'Саидова Нигина',
                'email' => 'n.saidova@example.tj',
                'phone' => '+992 98 101-55-44',
                'topic' => 'Просьба о лекции в школе',
                'message' => 'Школа № 42 просит организовать лекцию для старшеклассников о действиях при землетрясении и правилах вызова экстренных служб.',
                'region' => 'sughd',
                'status' => SubmissionStatus::New,
                'assignee' => null,
                'days_ago' => 2,
                'user_agent' => $safari,
                'comments' => [],
            ],
            [
                'name' => 'Курбонов Сафар',
                'email' => 's.qurbonov@example.tj',
                'phone' => '+992 90 606-70-80',
                'topic' => 'Подтопление приусадебного участка',
                'message' => 'Из-за подъёма уровня воды в реке подтоплен приусадебный участок, размыт берег. Прошу направить специалистов для оценки.',
                'region' => 'khatlon',
                'status' => SubmissionStatus::InProgress,
                'assignee' => 'sh.karimov@khf.tj',
                'days_ago' => 6,
                'user_agent' => $mobile,
                'comments' => [
                    ['author' => 'sh.karimov@khf.tj', 'body' => 'Выезд назначен, заявитель уведомлён.'],
                ],
            ],
            [
                'name' => 'Ахмедов Парвиз',
                'email' => 'p.ahmedov@example.tj',
                'phone' => null,
                'topic' => 'Реклама строительных услуг',
                'message' => 'Предлагаем услуги по строительству, низкие цены, звоните по номеру в подписи. Работаем по всей республике.',
                'region' => 'dushanbe',
                'status' => SubmissionStatus::Spam,
                'assignee' => null,
                'days_ago' => 9,
                'user_agent' => $desktop,
                'comments' => [],
            ],
            [
                'name' => 'Исмоилов Рустам',
                'email' => 'r.ismoilov@example.tj',
                'phone' => '+992 93 909-19-29',
                'topic' => 'Жалоба на соседа',
                'message' => 'Сосед складирует строительный мусор у дороги. Прошу принять меры.',
                'region' => 'dushanbe',
                'status' => SubmissionStatus::Rejected,
                'assignee' => 'n.odinaeva@khf.tj',
                'days_ago' => 18,
                'user_agent' => $mobile,
                'comments' => [
                    ['author' => 'n.odinaeva@khf.tj', 'body' => 'Вопрос вне компетенции Комитета; заявителю разъяснено, куда обратиться.'],
                ],
            ],
        ];
    }
}
