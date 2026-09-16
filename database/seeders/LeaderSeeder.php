<?php

namespace Database\Seeders;

use App\Models\Leader;
use Database\Seeders\Concerns\SeedsFromSource;
use Illuminate\Database\Seeder;

/**
 * C-1a: seeds the leadership roster. Names, ranks, office phones and portraits
 * mirror the Committee's published roster (kchs.tj/node/1230, khf.tj/node/1750)
 * so the public leadership page carries real people during development.
 *
 * Portraits are pulled from the source sites on first run and cached; see
 * {@see SeedsFromSource}. Without a network the roster is still seeded, just
 * without photos.
 */
class LeaderSeeder extends Seeder
{
    use SeedsFromSource;

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
                'ru' => 'Генерал-лейтенант · руководит Комитетом с 2016 года · (+992 37) 221-13-31',
                'tg' => 'Генерал-лейтенант · аз соли 2016 Кумитаро роҳбарӣ мекунад · (+992 37) 221-13-31',
                'en' => 'Lieutenant General · has led the Committee since 2016 · (+992 37) 221-13-31',
            ],
            'bio' => [
                'ru' => 'Осуществляет общее руководство Комитетом, координацию сил и средств единой государственной системы предупреждения и ликвидации чрезвычайных ситуаций, представляет Комитет в Правительстве Республики Таджикистан и международных организациях.',
                'tg' => 'Роҳбарии умумии Кумита, ҳамоҳангсозии қувва ва воситаҳои низоми ягонаи давлатии пешгирӣ ва бартарафсозии ҳолатҳои фавқулодаро амалӣ мекунад, Кумитаро дар Ҳукумати Ҷумҳурии Тоҷикистон ва созмонҳои байналмилалӣ намояндагӣ мекунад.',
                'en' => 'Provides overall leadership of the Committee, coordinates the forces and resources of the unified state system for emergency prevention and response, and represents the Committee in the Government of the Republic of Tajikistan and international organisations.',
            ],
            'photo' => null,
        ];

        $deputies = [
            [
                'sort' => 1,
                'role' => [
                    'ru' => 'Первый заместитель Председателя',
                    'tg' => 'Муовини якуми Раис',
                    'en' => 'First Deputy Chairman',
                ],
                'name' => [
                    'ru' => 'Латифзода Хотамшо Латиф',
                    'tg' => 'Латифзода Ҳотамшо Латиф',
                    'en' => 'Latifzoda Khotamsho Latif',
                ],
                'meta' => [
                    'ru' => 'Генерал-майор · (+992 37) 223-10-24',
                    'tg' => 'Генерал-майор · (+992 37) 223-10-24',
                    'en' => 'Major General · (+992 37) 223-10-24',
                ],
                'bio' => [
                    'ru' => 'Оперативное реагирование, Центр управления в кризисных ситуациях и служба спасения 112, координация работы территориальных управлений.',
                    'tg' => 'Вокуниши оперативӣ, Маркази идоракунӣ дар ҳолатҳои бӯҳронӣ ва хидмати наҷоти 112, ҳамоҳангсозии кори раёсатҳои минтақавӣ.',
                    'en' => 'Rapid response, the Crisis Management Centre and the 112 rescue service, coordination of the territorial directorates.',
                ],
                'photo' => 'https://khf.tj/sites/default/files/latifzoda_khotamsho_1_1.jpg',
            ],
            [
                'sort' => 2,
                'role' => [
                    'ru' => 'Заместитель Председателя',
                    'tg' => 'Муовини Раис',
                    'en' => 'Deputy Chairman',
                ],
                'name' => [
                    'ru' => 'Исозода Сулаймон Умар',
                    'tg' => 'Исозода Сулаймон Умар',
                    'en' => 'Isozoda Sulaymon Umar',
                ],
                'meta' => [
                    'ru' => 'Генерал-майор · (+992 37) 221-31-29',
                    'tg' => 'Генерал-майор · (+992 37) 221-31-29',
                    'en' => 'Major General · (+992 37) 221-31-29',
                ],
                'bio' => [
                    'ru' => 'Гражданская оборона, подготовка населения, эвакуационные мероприятия и защитные сооружения.',
                    'tg' => 'Мудофиаи гражданӣ, омодасозии аҳолӣ, чорабиниҳои эвакуатсионӣ ва иншооти муҳофизатӣ.',
                    'en' => 'Civil defence, public preparedness, evacuation measures and protective facilities.',
                ],
                'photo' => 'https://khf.tj/sites/default/files/whatsapp_image_2026-08-04_at_11.49.27.jpeg',
            ],
            [
                'sort' => 3,
                'role' => [
                    'ru' => 'Заместитель Председателя',
                    'tg' => 'Муовини Раис',
                    'en' => 'Deputy Chairman',
                ],
                'name' => [
                    'ru' => 'Иброхимзода Имомали Набибулло',
                    'tg' => 'Иброҳимзода Имомалӣ Набибулло',
                    'en' => 'Ibrohimzoda Imomali Nabibullo',
                ],
                'meta' => [
                    'ru' => 'Генерал-майор · (+992 37) 223-14-46',
                    'tg' => 'Генерал-майор · (+992 37) 223-14-46',
                    'en' => 'Major General · (+992 37) 223-14-46',
                ],
                'bio' => [
                    'ru' => 'Защита населения и территорий, прогнозирование рисков, государственный надзор и международное сотрудничество.',
                    'tg' => 'Ҳифзи аҳолӣ ва ҳудуд, пешгӯии хатарҳо, назорати давлатӣ ва ҳамкории байналмилалӣ.',
                    'en' => 'Protection of the population and territories, risk forecasting, state supervision and international cooperation.',
                ],
                'photo' => 'https://khf.tj/sites/default/files/0076.jpg',
            ],
        ];

        foreach ([$chairman, ...$deputies] as $data) {
            $photo = $data['photo'];
            unset($data['photo']);

            $leader = Leader::query()->updateOrCreate(['sort' => $data['sort']], $data);

            $this->attachSourceImage($leader, $photo, 'photo');
        }
    }
}
