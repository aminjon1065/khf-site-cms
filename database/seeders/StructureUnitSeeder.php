<?php

namespace Database\Seeders;

use App\Models\StructureUnit;
use Illuminate\Database\Seeder;

/**
 * C-1b: seeds the structure-units roster with the content that previously
 * lived in the front-end's `app/[locale]/structure/content.ts` (verbatim),
 * so the migration to the CMS does not change what visitors see.
 */
class StructureUnitSeeder extends Seeder
{
    public function run(): void
    {
        $units = [
            [
                'num' => '01',
                'name' => [
                    'ru' => 'Центр управления в кризисных ситуациях',
                    'tg' => 'Маркази идоракунӣ дар ҳолатҳои бӯҳронӣ',
                    'en' => 'Crisis Management Centre',
                ],
                'desc' => [
                    'ru' => 'Круглосуточный мониторинг обстановки, приём вызовов 112, координация реагирования',
                    'tg' => 'Мониторинги шабонарӯзии вазъият, қабули зангҳои 112, ҳамоҳангсозии вокуниш',
                    'en' => '24/7 situation monitoring, 112 call handling, response coordination',
                ],
            ],
            [
                'num' => '02',
                'name' => [
                    'ru' => 'Служба спасения',
                    'tg' => 'Хидмати наҷот',
                    'en' => 'Rescue service',
                ],
                'desc' => [
                    'ru' => 'Аэромобильный отряд, кинологические расчёты, водолазная и горная службы',
                    'tg' => 'Гурӯҳи ҳавоӣ, гурӯҳҳои кинологӣ, хидматҳои ғаввосӣ ва кӯҳӣ',
                    'en' => 'Air-mobile unit, canine teams, diving and mountain services',
                ],
            ],
            [
                'num' => '03',
                'name' => [
                    'ru' => 'Управление гражданской обороны',
                    'tg' => 'Идораи мудофиаи гражданӣ',
                    'en' => 'Civil Defence Directorate',
                ],
                'desc' => [
                    'ru' => 'Планы ГО, эвакуационные мероприятия, защитные сооружения',
                    'tg' => 'Нақшаҳои мудофиаи гражданӣ, чорабиниҳои эвакуатсионӣ, иншооти муҳофизатӣ',
                    'en' => 'Civil defence plans, evacuation measures, protective facilities',
                ],
            ],
            [
                'num' => '04',
                'name' => [
                    'ru' => 'Управление предупреждения ЧС',
                    'tg' => 'Идораи пешгирии ҲФ',
                    'en' => 'Emergency Prevention Directorate',
                ],
                'desc' => [
                    'ru' => 'Прогнозирование рисков, селе- и лавиноопасные участки, надзор',
                    'tg' => 'Пешгӯии хатарҳо, минтақаҳои хатари сел ва тарма, назорат',
                    'en' => 'Risk forecasting, mudflow- and avalanche-prone areas, supervision',
                ],
            ],
            [
                'num' => '05',
                'name' => [
                    'ru' => 'Учебный центр',
                    'tg' => 'Маркази таълимӣ',
                    'en' => 'Training centre',
                ],
                'desc' => [
                    'ru' => 'Подготовка спасателей и обучение населения действиям при ЧС',
                    'tg' => 'Омодасозии наҷотдиҳандагон ва омӯзиши аҳолӣ барои амал ҳангоми ҳолатҳои фавқулода',
                    'en' => 'Training rescuers and teaching the public how to act in emergencies',
                ],
            ],
            [
                'num' => '06',
                'name' => [
                    'ru' => 'Управление международного сотрудничества',
                    'tg' => 'Идораи ҳамкории байналмилалӣ',
                    'en' => 'International Cooperation Directorate',
                ],
                'desc' => [
                    'ru' => 'Программы с УСРБ ООН, ИНСАРАГ, партнёрами по региону',
                    'tg' => 'Барномаҳо бо СММ оид ба БОХ, ИНСАРАГ, шарикони минтақа',
                    'en' => 'Programmes with UNDRR, INSARAG and regional partners',
                ],
            ],
        ];

        foreach ($units as $sort => $data) {
            StructureUnit::query()->updateOrCreate(['num' => $data['num']], $data + ['sort' => $sort]);
        }
    }
}
