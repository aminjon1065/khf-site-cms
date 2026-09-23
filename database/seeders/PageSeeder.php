<?php

namespace Database\Seeders;

use App\Enums\ContentStatus;
use App\Models\Page;
use Illuminate\Database\Seeder;

class PageSeeder extends Seeder
{
    public function run(): void
    {
        $pages = [
            $this->page(
                'about',
                ['tg' => 'Дар бораи Кумита', 'ru' => 'О Комитете', 'en' => 'About the Committee'],
                [
                    'tg' => '<p>Кумитаи ҳолатҳои фавқулода ва мудофиаи граждании назди Ҳукумати Ҷумҳурии Тоҷикистон мақоми марказии идоракунии давлатӣ дар соҳаи пешгирӣ ва рафъи ҳолатҳои фавқулода ва ҳифзи аҳоливу ҳудудҳо мебошад.</p><p>Вазифаҳои асосӣ ташкили корҳои наҷотдиҳӣ, омодагии қувваю воситаҳо, огоҳонӣ ва омӯзиши аҳолӣ мебошанд.</p>',
                    'ru' => '<p>Комитет по чрезвычайным ситуациям и гражданской обороне при Правительстве Республики Таджикистан — центральный орган государственного управления в области предупреждения и ликвидации чрезвычайных ситуаций, защиты населения и территорий.</p><p>Основные задачи — организация аварийно-спасательных работ, готовность сил и средств, информирование и обучение населения.</p>',
                    'en' => '<p>The Committee of Emergency Situations and Civil Defence under the Government of the Republic of Tajikistan is the central public authority responsible for emergency prevention and response and for protecting people and territories.</p><p>Its core duties include rescue operations, preparedness, public warning and safety education.</p>',
                ],
                ['tg' => 'Маълумоти расмӣ дар бораи Кумита ва вазифаҳои он.', 'ru' => 'Официальная информация о Комитете и его задачах.', 'en' => 'Official information about the Committee and its responsibilities.'],
            ),
            $this->page(
                'leadership',
                ['tg' => 'Роҳбарияти Кумита', 'ru' => 'Руководство Комитета', 'en' => 'Committee leadership'],
                [
                    'tg' => '<p>Дар ин саҳифа ҳайати роҳбарияти Кумита, вазифаҳо ва самтҳои масъулияти онҳо нашр карда мешаванд.</p><p>Маълумоти қабул ва роҳҳои тамос дар бахши «Тамос» дастрас аст.</p>',
                    'ru' => '<p>На этой странице публикуются актуальный состав руководства Комитета, должности и направления ответственности.</p><p>График приёма и способы связи доступны в разделе «Контакты».</p>',
                    'en' => '<p>This page contains the current Committee leadership, official positions and areas of responsibility.</p><p>Reception hours and contact channels are available in the Contacts section.</p>',
                ],
                ['tg' => 'Ҳайати роҳбарият ва самтҳои масъулият.', 'ru' => 'Состав руководства и направления ответственности.', 'en' => 'Leadership team and areas of responsibility.'],
            ),
            $this->page(
                'structure',
                ['tg' => 'Сохтори Кумита', 'ru' => 'Структура Комитета', 'en' => 'Committee structure'],
                [
                    'tg' => '<p>Аппарати марказӣ, хидматҳои махсус ва идораҳои минтақавӣ низоми ягонаи давлатии пешгирӣ ва бартарафсозии ҳолатҳои фавқулодаро ташкил медиҳанд.</p>',
                    'ru' => '<p>Центральный аппарат, специализированные службы и региональные управления образуют единую государственную систему предупреждения и ликвидации чрезвычайных ситуаций.</p>',
                    'en' => '<p>The central office, specialised services and regional offices form the unified state system for emergency prevention and response.</p>',
                ],
                ['tg' => 'Сохтори ташкилӣ ва воҳидҳои Кумита.', 'ru' => 'Организационная структура и подразделения Комитета.', 'en' => 'Organisational structure and Committee units.'],
            ),
            $this->page(
                'symbols',
                ['tg' => 'Рамзҳои давлатии Ҷумҳурии Тоҷикистон', 'ru' => 'Государственные символы Республики Таджикистан', 'en' => 'State symbols of the Republic of Tajikistan'],
                [
                    'tg' => '<p>Парчами давлатӣ, Нишони давлатӣ ва Суруди миллӣ — рамзҳои соҳибихтиёрии Ҷумҳурии Тоҷикистон мебошанд. Тартиби истифодаи онҳо бо қарорҳои Маҷлиси намояндагони Маҷлиси Олии Ҷумҳурии Тоҷикистон аз 14 марти соли 2007 № 499 ва № 500 ва Қонуни ҶТ «Дар бораи Суруди миллӣ» муқаррар шудааст.</p>',
                    'ru' => '<p>Государственный Флаг, Государственный Герб и Государственный Гимн — символы суверенитета Республики Таджикистан. Порядок их использования установлен постановлениями Маджлиси намояндагон Маджлиси Оли Республики Таджикистан от 14 марта 2007 года № 499 и № 500 и Законом РТ «О Государственном Гимне».</p>',
                    'en' => '<p>The State Flag, the State Emblem and the State Anthem are symbols of the sovereignty of the Republic of Tajikistan. The rules for their use are established by resolutions No. 499 and No. 500 of the Assembly of Representatives of the Supreme Assembly of the Republic of Tajikistan of 14 March 2007 and by the Law of the RT “On the State Anthem”.</p>',
                ],
                ['tg' => 'Парчам, Нишон ва Суруди миллии Ҷумҳурии Тоҷикистон.', 'ru' => 'Флаг, Герб и Гимн Республики Таджикистан.', 'en' => 'The flag, emblem and anthem of the Republic of Tajikistan.'],
            ),
            $this->page(
                'sos',
                ['tg' => 'Барномаи мобилии SOS', 'ru' => 'Мобильное приложение SOS', 'en' => 'SOS mobile application'],
                [
                    'tg' => '<p>Барномаи расмии SOS барои ирсоли сигнали фаврӣ бо координатҳо, гирифтани огоҳиҳои минтақавӣ ва хондани дастурҳои бехатарӣ пешбинӣ шудааст.</p><p>Дар ҳолати таҳдиди мустақим ба ҳаёт ба рақами 112 занг занед.</p>',
                    'ru' => '<p>Официальное приложение SOS позволяет отправить экстренный сигнал с координатами, получать региональные предупреждения и читать инструкции по безопасности.</p><p>При непосредственной угрозе жизни звоните 112.</p>',
                    'en' => '<p>The official SOS application can send an emergency signal with coordinates, receive regional alerts and provide offline safety guidance.</p><p>Call 112 when there is an immediate threat to life.</p>',
                ],
                ['tg' => 'Барномаи расмии SOS ва тартиби истифодаи он.', 'ru' => 'Официальное приложение SOS и порядок его использования.', 'en' => 'The official SOS application and how to use it.'],
            ),
            $this->page(
                'privacy',
                ['tg' => 'Ҳифзи маълумоти шахсӣ', 'ru' => 'Защита персональных данных', 'en' => 'Personal data protection'],
                [
                    'tg' => '<p>Маълумоте, ки дар муроҷиати электронӣ пешниҳод мешавад, танҳо барои баррасии муроҷиат ва омода кардани ҷавоб истифода мегардад.</p><p>Коркарди маълумот мутобиқи қонунгузории Ҷумҳурии Тоҷикистон анҷом дода мешавад.</p>',
                    'ru' => '<p>Персональные данные из электронной приёмной используются только для рассмотрения обращения и подготовки ответа.</p><p>Обработка выполняется в соответствии с законодательством Республики Таджикистан.</p>',
                    'en' => '<p>Personal data submitted through the electronic reception is used only to process the request and prepare a response.</p><p>Processing is performed under the laws of the Republic of Tajikistan.</p>',
                ],
                ['tg' => 'Сиёсати коркард ва ҳифзи маълумоти шахсӣ.', 'ru' => 'Политика обработки и защиты персональных данных.', 'en' => 'Personal data processing and protection policy.'],
            ),
            $this->page(
                'accessibility',
                ['tg' => 'Дастрасӣ', 'ru' => 'Доступность', 'en' => 'Accessibility'],
                [
                    'tg' => '<p>Сомона паймоиш бо клавиатура, калонкунии матн, мавзӯи торик ва барномаҳои экранхонро дастгирӣ мекунад.</p><p>Дар бораи мушкилоти дастрасӣ тавассути қабулгоҳи электронӣ хабар диҳед.</p>',
                    'ru' => '<p>Сайт поддерживает навигацию с клавиатуры, масштабирование текста, тёмную тему и программы экранного доступа.</p><p>О проблеме доступности можно сообщить через электронную приёмную.</p>',
                    'en' => '<p>The site supports keyboard navigation, text scaling, a dark theme and screen readers.</p><p>Accessibility problems can be reported through the electronic reception.</p>',
                ],
                ['tg' => 'Имкониятҳои дастрасии сомонаи расмӣ.', 'ru' => 'Возможности доступности официального сайта.', 'en' => 'Accessibility features of the official website.'],
            ),
        ];

        foreach ($pages as $sort => $data) {
            $page = Page::withTrashed()->firstOrNew(['slug' => $data['slug']]);
            $page->fill([
                'title' => $data['title'],
                'body' => $data['body'],
                'seo_title' => $data['title'],
                'seo_description' => $data['seo_description'],
                'status' => ContentStatus::Published,
                'published_at' => now(),
                'sort' => $sort,
            ]);
            $page->deleted_at = null;
            $page->save();
        }
    }

    /**
     * @param  array<string, string>  $title
     * @param  array<string, string>  $body
     * @param  array<string, string>  $description
     * @return array<string, mixed>
     */
    private function page(string $slug, array $title, array $body, array $description): array
    {
        return [
            'slug' => $slug,
            'title' => $title,
            'body' => $body,
            'seo_description' => $description,
        ];
    }
}
