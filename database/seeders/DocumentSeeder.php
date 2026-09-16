<?php

namespace Database\Seeders;

use App\Enums\ContentStatus;
use App\Enums\DocType;
use App\Models\Document;
use App\Models\User;
use Database\Seeders\Concerns\SeedsFromSource;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Seeds the document library from the Committee's published legal corpus
 * (kchs.tj/node/1932 and /node/1941, khf.tj/node/3073 and /node/3074), plus a
 * handful of internal documents that give the editorial workflow something to
 * show in every status.
 *
 * Where the source publishes a PDF, the real file is downloaded on first run
 * and cached; otherwise a small placeholder PDF keeps the download link
 * working. Government resolutions carry the date and number exactly as
 * published; the laws index publishes titles only, so those stay undated
 * rather than inventing a citation.
 */
class DocumentSeeder extends Seeder
{
    use SeedsFromSource;

    private const FILES = 'https://kchs.tj/sites/default/files';

    public function run(): void
    {
        /** @var array<string, int> $users */
        $users = User::query()->pluck('id', 'email')->all();

        $documents = [...$this->laws(), ...$this->resolutions(), ...$this->internalDocuments()];

        foreach ($documents as $document) {
            $this->seedDocument($document, $users);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, int>  $users
     */
    private function seedDocument(array $data, array $users): void
    {
        /** @var ContentStatus $status */
        $status = $data['status'];
        /** @var string|null $date */
        $date = $data['date'];
        $published = $status === ContentStatus::Published;

        $attributes = [
            'name' => ['ru' => $data['ru'], 'tg' => $data['tg'], 'en' => $data['en'] ?? ''],
            'doc_type' => $data['type'],
            'number' => $data['number'],
            'doc_date' => $date === null ? null : Carbon::parse($date),
            'section' => $data['section'],
            'status' => $status,
            'published_at' => $published ? ($date === null ? now() : Carbon::parse($date)) : null,
            'author_id' => $users[$data['author']] ?? null,
        ];

        $document = Document::query()->where('name->ru', $data['ru'])->first();

        if ($document instanceof Document) {
            $document->update($attributes);
        } else {
            $document = Document::create($attributes);
        }

        if (! $published || ($data['noFile'] ?? false) === true) {
            return;
        }

        $this->attachFiles($document, $data['file'] ?? null, $data['locales'] ?? ['tg', 'ru']);
    }

    /**
     * Attaches the published PDF per language. The source library keeps one
     * bilingual file per act, so the same document is registered for every
     * language the CMS offers it in; a placeholder stands in when the source
     * has no file or the network is unavailable.
     *
     * @param  array<int, string>  $locales
     */
    private function attachFiles(Document $document, ?string $file, array $locales): void
    {
        $path = $file === null ? null : $this->sourceFilePath($file);

        foreach ($locales as $locale) {
            $collection = "file_{$locale}";

            if ($document->hasMedia($collection)) {
                continue;
            }

            if ($path === null) {
                $document->addMediaFromString($this->placeholderPdf())
                    ->usingFileName("document-{$locale}.pdf")
                    ->toMediaCollection($collection);

                continue;
            }

            $document->addMedia($path)
                ->preservingOriginal()
                ->usingFileName($this->sourceFileName((string) $file))
                ->toMediaCollection($collection);
        }
    }

    /**
     * Laws of the Republic of Tajikistan that govern the Committee's mandate.
     * The four with a published PDF come first.
     *
     * @return array<int, array<string, mixed>>
     */
    private function laws(): array
    {
        $laws = [
            [
                'ru' => 'Закон Республики Таджикистан «О гражданской обороне»',
                'tg' => 'Қонуни Ҷумҳурии Тоҷикистон «Дар бораи мудофиаи гражданӣ»',
                'en' => 'Law of the Republic of Tajikistan on Civil Defence',
                'file' => self::FILES.'/pdf/zakon/go.pdf',
            ],
            [
                'ru' => 'Закон Республики Таджикистан «О защите населения и территорий от чрезвычайных ситуаций природного и техногенного характера»',
                'tg' => 'Қонуни Ҷумҳурии Тоҷикистон «Дар бораи ҳифзи аҳолӣ ва ҳудуд аз ҳолатҳои фавқулоддаи дорои хусусияти табиӣ ва техногенӣ»',
                'en' => 'Law on the Protection of the Population and Territories from Natural and Technological Emergencies',
                'file' => self::FILES.'/pdf/zakon/zashita-nasileniya.pdf',
            ],
            [
                'ru' => 'Закон Республики Таджикистан «Об аварийно-спасательных службах, аварийно-спасательных формированиях и статусе спасателей»',
                'tg' => 'Қонуни Ҷумҳурии Тоҷикистон «Дар бораи хадамоти садамавию наҷотдиҳӣ, сохторҳои садамавию наҷотдиҳӣ ва вазъи ҳуқуқии наҷотдиҳандагон»',
                'en' => 'Law on Emergency Rescue Services, Rescue Formations and the Status of Rescuers',
                'file' => self::FILES.'/pdf/zakon/spasateli.pdf',
            ],
            [
                'ru' => 'Закон Республики Таджикистан «О государственном материальном резерве»',
                'tg' => 'Қонуни Ҷумҳурии Тоҷикистон «Дар бораи захираи моддии давлатӣ»',
                'en' => 'Law on the State Material Reserve',
                'file' => self::FILES.'/pdf/zakon/rezervi.pdf',
            ],
            [
                'ru' => 'Закон Республики Таджикистан «О воинской обязанности и военной службе»',
                'tg' => 'Қонуни Ҷумҳурии Тоҷикистон «Дар бораи ӯҳдадории ҳарбӣ ва хизмати ҳарбӣ»',
                'en' => 'Law on Military Duty and Military Service',
                'file' => null,
            ],
            [
                'ru' => 'Закон Республики Таджикистан «О статусе военнослужащих»',
                'tg' => 'Қонуни Ҷумҳурии Тоҷикистон «Дар бораи вазъи ҳуқуқии хизматчиёни ҳарбӣ»',
                'en' => 'Law on the Status of Military Personnel',
                'file' => null,
            ],
            [
                'ru' => 'Закон Республики Таджикистан «О Вооружённых Силах Республики Таджикистан»',
                'tg' => 'Қонуни Ҷумҳурии Тоҷикистон «Дар бораи Қувваҳои Мусаллаҳи Ҷумҳурии Тоҷикистон»',
                'en' => 'Law on the Armed Forces of the Republic of Tajikistan',
                'file' => null,
            ],
            [
                'ru' => 'Закон Республики Таджикистан «О противодействии терроризму»',
                'tg' => 'Қонуни Ҷумҳурии Тоҷикистон «Дар бораи муқовимат ба терроризм»',
                'en' => 'Law on Countering Terrorism',
                'file' => null,
            ],
            [
                'ru' => 'Закон Республики Таджикистан «О противодействии коррупции»',
                'tg' => 'Қонуни Ҷумҳурии Тоҷикистон «Дар бораи муқовимат ба коррупсия»',
                'en' => 'Law on Countering Corruption',
                'file' => null,
            ],
            [
                'ru' => 'Закон Республики Таджикистан «Об обращениях физических и юридических лиц»',
                'tg' => 'Қонуни Ҷумҳурии Тоҷикистон «Дар бораи муроҷиатҳои шахсони воқеӣ ва ҳуқуқӣ»',
                'en' => 'Law on Appeals of Individuals and Legal Entities',
                'file' => null,
            ],
            [
                'ru' => 'Закон Республики Таджикистан «О государственном языке»',
                'tg' => 'Қонуни Ҷумҳурии Тоҷикистон «Дар бораи забони давлатӣ»',
                'en' => 'Law on the State Language',
                'file' => null,
            ],
            [
                'ru' => 'Закон Республики Таджикистан «О государственных наградах»',
                'tg' => 'Қонуни Ҷумҳурии Тоҷикистон «Дар бораи Мукофотҳои давлатӣ»',
                'en' => 'Law on State Awards',
                'file' => null,
            ],
        ];

        return array_map(
            fn (array $law): array => $law + [
                'type' => DocType::Law,
                'number' => null,
                'date' => null,
                'section' => 'Законы',
                'status' => ContentStatus::Published,
                'author' => 'f.nazarov@khf.tj',
                'locales' => ['tg', 'ru'],
            ],
            $laws,
        );
    }

    /**
     * Government resolutions and national programmes, with the date and number
     * exactly as published in the Committee's index of sub-statutory acts.
     *
     * @return array<int, array<string, mixed>>
     */
    private function resolutions(): array
    {
        $resolutions = [
            [
                'ru' => 'Постановление Правительства РТ «Об утверждении Положения служб гражданской обороны»',
                'tg' => 'Қарори Ҳукумати ҶТ «Оид ба тасдиқи Низомномаи хадамоти мудофиаи гражданӣ»',
                'number' => '№ 229',
                'date' => '2006-06-03',
                'file' => self::FILES.'/229.pdf',
            ],
            [
                'ru' => 'Постановление Правительства РТ «Правила создания и использования резервов материальных ресурсов для ликвидации чрезвычайных ситуаций природного и техногенного характера»',
                'tg' => 'Қарори Ҳукумати ҶТ «Қоидаҳои таъсис ва истифодаи захираҳои моддӣ барои бартараф кардани ҳолатҳои фавқулоддаи табиӣ ва техногенӣ»',
                'number' => '№ 778',
                'date' => '2006-12-29',
                'file' => self::FILES.'/pdf/podzakon/778.pdf',
            ],
            [
                'ru' => 'Постановление Правительства РТ «Об утверждении Правил порядка финансирования мероприятий по ликвидации последствий чрезвычайных ситуаций»',
                'tg' => 'Қарори Ҳукумати ҶТ «Қоидаҳои тартиби маблағгузории чорабиниҳои бартарафсозии оқибатҳои ҳолатҳои фавқулодда»',
                'number' => '№ 699',
                'date' => '2009-12-30',
                'file' => self::FILES.'/699.pdf',
            ],
            [
                'ru' => 'Постановление Правительства РТ «О порядке и размере предоставления единовременной материальной помощи населению, пострадавшему в результате стихийных бедствий»',
                'tg' => 'Қарори Ҳукумати ҶТ «Дар бораи тартиб ва андозаи кумаки якдафъаинаи моддӣ ба аҳолии аз офатҳои табиӣ зарардида»',
                'number' => '№ 632',
                'date' => '2010-12-03',
                'file' => self::FILES.'/pdf/podzakon/632.pdf',
            ],
            [
                'ru' => 'Постановление Правительства РТ «Порядок материального обеспечения военизированных горноспасательных частей и подразделений Комитета»',
                'tg' => 'Қарори Ҳукумати ҶТ «Тартиби таъминоти моддии қисмҳо ва сохторҳои ҳарбикунонидашудаи наҷотдиҳии кӯҳии Кумита»',
                'number' => '№ 555',
                'date' => '2011-11-02',
                'file' => self::FILES.'/pdf/podzakon/555.pdf',
            ],
            [
                'ru' => 'Постановление Правительства РТ «Об образовании Национальной платформы Республики Таджикистан по снижению риска стихийных бедствий»',
                'tg' => 'Қарори Ҳукумати ҶТ «Дар бораи таъсиси Платформаи миллии Ҷумҳурии Тоҷикистон оид ба паст кардани хавфи офатҳои табиӣ»',
                'number' => '№ 98',
                'date' => '2012-03-01',
                'file' => self::FILES.'/pdf/podzakon/98.pdf',
            ],
            [
                'ru' => 'Постановление Правительства РТ «О структуре и порядке функционирования Единой государственной системы Республики Таджикистан по предупреждению и ликвидации чрезвычайных ситуаций»',
                'tg' => 'Қарори Ҳукумати ҶТ «Дар бораи сохтор ва тартиби фаъолияти Низоми ягонаи давлатии Ҷумҳурии Тоҷикистон оид ба пешгирӣ ва бартарафсозии ҳолатҳои фавқулодда»',
                'number' => '№ 833',
                'date' => '2014-12-31',
                'file' => self::FILES.'/pdf/podzakon/833.pdf',
            ],
            [
                'ru' => 'Постановление Правительства РТ «О вопросах Государственной комиссии Правительства Республики Таджикистан по чрезвычайным ситуациям»',
                'tg' => 'Қарори Ҳукумати ҶТ «Дар бораи масъалаҳои Комиссияи давлатии Ҳукумати Ҷумҳурии Тоҷикистон оид ба ҳолатҳои фавқулодда»',
                'number' => '№ 799',
                'date' => '2015-12-30',
                'file' => self::FILES.'/pdf/podzakon/799.pdf',
            ],
            [
                'ru' => 'Постановление Правительства РТ «Об установлении оперативного краткого единого номера связи „112“ в качестве номера вызова экстренной службы»',
                'tg' => 'Қарори Ҳукумати ҶТ «Дар бораи муқаррар намудани рақами кӯтоҳи ягонаи фаврии алоқаи „112“ ҳамчун рақами даъвати хадамоти таъҷилӣ»',
                'number' => '№ 214',
                'date' => '2017-04-28',
                'file' => self::FILES.'/pdf/podzakon/214.pdf',
            ],
            [
                'ru' => 'Постановление Правительства РТ «Положение о Комитете по чрезвычайным ситуациям и гражданской обороне при Правительстве Республики Таджикистан»',
                'tg' => 'Қарори Ҳукумати ҶТ «Низомнома дар бораи Кумитаи ҳолатҳои фавқулодда ва мудофиаи граждании назди Ҳукумати Ҷумҳурии Тоҷикистон»',
                'number' => '№ 547',
                'date' => '2017-11-29',
                'file' => self::FILES.'/%D0%9F%D0%BE%D1%81%D1%82%D0%B0%D0%BD%D0%BE%D0%B2%D0%BB%D0%B5%D0%BD%D0%B8%D0%B5%20547.pdf',
            ],
        ];

        $resolutions = array_map(
            fn (array $resolution): array => $resolution + [
                'en' => '',
                'type' => DocType::Resolution,
                'section' => 'Постановления Правительства',
                'status' => ContentStatus::Published,
                'author' => 'f.nazarov@khf.tj',
                'locales' => ['tg', 'ru'],
            ],
            $resolutions,
        );

        $strategies = [
            [
                'ru' => 'Национальная стратегия Республики Таджикистан по снижению риска стихийных бедствий на 2019–2034 годы',
                'tg' => 'Стратегияи миллии Ҷумҳурии Тоҷикистон оид ба паст кардани хавфи офатҳои табиӣ барои солҳои 2019–2034',
                'en' => 'National Disaster Risk Reduction Strategy of the Republic of Tajikistan for 2019–2034',
                'number' => '№ 602',
                'date' => '2018-12-29',
                'file' => self::FILES.'/National_DRR_Strategy_2019-2034.pdf',
                'locales' => ['tg', 'ru', 'en'],
            ],
            [
                'ru' => 'Среднесрочная государственная программа по защите населения и территорий от чрезвычайных ситуаций на 2023–2028 годы',
                'tg' => 'Барномаи давлатии миёнамуҳлати ҳифзи аҳолӣ ва ҳудуд аз ҳолатҳои фавқулодда барои солҳои 2023–2028',
                'en' => 'Mid-term State Programme for the Protection of the Population and Territories from Emergencies for 2023–2028',
                'number' => '№ 630',
                'date' => '2022-12-29',
                'file' => self::FILES.'/Mid-term%20State%20Programme%20%28taj-eng-rus%29_Optimized.pdf',
                'locales' => ['tg', 'ru', 'en'],
            ],
        ];

        $strategies = array_map(
            fn (array $strategy): array => $strategy + [
                'type' => DocType::Plan,
                'section' => 'Стратегии и программы',
                'status' => ContentStatus::Published,
                'author' => 'f.nazarov@khf.tj',
            ],
            $strategies,
        );

        return [...$resolutions, ...$strategies];
    }

    /**
     * Internal paperwork. These carry no public counterpart — they exist so the
     * editorial workflow has documents in review, draft and archived states.
     *
     * @return array<int, array<string, mixed>>
     */
    private function internalDocuments(): array
    {
        return [
            [
                'ru' => 'Отчёт о деятельности Комитета за первое полугодие 2026 года',
                'tg' => 'Ҳисобот дар бораи фаъолияти Кумита барои нимсолаи аввали соли 2026',
                'en' => '',
                'type' => DocType::Report,
                'number' => null,
                'date' => '2026-07-17',
                'section' => 'Отчёты',
                'status' => ContentStatus::Review,
                'author' => 'z.nazarova@khf.tj',
                'file' => null,
            ],
            [
                'ru' => 'План эвакуационных мероприятий по Хатлонской области',
                'tg' => 'Нақшаи чорабиниҳои эвакуатсионӣ оид ба вилояти Хатлон',
                'en' => '',
                'type' => DocType::Plan,
                'number' => '№ 87-ДСП',
                'date' => '2026-07-05',
                'section' => 'Планы',
                'status' => ContentStatus::Published,
                'author' => 'sh.karimov@khf.tj',
                'file' => null,
            ],
            [
                'ru' => 'Приказ о проведении месячника гражданской обороны',
                'tg' => 'Фармон дар бораи гузаронидани моҳномаи мудофиаи гражданӣ',
                'en' => '',
                'type' => DocType::Order,
                'number' => '№ 156',
                'date' => '2026-07-15',
                'section' => 'Приказы',
                'status' => ContentStatus::Draft,
                'author' => 'f.nazarov@khf.tj',
                'file' => null,
                'noFile' => true,
            ],
            [
                'ru' => 'Форма заявки на обучение по программе гражданской обороны для организаций',
                'tg' => 'Шакли ариза барои омӯзиш аз рӯи барномаи мудофиаи гражданӣ',
                'en' => '',
                'type' => DocType::Form,
                'number' => null,
                'date' => '2026-07-01',
                'section' => 'Формы',
                'status' => ContentStatus::Published,
                'author' => 'a.usmonov@khf.tj',
                'file' => null,
            ],
            [
                'ru' => 'Открытые данные: чрезвычайные ситуации в Республике Таджикистан, 2025 год',
                'tg' => 'Маълумоти кушод: ҳолатҳои фавқулодда дар Ҷумҳурии Тоҷикистон, соли 2025',
                'en' => 'Open data: emergencies in the Republic of Tajikistan, 2025',
                'type' => DocType::OpenData,
                'number' => null,
                'date' => '2026-02-20',
                'section' => 'Открытые данные',
                'status' => ContentStatus::Published,
                'author' => 'a.usmonov@khf.tj',
                'file' => null,
                'locales' => ['tg', 'ru', 'en'],
            ],
            [
                'ru' => 'Нормы обеспечения средствами индивидуальной защиты',
                'tg' => 'Меъёрҳои таъмин бо воситаҳои ҳифзи инфиродӣ',
                'en' => '',
                'type' => DocType::Norm,
                'number' => '№ 44-н',
                'date' => '2025-09-12',
                'section' => 'Нормативы',
                'status' => ContentStatus::Published,
                'author' => 'sh.karimov@khf.tj',
                'file' => null,
            ],
        ];
    }

    /**
     * A minimal, valid, single blank A4 page PDF, used where the source
     * publishes no file of its own.
     */
    private function placeholderPdf(): string
    {
        return "%PDF-1.4\n"
            ."1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n"
            ."2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n"
            ."3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 595 842]>>endobj\n"
            ."trailer<</Root 1 0 R>>\n"
            .'%%EOF';
    }
}
