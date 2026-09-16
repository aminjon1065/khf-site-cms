<?php

namespace Database\Seeders;

use App\Enums\RegionType;
use App\Models\District;
use App\Models\Region;
use Illuminate\Database\Seeder;

/**
 * Regional directorates and the districts they cover.
 *
 * Addresses, postcodes, phones and mailboxes are the ones the Committee
 * publishes on its contacts page (kchs.tj/node/197); the head officers come
 * from the structure page (kchs.tj/node/1230). `districts_count` is derived
 * from the districts actually seeded, so the directory never advertises a
 * number the list cannot back up.
 */
class RegionSeeder extends Seeder
{
    public function run(): void
    {
        $regions = [
            [
                'code' => 'dushanbe',
                'name' => ['ru' => 'г. Душанбе', 'tg' => 'ш. Душанбе', 'en' => 'Dushanbe'],
                'head' => 'Штаб КЧС города Душанбе · начальник — полковник Шамсизода Сулаймон Шамси',
                'type' => RegionType::City,
                'regional_center' => 'Душанбе',
                'address' => 'г. Душанбе, ул. Айни, 127, индекс 734029',
                'phone' => '+992 (37) 225-44-73',
                'duty_phone' => '+992 (37) 225-44-76',
                'email' => 'mchsdushanbe@rs.tj',
                'status' => 'normal',
                'districts' => ['Исмоили Сомони', 'Сино', 'Фирдавси', 'Шохмансур'],
            ],
            [
                'code' => 'sughd',
                'name' => ['ru' => 'Согдийская область', 'tg' => 'вилояти Суғд', 'en' => 'Sughd Region'],
                'head' => 'Штаб КЧС по Согдийской области',
                'type' => RegionType::Oblast,
                'regional_center' => 'Худжанд',
                'address' => 'Согдийская область, г. Худжанд, 32 мкр, индекс 735700',
                'phone' => '+992 (3422) 6-58-72',
                'duty_phone' => '+992 (3422) 5-15-55',
                'email' => 'mchssughd@rs.tj',
                'status' => 'normal',
                'districts' => [
                    'Худжанд', 'Бустон', 'Гулистон', 'Истаравшан', 'Исфара', 'Канибадам', 'Пенджикент',
                    'Айни', 'Ашт', 'Бободжон Гафуров', 'Деваштич', 'Джаббор Расулов', 'Зафарабад',
                    'Истиклол', 'Кухистони Мастчох', 'Мастчох', 'Спитамен', 'Шахристан',
                ],
            ],
            [
                'code' => 'khatlon',
                'name' => ['ru' => 'Хатлонская область', 'tg' => 'вилояти Хатлон', 'en' => 'Khatlon Region'],
                'head' => 'Штаб КЧС по Хатлонской области',
                'type' => RegionType::Oblast,
                'regional_center' => 'Бохтар',
                'address' => 'г. Бохтар, проспект Вахдат, 178, индекс 735140',
                'phone' => '+992 (3222) 2-35-05',
                'duty_phone' => '+992 (3222) 2-78-28',
                'email' => 'mchskhatlon@rs.tj',
                'status' => 'warning',
                'districts' => [
                    'Бохтар', 'Куляб', 'Леваканд', 'Нурек',
                    'Абдурахмони Джоми', 'Балхи', 'Вахш', 'Восе', 'Дангара', 'Джайхун',
                    'Джалолиддини Балхи', 'Дусти', 'Кубодиён', 'Кушониён', 'Мир Сайид Алии Хамадони',
                    'Муминабад', 'Носири Хусрав', 'Пяндж', 'Темурмалик', 'Фархор', 'Хамадони',
                    'Ховалинг', 'Хуросон', 'Шамсиддин Шохин', 'Шахритус', 'Яван',
                ],
            ],
            [
                'code' => 'gbao',
                'name' => ['ru' => 'ГБАО', 'tg' => 'ВМКБ', 'en' => 'GBAO'],
                'head' => 'Штаб КЧС по ГБАО · начальник — полковник Мираков Наим',
                'type' => RegionType::Gbao,
                'regional_center' => 'Хорог',
                'address' => 'г. Хорог, ул. Саидамир Абдурахмонова, 28, индекс 736001',
                'phone' => '+992 (3522) 2-40-57',
                'duty_phone' => '+992 (3522) 2-53-47',
                'email' => 'mchsbadakhshon@rs.tj',
                'status' => 'attention',
                'districts' => ['Хорог', 'Ванч', 'Дарваз', 'Ишкашим', 'Мургаб', 'Рошткала', 'Рушан', 'Шугнан'],
            ],
            [
                'code' => 'rrp',
                // Compact label matches the risk-map / contacts convention; the
                // full designation lives in `head`.
                'name' => ['ru' => 'РРП', 'tg' => 'НТҶ', 'en' => 'RRP'],
                'head' => 'Штаб КЧС города Гиссара — районы республиканского подчинения',
                'type' => RegionType::Rrp,
                'regional_center' => 'Гиссар',
                'address' => 'г. Гиссар, ул. Исмоили Сомони, 10, индекс 735020',
                'phone' => '+992 (3139) 2-60-74',
                'duty_phone' => '+992 (3139) 2-60-45',
                'email' => 'mchshisor@rs.tj',
                'status' => 'attention',
                'districts' => [
                    'Вахдат', 'Гиссар', 'Рогун', 'Турсунзаде',
                    'Варзоб', 'Лахш', 'Нурабад', 'Рашт', 'Рудаки', 'Сангвор',
                    'Таджикабад', 'Файзабад', 'Шахринав',
                ],
            ],
        ];

        foreach ($regions as $sort => $data) {
            $districts = $data['districts'];
            unset($data['districts']);

            // Office name and postal address are stored translatable; the source
            // strings are Russian and mirrored to the other locales as a seed.
            $data['head'] = $this->translated($data['head']);
            $data['address'] = $this->translated($data['address']);
            $data['districts_count'] = count($districts);

            $region = Region::updateOrCreate(
                ['code' => $data['code']],
                array_merge($data, ['sort' => $sort]),
            );

            foreach ($districts as $districtSort => $districtName) {
                District::updateOrCreate(
                    ['region_id' => $region->id, 'sort' => $districtSort],
                    ['name' => $this->translated($districtName)],
                );
            }
        }
    }

    /**
     * @return array<string, string>
     */
    private function translated(string $value): array
    {
        return ['ru' => $value, 'tg' => $value, 'en' => $value];
    }
}
