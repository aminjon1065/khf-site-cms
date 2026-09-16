<?php

namespace Database\Seeders;

use App\Models\StructureUnit;
use Illuminate\Database\Seeder;

/**
 * C-1b: seeds the structure roster from the Committee's published structure
 * page (kchs.tj/node/1230, khf.tj/node/1750): the real directorates, centres
 * and services, each with the head officer and office phone where the source
 * publishes one.
 *
 * `desc` carries a one-line remit followed by the head officer, which is what
 * the public structure page renders under the unit name.
 */
class StructureUnitSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->units() as $sort => $unit) {
            $head = $unit['head'];
            unset($unit['head']);

            $unit['desc'] = $this->describe($unit['desc'], $head);

            StructureUnit::query()->updateOrCreate(
                ['num' => $unit['num']],
                $unit + ['sort' => $sort],
            );
        }
    }

    /**
     * Appends the head officer to the unit's remit, per locale. A vacant post
     * simply leaves the remit alone rather than printing an empty label.
     *
     * @param  array<string, string>  $desc
     * @param  array<string, string>|null  $head
     * @return array<string, string>
     */
    private function describe(array $desc, ?array $head): array
    {
        if ($head === null) {
            return $desc;
        }

        foreach ($desc as $locale => $text) {
            $desc[$locale] = trim($text.' · '.($head[$locale] ?? ''), ' ·');
        }

        return $desc;
    }

    /**
     * @return array<int, array{
     *     num: string,
     *     name: array<string, string>,
     *     desc: array<string, string>,
     *     head: array<string, string>|null,
     * }>
     */
    private function units(): array
    {
        return [
            [
                'num' => '01',
                'name' => [
                    'ru' => 'Аппарат Председателя Комитета',
                    'tg' => 'Дастгоҳи Раиси Кумита',
                    'en' => 'Office of the Chairman',
                ],
                'desc' => [
                    'ru' => 'Организация работы руководства, документооборот, контроль исполнения поручений',
                    'tg' => 'Ташкили кори роҳбарият, гардиши ҳуҷҷатҳо, назорати иҷрои супоришҳо',
                    'en' => 'Support for the leadership, records management and follow-up on assignments',
                ],
                'head' => $this->head('Давлатзода Абдусамад Хаким', 'Давлатзода Абдусамад Ҳаким', 'Davlatzoda Abdusamad Hakim', 'полковник', '(+992 37) 227-88-44'),
            ],
            [
                'num' => '02',
                'name' => [
                    'ru' => 'Главное управление гражданской обороны',
                    'tg' => 'Сарраёсати мудофиаи гражданӣ',
                    'en' => 'Main Civil Defence Directorate',
                ],
                'desc' => [
                    'ru' => 'Планы гражданской обороны, эвакуационные мероприятия, защитные сооружения, подготовка населения',
                    'tg' => 'Нақшаҳои мудофиаи гражданӣ, чорабиниҳои эвакуатсионӣ, иншооти муҳофизатӣ, омодасозии аҳолӣ',
                    'en' => 'Civil defence planning, evacuation measures, protective facilities and public preparedness',
                ],
                'head' => $this->head('Вализода Хуршед Вали', 'Вализода Хуршед Валӣ', 'Valizoda Khurshed Vali', 'полковник', '(+992 37) 221-77-17'),
            ],
            [
                'num' => '03',
                'name' => [
                    'ru' => 'Главное управление защиты населения и территорий',
                    'tg' => 'Сарраёсати ҳифзи аҳолӣ ва ҳудуд',
                    'en' => 'Main Directorate for the Protection of Population and Territories',
                ],
                'desc' => [
                    'ru' => 'Предупреждение чрезвычайных ситуаций, оценка рисков, надзор за соблюдением требований безопасности',
                    'tg' => 'Пешгирии ҳолатҳои фавқулода, арзёбии хатарҳо, назорати риояи талаботи бехатарӣ',
                    'en' => 'Emergency prevention, risk assessment and supervision of safety requirements',
                ],
                'head' => $this->head('Иброхимзода Мухаббат Мирзовали', 'Иброҳимзода Муҳаббат Саидвалӣ', 'Ibrohimzoda Muhabbat Mirzovali', 'генерал-майор', null),
            ],
            [
                'num' => '04',
                'name' => [
                    'ru' => 'Управление войск',
                    'tg' => 'Раёсати қӯшунҳо',
                    'en' => 'Troops Directorate',
                ],
                'desc' => [
                    'ru' => 'Войска гражданской обороны, боевая подготовка, служба личного состава',
                    'tg' => 'Қӯшунҳои мудофиаи гражданӣ, омодагии ҷангӣ, хидмати ҳайати шахсӣ',
                    'en' => 'Civil defence troops, combat training and service of personnel',
                ],
                'head' => $this->head('Давлатзода Саиджон Давлат', 'Давлатзода Саидҷон Давлат', 'Davlatzoda Saidjon Davlat', 'полковник', '(+992 37) 227-90-82'),
            ],
            [
                'num' => '05',
                'name' => [
                    'ru' => 'Управление строительства, эксплуатации зданий и сооружений',
                    'tg' => 'Раёсати сохтмон, истифодабарии биноҳо ва иншоот',
                    'en' => 'Directorate of Construction and Building Maintenance',
                ],
                'desc' => [
                    'ru' => 'Капитальное строительство, содержание зданий и защитных сооружений Комитета',
                    'tg' => 'Сохтмони асосӣ, нигоҳдории биноҳо ва иншооти муҳофизатии Кумита',
                    'en' => 'Capital construction and upkeep of the Committee’s buildings and protective facilities',
                ],
                'head' => null,
            ],
            [
                'num' => '06',
                'name' => [
                    'ru' => 'Управление кадров и по работе с личным составом',
                    'tg' => 'Раёсати кадрҳо ва кор бо ҳайати шахсӣ',
                    'en' => 'Human Resources Directorate',
                ],
                'desc' => [
                    'ru' => 'Комплектование, аттестация, социальная защита сотрудников и спасателей',
                    'tg' => 'Ҷобаҷогузорӣ, аттестатсия, ҳифзи иҷтимоии кормандон ва наҷотдиҳандагон',
                    'en' => 'Staffing, certification and social protection of officers and rescuers',
                ],
                'head' => $this->head('Шокирзода Бахром', 'Шокирзода Баҳром', 'Shokirzoda Bahrom', 'полковник', '(+992 37) 227-28-36'),
            ],
            [
                'num' => '07',
                'name' => [
                    'ru' => 'Управление международного сотрудничества',
                    'tg' => 'Раёсати ҳамкориҳои байналмилалӣ',
                    'en' => 'International Cooperation Directorate',
                ],
                'desc' => [
                    'ru' => 'Программы с УСРБ ООН, ИНСАРАГ и партнёрами по региону, приём гуманитарной помощи',
                    'tg' => 'Барномаҳо бо СММ оид ба БОХ, ИНСАРАГ ва шарикони минтақа, қабули кумаки башардӯстона',
                    'en' => 'Programmes with UNDRR, INSARAG and regional partners; humanitarian aid intake',
                ],
                'head' => $this->head('Саидиён Насриддин Табари', 'Саидиён Насриддин Табарӣ', 'Saidiyon Nasriddin Tabari', 'полковник', '(+992) 93 880-80-02'),
            ],
            [
                'num' => '08',
                'name' => [
                    'ru' => 'Финансово-экономическое управление',
                    'tg' => 'Раёсати молия ва иқтисод',
                    'en' => 'Finance and Economics Directorate',
                ],
                'desc' => [
                    'ru' => 'Бюджетное планирование, финансирование мероприятий по ликвидации последствий ЧС',
                    'tg' => 'Банақшагирии буҷет, маблағгузории чорабиниҳои бартарафсозии оқибатҳои ҲФ',
                    'en' => 'Budget planning and funding of emergency-response measures',
                ],
                'head' => null,
            ],
            [
                'num' => '09',
                'name' => [
                    'ru' => 'Медицинское управление',
                    'tg' => 'Раёсати тиббӣ',
                    'en' => 'Medical Directorate',
                ],
                'desc' => [
                    'ru' => 'Медицинское обеспечение спасательных операций, санитарно-эпидемиологический контроль',
                    'tg' => 'Таъминоти тиббии амалиёти наҷот, назорати санитарию эпидемиологӣ',
                    'en' => 'Medical support for rescue operations and sanitary-epidemiological control',
                ],
                'head' => $this->head('Шарипов Саъдулло Рахматуллоевич', 'Шарипов Саъдулло Раҳматуллоевич', 'Sharipov Sadullo Rahmatulloevich', 'полковник', '(+992) 93 575-30-92'),
            ],
            [
                'num' => '10',
                'name' => [
                    'ru' => 'Центр управления в кризисных ситуациях',
                    'tg' => 'Маркази идоракунӣ дар ҳолатҳои бӯҳронӣ',
                    'en' => 'Crisis Management Centre',
                ],
                'desc' => [
                    'ru' => 'Круглосуточный мониторинг обстановки, приём вызовов 112, координация реагирования',
                    'tg' => 'Мониторинги шабонарӯзии вазъият, қабули зангҳои 112, ҳамоҳангсозии вокуниш',
                    'en' => '24/7 situation monitoring, 112 call handling and response coordination',
                ],
                'head' => $this->head('Камолзода Джамшед Джамол', 'Камолзода Ҷамшед Ҷамол', 'Kamolzoda Jamshed Jamol', 'генерал-майор', null),
            ],
            [
                'num' => '11',
                'name' => [
                    'ru' => 'Правовой отдел',
                    'tg' => 'Шуъбаи ҳуқуқӣ',
                    'en' => 'Legal Department',
                ],
                'desc' => [
                    'ru' => 'Нормотворчество, правовая экспертиза, представление интересов Комитета',
                    'tg' => 'Таҳияи санадҳои меъёрӣ, экспертизаи ҳуқуқӣ, намояндагии манфиатҳои Кумита',
                    'en' => 'Rule-making, legal review and representation of the Committee',
                ],
                'head' => null,
            ],
            [
                'num' => '12',
                'name' => [
                    'ru' => 'Отдел по работе с общественностью',
                    'tg' => 'Шуъбаи кор бо ҷомеа',
                    'en' => 'Public Relations Department',
                ],
                'desc' => [
                    'ru' => 'Информирование населения, работа со СМИ, официальные каналы Комитета',
                    'tg' => 'Иттилоъдиҳии аҳолӣ, кор бо ВАО, шабакаҳои расмии Кумита',
                    'en' => 'Public information, media relations and the Committee’s official channels',
                ],
                'head' => $this->head('Нозимиён Ориф Ислом', 'Нозимиён Ориф Ислом', 'Nozimiyon Orif Islom', 'полковник', '(+992) 20 474-37-37'),
            ],
            [
                'num' => '13',
                'name' => [
                    'ru' => 'Управление специализированных поисково-спасательных служб',
                    'tg' => 'Раёсати хадамоти махсусгардонидашудаи ҷустуҷӯию наҷотдиҳӣ',
                    'en' => 'Directorate of Specialised Search and Rescue Services',
                ],
                'desc' => [
                    'ru' => 'Аэромобильный отряд, кинологические расчёты, водолазная и горная службы',
                    'tg' => 'Гурӯҳи ҳавоӣ, гурӯҳҳои кинологӣ, хидматҳои ғаввосӣ ва кӯҳӣ',
                    'en' => 'Air-mobile unit, canine teams, diving and mountain services',
                ],
                'head' => $this->head('Курбонзода Изатулло Акмал', 'Қурбонзода Иззатулло Акмал', 'Qurbonzoda Izzatullo Akmal', 'полковник', '(+992 37) 224-67-62'),
            ],
            [
                'num' => '14',
                'name' => [
                    'ru' => 'Управление материально-технического, продовольственного и транспортного обеспечения',
                    'tg' => 'Раёсати таъминоти моддию техникӣ, озуқаворӣ ва нақлиёт',
                    'en' => 'Directorate of Logistics, Supply and Transport',
                ],
                'desc' => [
                    'ru' => 'Резервы материальных ресурсов, аварийно-спасательная техника, транспорт и склады',
                    'tg' => 'Захираҳои моддӣ, техникаи садамавию наҷотдиҳӣ, нақлиёт ва анборҳо',
                    'en' => 'Material reserves, rescue equipment, vehicles and warehouses',
                ],
                'head' => $this->head('Назарзода Зоиршо Хоркаш', 'Назарзода Зоиршо Хоркаш', 'Nazarzoda Zoirsho Khorkash', 'полковник', '(+992 37) 236-63-43'),
            ],
            [
                'num' => '15',
                'name' => [
                    'ru' => 'Управление по вопросам Сарезского озера',
                    'tg' => 'Раёсати масъалаҳои кӯли Сарез',
                    'en' => 'Sarez Lake Directorate',
                ],
                'desc' => [
                    'ru' => 'Мониторинг Усойского завала и Сарезского озера, система раннего оповещения долины Бартанга',
                    'tg' => 'Мониторинги тарма-сарбанди Усой ва кӯли Сарез, низоми огоҳсозии барвақти водии Бартанг',
                    'en' => 'Monitoring of the Usoi dam and Sarez Lake and the Bartang valley early-warning system',
                ],
                'head' => $this->head('Аюбзода Эроншо Гулаёз', 'Аюбзода Эроншо Гулаёз', 'Ayubzoda Eronsho Gulayoz', 'полковник', '(+992 37) 221-69-77'),
            ],
            [
                'num' => '16',
                'name' => [
                    'ru' => 'Управление КЧС по ГБАО',
                    'tg' => 'Раёсати КҲФ дар ВМКБ',
                    'en' => 'Regional Directorate for GBAO',
                ],
                'desc' => [
                    'ru' => 'Хорог · реагирование в высокогорных районах, лавинная и селевая опасность',
                    'tg' => 'Хоруғ · вокуниш дар ноҳияҳои баландкӯҳ, хатари тарма ва сел',
                    'en' => 'Khorog · response in high-mountain districts, avalanche and mudflow risk',
                ],
                'head' => $this->head('Мираков Наим', 'Мираков Наим', 'Mirakov Naim', 'полковник', '(+992 3522) 2-40-57'),
            ],
            [
                'num' => '17',
                'name' => [
                    'ru' => 'Управление КЧС по Согдийской области',
                    'tg' => 'Раёсати КҲФ дар вилояти Суғд',
                    'en' => 'Regional Directorate for Sughd',
                ],
                'desc' => [
                    'ru' => 'Худжанд · реагирование в 18 городах и районах области',
                    'tg' => 'Хуҷанд · вокуниш дар 18 шаҳру ноҳияи вилоят',
                    'en' => 'Khujand · response across the region’s 18 cities and districts',
                ],
                'head' => null,
            ],
            [
                'num' => '18',
                'name' => [
                    'ru' => 'Управление КЧС по Хатлонской области',
                    'tg' => 'Раёсати КҲФ дар вилояти Хатлон',
                    'en' => 'Regional Directorate for Khatlon',
                ],
                'desc' => [
                    'ru' => 'Бохтар · паводки и сели, крупнейшая группировка сил в республике',
                    'tg' => 'Бохтар · обхезӣ ва сел, бузургтарин гурӯҳбандии қувваҳо дар ҷумҳурӣ',
                    'en' => 'Bokhtar · floods and mudflows; the largest force grouping in the country',
                ],
                'head' => null,
            ],
            [
                'num' => '19',
                'name' => [
                    'ru' => 'Управление КЧС по городу Душанбе',
                    'tg' => 'Раёсати КҲФ дар шаҳри Душанбе',
                    'en' => 'Regional Directorate for Dushanbe',
                ],
                'desc' => [
                    'ru' => 'Душанбе · городские аварии, техногенные происшествия, работа с населением',
                    'tg' => 'Душанбе · садамаҳои шаҳрӣ, ҳодисаҳои техногенӣ, кор бо аҳолӣ',
                    'en' => 'Dushanbe · urban incidents, technological accidents and public outreach',
                ],
                'head' => $this->head('Шамсизода Сулаймон Шамси', 'Шамсизода Сулаймон Шамсӣ', 'Shamsizoda Sulaymon Shamsi', 'полковник', '(+992 37) 225-44-76'),
            ],
            [
                'num' => '20',
                'name' => [
                    'ru' => 'Отдел КЧС по Кулябской группе районов',
                    'tg' => 'Шуъбаи КҲФ дар гурӯҳи ноҳияҳои Кӯлоб',
                    'en' => 'Department for the Kulob Group of Districts',
                ],
                'desc' => [
                    'ru' => 'Куляб · Восе, Муминабад, Ховалинг, Фархор и соседние районы',
                    'tg' => 'Кӯлоб · Восеъ, Муъминобод, Ховалинг, Фархор ва ноҳияҳои ҳамсоя',
                    'en' => 'Kulob · Vose, Muminobod, Khovaling, Farkhor and neighbouring districts',
                ],
                'head' => $this->head('Рахмонзода Содикджон Шариф', 'Раҳмонзода Содиқҷон Шариф', 'Rahmonzoda Sodiqjon Sharif', 'полковник', '(+992 3322) 2-16-56'),
            ],
            [
                'num' => '21',
                'name' => [
                    'ru' => 'Противоградовая служба',
                    'tg' => 'Хадамоти зиддижола',
                    'en' => 'Anti-hail Service',
                ],
                'desc' => [
                    'ru' => 'Активное воздействие на градовые процессы над сельскохозяйственными угодьями',
                    'tg' => 'Таъсири фаъол ба равандҳои жола дар болои заминҳои кишоварзӣ',
                    'en' => 'Active suppression of hail over farmland',
                ],
                'head' => $this->head('Аламшозода Манучехр Аламшо', 'Аламшозода Манучеҳр Аламшо', 'Alamshozoda Manuchehr Alamsho', 'капитан', null),
            ],
            [
                'num' => '22',
                'name' => [
                    'ru' => 'Республиканский учебно-методический центр',
                    'tg' => 'Маркази ҷумҳуриявии таълимию методӣ',
                    'en' => 'National Training and Methodology Centre',
                ],
                'desc' => [
                    'ru' => 'Подготовка спасателей и обучение населения действиям при чрезвычайных ситуациях',
                    'tg' => 'Омодасозии наҷотдиҳандагон ва омӯзиши аҳолӣ барои амал ҳангоми ҳолатҳои фавқулода',
                    'en' => 'Training rescuers and teaching the public how to act in emergencies',
                ],
                'head' => $this->head('Махмадзода Тоджиддин Файзиддин', 'Маҳмадзода Тоҷиддин Файзиддин', 'Mahmadzoda Tojiddin Fayziddin', 'полковник', '(+992) 90 772-97-68'),
            ],
            [
                'num' => '23',
                'name' => [
                    'ru' => 'Республиканская химико-радиометрическая лаборатория',
                    'tg' => 'Лабораторияи ҷумҳуриявии химиявию радиометрӣ',
                    'en' => 'National Chemical and Radiometric Laboratory',
                ],
                'desc' => [
                    'ru' => 'Контроль химической и радиационной обстановки, лабораторный анализ проб',
                    'tg' => 'Назорати вазъи химиявӣ ва радиатсионӣ, таҳлили лабораторӣ',
                    'en' => 'Chemical and radiation monitoring and laboratory sample analysis',
                ],
                'head' => $this->head('Стоцкий Дмитрий Францевич', 'Стотский Дмитрий Франтсевич', 'Stotskiy Dmitriy Frantsevich', 'полковник', '(+992) 93 880-28-33'),
            ],
            [
                'num' => '24',
                'name' => [
                    'ru' => 'Военный госпиталь (войсковая часть 45010)',
                    'tg' => 'Госпитали ҳарбӣ (қисми ҳарбии 45010)',
                    'en' => 'Military Hospital (unit 45010)',
                ],
                'desc' => [
                    'ru' => 'Стационарная и амбулаторная помощь личному составу Комитета',
                    'tg' => 'Ёрии статсионарӣ ва амбулаторӣ ба ҳайати шахсии Кумита',
                    'en' => 'Inpatient and outpatient care for the Committee’s personnel',
                ],
                'head' => $this->head('Каримов Асомиддин', 'Каримов Асомиддин', 'Karimov Asomiddin', 'полковник', '(+992 37) 227-48-40'),
            ],
            [
                'num' => '25',
                'name' => [
                    'ru' => 'Военизированная горноспасательная служба, Худжанд',
                    'tg' => 'Хадамоти низомикунонидашудаи кӯҳию наҷотдиҳӣ, Хуҷанд',
                    'en' => 'Mine Rescue Service, Khujand',
                ],
                'desc' => [
                    'ru' => 'Горноспасательные работы на предприятиях севера республики',
                    'tg' => 'Корҳои кӯҳию наҷотдиҳӣ дар корхонаҳои шимоли ҷумҳурӣ',
                    'en' => 'Mine rescue operations at northern industrial sites',
                ],
                'head' => $this->head('Муродкулов Т. М.', 'Муродқулов Т. М.', 'Murodqulov T. M.', null, '(+992 3422) 5-73-62'),
            ],
            [
                'num' => '26',
                'name' => [
                    'ru' => 'Военизированная горноспасательная служба, Нурек',
                    'tg' => 'Хадамоти низомикунонидашудаи кӯҳию наҷотдиҳӣ, Норак',
                    'en' => 'Mine Rescue Service, Norak',
                ],
                'desc' => [
                    'ru' => 'Аварийно-спасательное обеспечение Нурекской ГЭС и прилегающих объектов',
                    'tg' => 'Таъминоти садамавию наҷотдиҳии НБО Норак ва объектҳои атроф',
                    'en' => 'Emergency cover for the Norak hydropower plant and nearby facilities',
                ],
                'head' => $this->head('Сатторов Шавкат', 'Сатторов Шавкат', 'Sattorov Shavkat', null, null),
            ],
            [
                'num' => '27',
                'name' => [
                    'ru' => 'Военизированная горноспасательная служба, Рогун',
                    'tg' => 'Хадамоти низомикунонидашудаи кӯҳию наҷотдиҳӣ, Роғун',
                    'en' => 'Mine Rescue Service, Rogun',
                ],
                'desc' => [
                    'ru' => 'Аварийно-спасательное обеспечение строительства Рогунской ГЭС',
                    'tg' => 'Таъминоти садамавию наҷотдиҳии сохтмони НБО Роғун',
                    'en' => 'Emergency cover for the Rogun hydropower construction site',
                ],
                'head' => $this->head('Хайруллозода Наджибулло', 'Хайруллозода Наҷибулло', 'Khayrullozoda Najibullo', null, null),
            ],
        ];
    }

    /**
     * Formats the head officer line, e.g. "Начальник — полковник Иванов И. И.
     * · (+992 37) 000-00-00". Rank and phone are optional in the source.
     *
     * @return array<string, string>
     */
    private function head(string $ru, string $tg, string $en, ?string $rank, ?string $phone): array
    {
        $ranks = [
            'полковник' => ['tg' => 'полковник', 'en' => 'Colonel'],
            'генерал-майор' => ['tg' => 'генерал-майор', 'en' => 'Major General'],
            'капитан' => ['tg' => 'капитан', 'en' => 'Captain'],
        ];

        $labels = [
            'ru' => ['Начальник', $rank, $ru],
            'tg' => ['Сардор', $rank === null ? null : ($ranks[$rank]['tg'] ?? $rank), $tg],
            'en' => ['Head', $rank === null ? null : ($ranks[$rank]['en'] ?? $rank), $en],
        ];

        $head = [];

        foreach ($labels as $locale => [$title, $officerRank, $name]) {
            $line = $title.' — '.trim(($officerRank === null ? '' : $officerRank.' ').$name);
            $head[$locale] = $phone === null ? $line : $line.' · '.$phone;
        }

        return $head;
    }
}
