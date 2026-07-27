<?php

namespace Database\Seeders;

use App\Models\Leader;
use Illuminate\Database\Seeder;

/**
 * C-1a: seeds the leadership roster with the content that previously lived in
 * the front-end's `app/[locale]/leadership/content.ts` (verbatim), so the
 * migration to the CMS does not change what visitors see.
 */
class LeaderSeeder extends Seeder
{
    public function run(): void
    {
        $chairman = [
            'sort' => 0,
            'is_chairman' => true,
            'role' => [
                'ru' => 'Председатель Комитета',
                'tg' => 'Раиси Кумита',
                'en' => 'Chairman of the Committee',
            ],
            'name' => [
                'ru' => 'Рустам Назарзода',
                'tg' => 'Рустам Назарзода',
                'en' => 'Rustam Nazarzoda',
            ],
            'meta' => [
                'ru' => 'Генерал-лейтенант · руководит Комитетом с 2016 года',
                'tg' => 'Генерал-лейтенант · аз соли 2016 Кумитаро роҳбарӣ мекунад',
                'en' => 'Lieutenant General · has led the Committee since 2016',
            ],
            'bio' => [
                'ru' => 'Осуществляет общее руководство Комитетом, координацию сил и средств единой государственной системы предупреждения и ликвидации чрезвычайных ситуаций, представляет Комитет в Правительстве Республики Таджикистан и международных организациях.',
                'tg' => 'Роҳбарии умумии Кумита, ҳамоҳангсозии қувва ва воситаҳои низоми ягонаи давлатии пешгирӣ ва бартарафсозии ҳолатҳои фавқулодаро амалӣ мекунад, Кумитаро дар Ҳукумати Ҷумҳурии Тоҷикистон ва созмонҳои байналмилалӣ намояндагӣ мекунад.',
                'en' => 'Provides overall leadership of the Committee, coordinates the forces and resources of the unified state system for emergency prevention and response, and represents the Committee in the Government of the Republic of Tajikistan and international organisations.',
            ],
        ];

        $deputies = [
            [
                'sort' => 1,
                'role' => [
                    'ru' => 'Первый заместитель председателя',
                    'tg' => 'Муовини якуми раис',
                    'en' => 'First Deputy Chairman',
                ],
                'name' => [
                    'ru' => 'Первый заместитель',
                    'tg' => 'Муовини якум',
                    'en' => 'First Deputy',
                ],
                'bio' => [
                    'ru' => 'Оперативное реагирование, Центр управления в кризисных ситуациях и служба спасения 112.',
                    'tg' => 'Вокуниши оперативӣ, Маркази идоракунӣ дар ҳолатҳои бӯҳронӣ ва хидмати наҷоти 112.',
                    'en' => 'Rapid response, the Crisis Management Centre and the 112 rescue service.',
                ],
            ],
            [
                'sort' => 2,
                'role' => [
                    'ru' => 'Заместитель председателя',
                    'tg' => 'Муовини раис',
                    'en' => 'Deputy Chairman',
                ],
                'name' => [
                    'ru' => 'Заместитель по гражданской обороне',
                    'tg' => 'Муовин оид ба мудофиаи гражданӣ',
                    'en' => 'Deputy for Civil Defence',
                ],
                'bio' => [
                    'ru' => 'Гражданская оборона, подготовка населения, эвакуационные мероприятия и защитные сооружения.',
                    'tg' => 'Мудофиаи гражданӣ, омодасозии аҳолӣ, чорабиниҳои эвакуатсионӣ ва иншооти муҳофизатӣ.',
                    'en' => 'Civil defence, public preparedness, evacuation measures and protective facilities.',
                ],
            ],
            [
                'sort' => 3,
                'role' => [
                    'ru' => 'Заместитель председателя',
                    'tg' => 'Муовини раис',
                    'en' => 'Deputy Chairman',
                ],
                'name' => [
                    'ru' => 'Заместитель по предупреждению ЧС',
                    'tg' => 'Муовин оид ба пешгирии ҲФ',
                    'en' => 'Deputy for Emergency Prevention',
                ],
                'bio' => [
                    'ru' => 'Прогнозирование рисков, государственный надзор и международное сотрудничество.',
                    'tg' => 'Пешгӯии хатарҳо, назорати давлатӣ ва ҳамкории байналмилалӣ.',
                    'en' => 'Risk forecasting, state supervision and international cooperation.',
                ],
            ],
        ];

        foreach ([$chairman, ...$deputies] as $data) {
            Leader::query()->updateOrCreate(['sort' => $data['sort']], $data);
        }
    }
}
