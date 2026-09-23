<?php

use App\Models\Page;
use Illuminate\Database\Migrations\Migration;

/**
 * One-off data alignment: the public site now renders the CMS pages
 * `structure` and `symbols` inside its own sections (/structure, /symbols) —
 * the page title becomes the section heading and the page text its intro.
 * The originally seeded texts were written as standalone pages (the symbols
 * text repeats the official flag/emblem/anthem blocks the section already
 * shows), so replace them with the sections' current intros. Only values
 * nobody has edited since seeding are touched.
 */
return new class extends Migration
{
    /**
     * slug → field → locale → [seeded value, new value].
     *
     * @var array<string, array<string, array<string, array{0: string, 1: string}>>>
     */
    private const REPLACEMENTS = [
        'structure' => [
            'body' => [
                'ru' => [
                    '<p>В структуру Комитета входят центральный аппарат, центр управления в кризисных ситуациях, спасательные подразделения, гражданская оборона, подразделения предупреждения ЧС, учебный центр и региональные управления.</p>',
                    '<p>Центральный аппарат, специализированные службы и региональные управления образуют единую государственную систему предупреждения и ликвидации чрезвычайных ситуаций.</p>',
                ],
                'tg' => [
                    '<p>Сохтори Кумита дастгоҳи марказӣ, маркази идоракунии ҳолатҳои буҳронӣ, воҳидҳои наҷотдиҳӣ, мудофиаи гражданӣ, пешгирии ҳолатҳои фавқулода, маркази таълимӣ ва раёсатҳои минтақавиро дар бар мегирад.</p>',
                    '<p>Аппарати марказӣ, хидматҳои махсус ва идораҳои минтақавӣ низоми ягонаи давлатии пешгирӣ ва бартарафсозии ҳолатҳои фавқулодаро ташкил медиҳанд.</p>',
                ],
                'en' => [
                    '<p>The Committee includes its central administration, crisis management centre, rescue units, civil defence and emergency prevention departments, a training centre and regional offices.</p>',
                    '<p>The central office, specialised services and regional offices form the unified state system for emergency prevention and response.</p>',
                ],
            ],
        ],
        'symbols' => [
            'title' => [
                'ru' => ['Государственные символы Таджикистана', 'Государственные символы Республики Таджикистан'],
                'tg' => ['Рамзҳои давлатии Тоҷикистон', 'Рамзҳои давлатии Ҷумҳурии Тоҷикистон'],
                'en' => ['State symbols of Tajikistan', 'State symbols of the Republic of Tajikistan'],
            ],
            'seo_title' => [
                'ru' => ['Государственные символы Таджикистана', 'Государственные символы Республики Таджикистан'],
                'tg' => ['Рамзҳои давлатии Тоҷикистон', 'Рамзҳои давлатии Ҷумҳурии Тоҷикистон'],
                'en' => ['State symbols of Tajikistan', 'State symbols of the Republic of Tajikistan'],
            ],
            'body' => [
                'ru' => [
                    '<h2>Государственный флаг</h2><p>Флаг состоит из трёх горизонтальных полос — красной, белой и зелёной; в центре расположены корона и семь звёзд.</p><h2>Государственный герб</h2><p>Герб включает корону, семь звёзд, восходящее солнце, горы, колосья пшеницы, хлопок и раскрытую книгу.</p><h2>Государственный гимн</h2><p>Государственным гимном Республики Таджикистан является «Суруди миллӣ».</p>',
                    '<p>Государственный Флаг, Государственный Герб и Государственный Гимн — символы суверенитета Республики Таджикистан. Порядок их использования установлен постановлениями Маджлиси намояндагон Маджлиси Оли Республики Таджикистан от 14 марта 2007 года № 499 и № 500 и Законом РТ «О Государственном Гимне».</p>',
                ],
                'tg' => [
                    '<h2>Парчами давлатӣ</h2><p>Парчам аз се рахи уфуқии сурх, сафед ва сабз иборат буда, дар маркази он тоҷ ва ҳафт ситора ҷойгиранд.</p><h2>Нишони давлатӣ</h2><p>Нишон тоҷ, ҳафт ситора, офтоби тулӯъкунанда, кӯҳҳо, хӯшаҳои гандум, пахта ва китоби кушодаро дар бар мегирад.</p><h2>Суруди миллӣ</h2><p>Суруди миллии Ҷумҳурии Тоҷикистон «Суруди миллӣ» мебошад.</p>',
                    '<p>Парчами давлатӣ, Нишони давлатӣ ва Суруди миллӣ — рамзҳои соҳибихтиёрии Ҷумҳурии Тоҷикистон мебошанд. Тартиби истифодаи онҳо бо қарорҳои Маҷлиси намояндагони Маҷлиси Олии Ҷумҳурии Тоҷикистон аз 14 марти соли 2007 № 499 ва № 500 ва Қонуни ҶТ «Дар бораи Суруди миллӣ» муқаррар шудааст.</p>',
                ],
                'en' => [
                    '<h2>National flag</h2><p>The flag has three horizontal red, white and green bands, with a crown and seven stars in the centre.</p><h2>National emblem</h2><p>The emblem includes a crown, seven stars, a rising sun, mountains, wheat, cotton and an open book.</p><h2>National anthem</h2><p>The national anthem of the Republic of Tajikistan is “Surudi Milli”.</p>',
                    '<p>The State Flag, the State Emblem and the State Anthem are symbols of the sovereignty of the Republic of Tajikistan. The rules for their use are established by resolutions No. 499 and No. 500 of the Assembly of Representatives of the Supreme Assembly of the Republic of Tajikistan of 14 March 2007 and by the Law of the RT “On the State Anthem”.</p>',
                ],
            ],
        ],
    ];

    public function up(): void
    {
        foreach (self::REPLACEMENTS as $slug => $fields) {
            $page = Page::query()->where('slug', $slug)->first();

            if ($page === null) {
                continue;
            }

            $dirty = false;

            foreach ($fields as $field => $locales) {
                foreach ($locales as $locale => [$seeded, $replacement]) {
                    if ($page->getTranslation($field, $locale, false) === $seeded) {
                        $page->setTranslation($field, $locale, $replacement);
                        $dirty = true;
                    }
                }
            }

            if ($dirty) {
                $page->save();
            }
        }
    }

    public function down(): void
    {
        // Content alignment only: the previous seeded texts are not restored.
    }
};
