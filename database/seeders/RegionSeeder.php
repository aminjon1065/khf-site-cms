<?php

namespace Database\Seeders;

use App\Enums\RegionType;
use App\Models\District;
use App\Models\Region;
use Illuminate\Database\Seeder;

class RegionSeeder extends Seeder
{
    public function run(): void
    {
        $regions = [
            [
                'code' => 'dushanbe',
                'name' => ['ru' => 'г. Душанбе', 'tg' => 'ш. Душанбе', 'en' => 'Dushanbe'],
                'head' => 'Управление по г. Душанбе',
                'type' => RegionType::City,
                'regional_center' => 'Душанбе',
                'address' => 'ул. Н. Карабаева, 54',
                'phone' => '+992 (37) 233-18-05',
                'duty_phone' => '+992 (37) 221-59-00',
                'email' => 'dushanbe@khf.tj',
                'districts_count' => 4,
                'status' => 'normal',
                'districts' => ['Исмоили Сомони', 'Сино', 'Фирдавси', 'Шохмансур'],
            ],
            [
                'code' => 'sughd',
                'name' => ['ru' => 'Согдийская область', 'tg' => 'вилояти Суғд', 'en' => 'Sughd Region'],
                'head' => 'Управление по Согдийской области',
                'type' => RegionType::Oblast,
                'regional_center' => 'Худжанд',
                'address' => 'г. Худжанд, ул. Камола Худжанди, 120',
                'phone' => '+992 (3422) 6-44-71',
                'duty_phone' => '+992 (3422) 6-25-11',
                'email' => 'sughd@khf.tj',
                'districts_count' => 18,
                'status' => 'normal',
                'districts' => ['Худжанд', 'Бустон', 'Гулистон', 'Истаравшан', 'Канибадам', 'Исфара', 'Пенджикент', 'Айни'],
            ],
            [
                'code' => 'khatlon',
                'name' => ['ru' => 'Хатлонская область', 'tg' => 'вилояти Хатлон', 'en' => 'Khatlon Region'],
                'head' => 'Управление по Хатлонской области',
                'type' => RegionType::Oblast,
                'regional_center' => 'Бохтар',
                'address' => 'г. Бохтар, ул. Айни, 47',
                'phone' => '+992 (3222) 2-38-90',
                'duty_phone' => '+992 (3222) 2-14-77',
                'email' => 'khatlon@khf.tj',
                'districts_count' => 25,
                'status' => 'warning',
                'districts' => ['Бохтар', 'Куляб', 'Дангара', 'Фархор', 'Восе', 'Муминабад', 'Ховалинг', 'Норак', 'Леваканд'],
            ],
            [
                'code' => 'gbao',
                'name' => ['ru' => 'ГБАО', 'tg' => 'ВМКБ', 'en' => 'GBAO'],
                'head' => 'Управление по ГБАО',
                'type' => RegionType::Gbao,
                'regional_center' => 'Хорог',
                'address' => 'г. Хорог, ул. Ленина, 18',
                'phone' => '+992 (3522) 2-25-13',
                'duty_phone' => '+992 (3522) 2-15-01',
                'email' => 'gbao@khf.tj',
                'districts_count' => 8,
                'status' => 'attention',
                'districts' => ['Хорог', 'Ишкашим', 'Мургаб', 'Рушан', 'Дарваз', 'Ванч'],
            ],
            [
                'code' => 'rrp',
                // Compact label matches the risk-map / contacts convention; the
                // full designation lives in `head`.
                'name' => ['ru' => 'РРП', 'tg' => 'НТҶ', 'en' => 'RRP'],
                'head' => 'Управление по районам республиканского подчинения',
                'type' => RegionType::Rrp,
                'regional_center' => 'Душанбе',
                'address' => 'г. Вахдат, ул. Исмоили Сомони, 9',
                'phone' => '+992 (3136) 2-27-44',
                'duty_phone' => '+992 (37) 221-60-01',
                'email' => 'rrp@khf.tj',
                'districts_count' => 13,
                'status' => 'attention',
                'districts' => ['Гиссар', 'Вахдат', 'Рогун', 'Турсунзаде', 'Файзабад', 'Рашт', 'Нурабад', 'Таджикабад'],
            ],
        ];

        foreach ($regions as $sort => $data) {
            $districts = $data['districts'];
            unset($data['districts']);

            // Office name and postal address are stored translatable; the source
            // strings are Russian and mirrored to the other locales as a seed.
            $data['head'] = $this->translated($data['head']);
            $data['address'] = $this->translated($data['address']);

            $region = Region::updateOrCreate(
                ['code' => $data['code']],
                array_merge($data, ['sort' => $sort]),
            );

            foreach ($districts as $dSort => $districtName) {
                District::updateOrCreate(
                    ['region_id' => $region->id, 'sort' => $dSort],
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
