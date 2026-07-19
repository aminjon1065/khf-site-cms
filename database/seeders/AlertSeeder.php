<?php

namespace Database\Seeders;

use App\Enums\ContentStatus;
use App\Enums\HazardType;
use App\Enums\Severity;
use App\Models\Alert;
use App\Models\District;
use App\Models\Region;
use App\Models\User;
use App\Models\WorkflowTransition;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class AlertSeeder extends Seeder
{
    public function run(): void
    {
        /** @var array<string, int> $users */
        $users = User::query()->pluck('id', 'email')->all();
        /** @var array<string, int> $regions */
        $regions = Region::query()->pluck('id', 'code')->all();

        $khatlonDistricts = District::query()
            ->where('region_id', $regions['khatlon'] ?? 0)
            ->whereIn('sort', [2, 3, 4, 5, 6, 7]) // Дангара, Фархор, Восе, Муминабад, Ховалинг, Норак
            ->pluck('id')
            ->all();

        $alerts = [
            [
                'internal_title' => 'Селевая опасность — Хатлонская область, июль 2026',
                'title' => [
                    'ru' => 'Селевая опасность в предгорных районах Хатлонской области',
                    'tg' => 'Хатари сел дар ноҳияҳои доманакӯҳи вилояти Хатлон',
                    'en' => 'Mudflow risk in the foothill districts of Khatlon',
                ],
                'summary' => [
                    'ru' => 'До 20 июля возможен сход селей в предгорных районах. Избегайте русел рек и пойменных участков.',
                    'tg' => 'То 20 июл дар ноҳияҳои доманакӯҳ имкони фаромадани сел вуҷуд дорад. Аз маҷрои дарёҳо дурӣ ҷӯед.',
                    'en' => 'Mudflows are possible until 20 July in foothill areas. Avoid river channels and floodplains.',
                ],
                'body' => [
                    'ru' => 'По прогнозу Агентства по гидрометеорологии, из-за интенсивного таяния снега и дождей ожидается активизация селевых потоков в районах Дангара, Фархор, Восе, Куляб, Муминабад и Ховалинг.',
                    'tg' => 'Тибқи пешбинии Агентии гидрометеорология, аз сабаби обшавии барф ва боришот дар ноҳияҳои Данғара, Фархор, Восеъ, Кӯлоб, Муъминобод ва Ховалинг фаъолшавии селҳо интизор меравад.',
                    'en' => '',
                ],
                'instructions' => [
                    'ru' => 'Не оставляйте транспорт и имущество в руслах и поймах рек. При объявлении эвакуации следуйте указаниям местных органов КЧС. Экстренный вызов — 112.',
                    'tg' => 'Нақлиёт ва амволро дар маҷрои дарёҳо нагузоред. Ҳангоми эълони эвакуатсия ба дастури мақомоти КҲФ амал кунед. Тамоси фаврӣ — 112.',
                    'en' => '',
                ],
                'contacts' => ['ru' => 'Единая служба спасения — 112 · Телефон доверия +992 (37) 221-59-00', 'tg' => 'Хадамоти ягонаи наҷот — 112', 'en' => ''],
                'hazard_type' => HazardType::Mudflow,
                'severity' => Severity::Warning,
                'status' => ContentStatus::Published,
                'source' => 'Агентство по гидрометеорологии',
                'risk_category' => 'hydro',
                'channels' => ['site', 'sos_app', 'rss'],
                'starts_at' => '2026-07-18 09:00', 'ends_at' => '2026-07-20 18:00', 'published_at' => '2026-07-18 09:00',
                'author' => 'sh.karimov@khf.tj', 'approver' => 'f.nazarov@khf.tj',
                'regions' => ['khatlon'], 'districts' => $khatlonDistricts,
            ],
            [
                'internal_title' => 'Аномальная жара — юг республики, июль 2026',
                'title' => [
                    'ru' => 'Жара до +43 °C в южных районах',
                    'tg' => 'Гармии то +43 °C дар ноҳияҳои ҷанубӣ',
                    'en' => 'Heat up to +43 °C in southern districts',
                ],
                'summary' => [
                    'ru' => 'С 17 по 21 июля в Хатлонской области и РРП ожидается аномальная жара. Ограничьте пребывание на открытом солнце.',
                    'tg' => 'Аз 17 то 21 июл дар вилояти Хатлон ва НТҶ гармии ғайримуқаррарӣ интизор аст.',
                    'en' => 'Abnormal heat is expected 17–21 July in Khatlon and DRS. Limit sun exposure.',
                ],
                'body' => [
                    'ru' => 'Температура воздуха днём достигнет +40…+43 °C. Особое внимание — пожилым людям и детям. Пейте больше воды.',
                    'tg' => 'Ҳарорати ҳаво дар рӯз то +40…+43 °C мерасад. Ба калонсолон ва кӯдакон диққати махсус диҳед.',
                    'en' => 'Daytime temperatures will reach +40…+43 °C. Keep hydrated.',
                ],
                'instructions' => [
                    'ru' => 'Избегайте физических нагрузок в дневные часы. Не оставляйте детей в закрытых автомобилях. Экстренный вызов — 112.',
                    'tg' => 'Аз бори ҷисмонӣ дар рӯз худдорӣ кунед. Кӯдаконро дар мошинҳои пӯшида нагузоред.',
                    'en' => 'Avoid physical activity during the day. Never leave children in closed cars.',
                ],
                'contacts' => ['ru' => 'Единая служба спасения — 112', 'tg' => '112', 'en' => '112'],
                'hazard_type' => HazardType::Heat,
                'severity' => Severity::Info,
                'status' => ContentStatus::Published,
                'source' => 'Агентство по гидрометеорологии',
                'risk_category' => 'meteo',
                'channels' => ['site', 'sos_app'],
                'starts_at' => '2026-07-17 16:00', 'ends_at' => '2026-07-21 20:00', 'published_at' => '2026-07-17 16:00',
                'author' => 'm.rahimova@khf.tj', 'approver' => 'f.nazarov@khf.tj',
                'regions' => ['khatlon', 'rrp'], 'districts' => [],
            ],
            [
                'internal_title' => 'Подъём воды — река Вахш, участок Норак — Сарбанд',
                'title' => [
                    'ru' => 'Повышение уровня воды на реке Вахш',
                    'tg' => 'Баландшавии сатҳи об дар дарёи Вахш',
                    'en' => '',
                ],
                'summary' => [
                    'ru' => 'Ожидается подъём уровня воды на 0,8–1,2 м на участке Норак — Сарбанд. Соблюдайте осторожность у береговой линии.',
                    'tg' => 'Дар қитъаи Норак — Сарбанд баландшавии сатҳи об то 0,8–1,2 м интизор аст.',
                    'en' => '',
                ],
                'body' => [
                    'ru' => 'В связи с плановыми сбросами и притоком талых вод возможен подъём уровня воды. Оперативная служба ведёт мониторинг.',
                    'tg' => 'Дар робита ба партовҳои нақшавӣ ва оби барф баландшавии сатҳи об имконпазир аст.',
                    'en' => '',
                ],
                'instructions' => [
                    'ru' => 'Не приближайтесь к береговой линии и не купайтесь в реке. Следуйте указаниям дежурных служб. Экстренный вызов — 112.',
                    'tg' => 'Ба соҳил наздик нашавед ва дар дарё оббозӣ накунед.',
                    'en' => '',
                ],
                'contacts' => ['ru' => 'Дежурный по Хатлонской области — +992 (3222) 2-14-77', 'tg' => '', 'en' => ''],
                'hazard_type' => HazardType::Flood,
                'severity' => Severity::Attention,
                'status' => ContentStatus::Review,
                'source' => 'Оперативная служба КЧС',
                'risk_category' => 'hydro',
                'channels' => ['site', 'sos_app'],
                'starts_at' => '2026-07-18 15:00', 'ends_at' => '2026-07-24 12:00', 'published_at' => null,
                'author' => 'm.rahimova@khf.tj', 'approver' => 'f.nazarov@khf.tj',
                'regions' => ['khatlon'], 'districts' => [],
            ],
            [
                'internal_title' => 'Лавинная опасность — перевал Анзоб',
                'title' => [
                    'ru' => 'Лавинная опасность на перевале Анзоб',
                    'tg' => 'Хатари тарма дар ағбаи Анзоб',
                    'en' => 'Avalanche risk at the Anzob pass',
                ],
                'summary' => [
                    'ru' => 'В период с 19 по 26 июля сохраняется лавинная опасность на перевале Анзоб. Планируйте маршруты заранее.',
                    'tg' => 'Аз 19 то 26 июл дар ағбаи Анзоб хатари тарма боқӣ мемонад.',
                    'en' => 'Avalanche risk remains at the Anzob pass 19–26 July.',
                ],
                'body' => [
                    'ru' => 'Дорожные и спасательные службы приведены в готовность. При ухудшении погоды движение может быть ограничено.',
                    'tg' => 'Хадамоти роҳ ва наҷот ба омодагӣ оварда шудаанд.',
                    'en' => 'Road and rescue services are on standby.',
                ],
                'instructions' => [
                    'ru' => 'Уточняйте состояние дороги перед выездом. Имейте запас топлива и тёплых вещей. Экстренный вызов — 112.',
                    'tg' => 'Пеш аз ҳаракат ҳолати роҳро аниқ кунед.',
                    'en' => 'Check road conditions before travel.',
                ],
                'contacts' => ['ru' => '112', 'tg' => '112', 'en' => '112'],
                'hazard_type' => HazardType::Avalanche,
                'severity' => Severity::Warning,
                'status' => ContentStatus::Scheduled,
                'source' => 'Региональное управление',
                'risk_category' => 'geo',
                'channels' => ['site', 'sos_app', 'rss'],
                'starts_at' => '2026-07-19 06:00', 'ends_at' => '2026-07-26 12:00', 'published_at' => null,
                'scheduled_at' => '2026-07-19 06:00',
                'author' => 'd.sattorov@khf.tj', 'approver' => 'f.nazarov@khf.tj',
                'regions' => ['rrp'], 'districts' => [],
            ],
            [
                'internal_title' => 'Сейсмическая активность — Раштская долина',
                'title' => [
                    'ru' => 'Сейсмическая активность в Раштской долине',
                    'tg' => 'Фаъолнокии сейсмикӣ дар водии Рашт',
                    'en' => '',
                ],
                'summary' => [
                    'ru' => 'Зафиксирована серия слабых подземных толчков. Угрозы разрушений нет, ведётся наблюдение.',
                    'tg' => '',
                    'en' => '',
                ],
                'body' => ['ru' => 'Магнитуда толчков не превышает 3,5. Специалисты продолжают мониторинг сейсмической обстановки.', 'tg' => '', 'en' => ''],
                'instructions' => ['ru' => 'Проверьте крепление тяжёлых предметов в помещениях. Экстренный вызов — 112.', 'tg' => '', 'en' => ''],
                'contacts' => ['ru' => '112', 'tg' => '', 'en' => ''],
                'hazard_type' => HazardType::Earthquake,
                'severity' => Severity::Attention,
                'status' => ContentStatus::Draft,
                'source' => 'Оперативная служба КЧС',
                'risk_category' => 'geo',
                'channels' => ['site'],
                'starts_at' => null, 'ends_at' => null, 'published_at' => null,
                'author' => 'f.nazarov@khf.tj', 'approver' => null,
                'regions' => ['rrp'], 'districts' => [],
            ],
            [
                'internal_title' => 'Паводок — река Зеравшан',
                'title' => [
                    'ru' => 'Паводок на реке Зеравшан',
                    'tg' => 'Обхезӣ дар дарёи Зарафшон',
                    'en' => 'Flooding on the Zeravshan river',
                ],
                'summary' => [
                    'ru' => 'Паводок в Согдийской области завершён. Уровень воды вернулся к норме.',
                    'tg' => 'Обхезӣ дар вилояти Суғд ба анҷом расид.',
                    'en' => 'Flooding in Sughd region has ended.',
                ],
                'body' => ['ru' => 'Оперативная обстановка стабилизировалась, ограничения сняты.', 'tg' => 'Вазъият ба эътидол омад.', 'en' => 'The situation has stabilised.'],
                'instructions' => ['ru' => 'Соблюдайте осторожность вблизи размытых участков берега.', 'tg' => 'Дар наздикии соҳил эҳтиёткор бошед.', 'en' => 'Take care near eroded banks.'],
                'contacts' => ['ru' => '112', 'tg' => '112', 'en' => '112'],
                'hazard_type' => HazardType::Flood,
                'severity' => Severity::Info,
                'status' => ContentStatus::Completed,
                'source' => 'Оперативная служба КЧС',
                'risk_category' => 'hydro',
                'channels' => ['site', 'rss'],
                'starts_at' => '2026-07-10 08:00', 'ends_at' => '2026-07-14 09:00', 'published_at' => '2026-07-10 08:00',
                'author' => 'z.nazarova@khf.tj', 'approver' => 'f.nazarov@khf.tj',
                'regions' => ['sughd'], 'districts' => [],
            ],
        ];

        foreach ($alerts as $data) {
            $regionCodes = $data['regions'];
            $districtIds = $data['districts'];
            $authorEmail = $data['author'];
            $approverEmail = $data['approver'];
            unset($data['regions'], $data['districts'], $data['author'], $data['approver']);

            $data['author_id'] = $users[$authorEmail] ?? null;
            $data['approver_id'] = $approverEmail ? ($users[$approverEmail] ?? null) : null;

            foreach (['starts_at', 'ends_at', 'published_at', 'scheduled_at'] as $dateField) {
                if (! empty($data[$dateField])) {
                    $data[$dateField] = Carbon::parse($data[$dateField]);
                }
            }

            $alert = Alert::updateOrCreate(['internal_title' => $data['internal_title']], $data);

            $regionIds = array_values(array_filter(array_map(fn ($c) => $regions[$c] ?? null, $regionCodes)));
            $alert->regions()->sync($regionIds);
            $alert->districts()->sync($districtIds);

            $this->seedTransitions($alert);
        }
    }

    private function seedTransitions(Alert $alert): void
    {
        if ($alert->transitions()->exists()) {
            return;
        }

        $trail = match ($alert->status) {
            ContentStatus::Published, ContentStatus::Completed => [
                ['from' => null, 'to' => 'draft'],
                ['from' => 'draft', 'to' => 'review'],
                ['from' => 'review', 'to' => 'approved'],
                ['from' => 'approved', 'to' => 'published'],
            ],
            ContentStatus::Review => [
                ['from' => null, 'to' => 'draft'],
                ['from' => 'draft', 'to' => 'review'],
            ],
            ContentStatus::Scheduled => [
                ['from' => null, 'to' => 'draft'],
                ['from' => 'draft', 'to' => 'approved'],
                ['from' => 'approved', 'to' => 'scheduled'],
            ],
            default => [['from' => null, 'to' => 'draft']],
        };

        if ($alert->status === ContentStatus::Completed) {
            $trail[] = ['from' => 'published', 'to' => 'completed'];
        }

        foreach ($trail as $step) {
            WorkflowTransition::query()->create([
                'subject_type' => $alert->getMorphClass(),
                'subject_id' => $alert->id,
                'from_status' => $step['from'],
                'to_status' => $step['to'],
                'user_id' => $alert->author_id,
                'comment' => null,
            ]);
        }
    }
}
