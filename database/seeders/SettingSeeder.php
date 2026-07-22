<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            'org' => [
                'name_ru' => 'Комитет по чрезвычайным ситуациям и гражданской обороне при Правительстве Республики Таджикистан',
                'name_tg' => 'Кумитаи ҳолатҳои фавқулодда ва мудофиаи гражданӣ назди Ҳукумати Ҷумҳурии Тоҷикистон',
                'name_en' => 'Committee of Emergency Situations and Civil Defence under the Government of the Republic of Tajikistan',
                'short_name_ru' => 'КЧС и ГО Республики Таджикистан',
                'short_name_tg' => 'КҲФ ва МГ Ҷумҳурии Тоҷикистон',
                'short_name_en' => 'CESCD of Tajikistan',
                'about' => 'Государственный орган по предупреждению и ликвидации чрезвычайных ситуаций, защите населения и территорий Республики Таджикистан.',
                'about_ru' => 'Государственный орган по предупреждению и ликвидации чрезвычайных ситуаций, защите населения и территорий Республики Таджикистан.',
                'about_tg' => 'Мақоми давлатӣ оид ба пешгирӣ ва рафъи ҳолатҳои фавқулода, ҳифзи аҳолӣ ва ҳудуди Ҷумҳурии Тоҷикистон.',
                'about_en' => 'The public authority responsible for emergency prevention and response and for protecting the people and territory of the Republic of Tajikistan.',
                'trust_phone' => '+992 (37) 221-59-00',
                'emergency_number' => '112',
                'address' => 'г. Душанбе, ул. Лохути, 26',
                'address_ru' => 'г. Душанбе, ул. Лохути, 26',
                'address_tg' => 'ш. Душанбе, кӯчаи Лоҳутӣ, 26',
                'address_en' => '26 Lohuti Street, Dushanbe',
                'email' => 'info@khf.tj',
            ],
            'social' => [
                'telegram' => 'https://t.me/khf_tj',
                'facebook' => 'https://facebook.com/khf.tj',
                'instagram' => 'https://instagram.com/khf.tj',
                'youtube' => 'https://youtube.com/@khf_tj',
            ],
            'footer' => [
                'copyright' => '© 2026 Комитет по чрезвычайным ситуациям и гражданской обороне. При использовании материалов ссылка на khf.tj обязательна.',
                'copyright_ru' => '© 2026 Комитет по чрезвычайным ситуациям и гражданской обороне. При использовании материалов ссылка на khf.tj обязательна.',
                'copyright_tg' => '© 2026 Кумитаи ҳолатҳои фавқулодда ва мудофиаи гражданӣ. Ҳангоми истифодаи мавод истинод ба khf.tj ҳатмист.',
                'copyright_en' => '© 2026 Committee of Emergency Situations and Civil Defence. Attribution to khf.tj is required when materials are reused.',
                'emergency_112_ru' => 'единая служба спасения',
                'emergency_112_tg' => 'хадамоти ягонаи наҷот',
                'emergency_112_en' => 'unified rescue service',
                'emergency_101_ru' => 'пожарная охрана',
                'emergency_101_tg' => 'хадамоти оташнишонӣ',
                'emergency_101_en' => 'fire service',
                'emergency_102_ru' => 'милиция',
                'emergency_102_tg' => 'милитсия',
                'emergency_102_en' => 'police',
                'emergency_103_ru' => 'скорая помощь',
                'emergency_103_tg' => 'ёрии таъҷилӣ',
                'emergency_103_en' => 'ambulance',
                'emergency_services' => [
                    ['num' => '112', 'label' => 'единая служба спасения'],
                    ['num' => '101', 'label' => 'пожарная охрана'],
                    ['num' => '102', 'label' => 'милиция'],
                    ['num' => '103', 'label' => 'скорая помощь'],
                ],
            ],
            'general' => [
                'site_title_ru' => 'Комитет по чрезвычайным ситуациям и гражданской обороне',
                'site_url' => 'https://khf.tj',
                'timezone' => 'Asia/Dushanbe',
                'default_locale' => 'tg',
            ],
            'contacts' => [
                'press_email' => 'press@khf.tj',
                'press_phone' => '+992 (37) 221-59-10',
                'duty_phone' => '+992 (37) 221-59-00',
            ],
            'languages' => [
                'enabled' => ['tg', 'ru', 'en'],
                'default' => 'tg',
                'require_translation' => ['tg', 'ru'],
            ],
            'seo' => [
                'meta_title' => 'КЧС и ГО Республики Таджикистан',
                'meta_description' => 'Официальный сайт Комитета по чрезвычайным ситуациям и гражданской обороне.',
                'meta_title_ru' => 'КЧС и ГО Республики Таджикистан',
                'meta_title_tg' => 'КҲФ ва МГ Ҷумҳурии Тоҷикистон',
                'meta_title_en' => 'CESCD of the Republic of Tajikistan',
                'meta_description_ru' => 'Официальный сайт Комитета по чрезвычайным ситуациям и гражданской обороне.',
                'meta_description_tg' => 'Сомонаи расмии Кумитаи ҳолатҳои фавқулодда ва мудофиаи гражданӣ.',
                'meta_description_en' => 'Official website of the Committee of Emergency Situations and Civil Defence.',
            ],
            'notifications' => [
                'approval_immediate' => true,
                'expiry_24h' => true,
                'daily_digest' => false,
            ],
            'security' => [
                'require_2fa' => true,
                'require_2fa_from' => '2026-08-01',
                'session_lifetime_minutes' => 120,
            ],
            'integrations' => [
                'sos_app_gateway' => 'https://sos.khf.tj/api',
                'rss_enabled' => true,
                'sms_gateway_status' => 'degraded',
            ],
            'backup' => [
                'schedule' => 'daily',
                'retention_days' => 30,
            ],
        ];

        foreach ($settings as $group => $pairs) {
            foreach ($pairs as $key => $value) {
                Setting::updateOrCreate(
                    ['group' => $group, 'key' => $key],
                    ['value' => $value],
                );
            }
        }
    }
}
