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
                'type' => RegionType::City,
                'regional_center' => 'Душанбе',
                'phone' => '+992 (37) 221-59-00',
                'duty_phone' => '+992 (37) 221-59-00',
                'districts_count' => 4,
                'status' => 'normal',
                'districts' => ['Исмоили Сомони', 'Сино', 'Фирдавси', 'Шохмансур'],
            ],
            [
                'code' => 'sughd',
                'name' => ['ru' => 'Согдийская область', 'tg' => 'вилояти Суғд', 'en' => 'Sughd Region'],
                'type' => RegionType::Oblast,
                'regional_center' => 'Худжанд',
                'phone' => '+992 (3422) 6-25-00',
                'duty_phone' => '+992 (3422) 6-25-11',
                'districts_count' => 18,
                'status' => 'normal',
                'districts' => ['Худжанд', 'Бустон', 'Гулистон', 'Истаравшан', 'Канибадам', 'Исфара', 'Пенджикент', 'Айни'],
            ],
            [
                'code' => 'khatlon',
                'name' => ['ru' => 'Хатлонская область', 'tg' => 'вилояти Хатлон', 'en' => 'Khatlon Region'],
                'type' => RegionType::Oblast,
                'regional_center' => 'Бохтар',
                'phone' => '+992 (3222) 2-14-77',
                'duty_phone' => '+992 (3222) 2-14-77',
                'districts_count' => 25,
                'status' => 'warning',
                'districts' => ['Бохтар', 'Куляб', 'Дангара', 'Фархор', 'Восе', 'Муминабад', 'Ховалинг', 'Норак', 'Леваканд'],
            ],
            [
                'code' => 'gbao',
                'name' => ['ru' => 'ГБАО', 'tg' => 'ВМКБ', 'en' => 'GBAO'],
                'type' => RegionType::Gbao,
                'regional_center' => 'Хорог',
                'phone' => '+992 (3522) 2-15-00',
                'duty_phone' => '+992 (3522) 2-15-01',
                'districts_count' => 8,
                'status' => 'attention',
                'districts' => ['Хорог', 'Ишкашим', 'Мургаб', 'Рушан', 'Дарваз', 'Ванч'],
            ],
            [
                'code' => 'rrp',
                'name' => ['ru' => 'Районы республиканского подчинения', 'tg' => 'НТҶ', 'en' => 'Districts of Republican Subordination'],
                'type' => RegionType::Rrp,
                'regional_center' => 'Душанбе',
                'phone' => '+992 (37) 221-60-00',
                'duty_phone' => '+992 (37) 221-60-01',
                'districts_count' => 13,
                'status' => 'attention',
                'districts' => ['Гиссар', 'Вахдат', 'Рогун', 'Турсунзаде', 'Файзабад', 'Рашт', 'Нурабад', 'Таджикабад'],
            ],
        ];

        foreach ($regions as $sort => $data) {
            $districts = $data['districts'];
            unset($data['districts']);

            $region = Region::updateOrCreate(
                ['code' => $data['code']],
                array_merge($data, ['sort' => $sort]),
            );

            foreach ($districts as $dSort => $districtName) {
                District::updateOrCreate(
                    ['region_id' => $region->id, 'sort' => $dSort],
                    ['name' => ['ru' => $districtName, 'tg' => $districtName, 'en' => $districtName]],
                );
            }
        }
    }
}
