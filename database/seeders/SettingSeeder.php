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
                'short_name_ru' => 'КЧС и ГО РТ',
                'trust_phone' => '+992 (37) 221-59-00',
                'emergency_number' => '112',
                'address' => 'г. Душанбе, ул. Лохути, 26',
                'email' => 'info@khf.tj',
            ],
            'general' => [
                'site_title_ru' => 'Комитет по чрезвычайным ситуациям и гражданской обороне',
                'site_url' => 'https://khf.tj',
                'timezone' => 'Asia/Dushanbe',
                'default_locale' => 'ru',
            ],
            'contacts' => [
                'press_email' => 'press@khf.tj',
                'press_phone' => '+992 (37) 221-59-10',
                'duty_phone' => '+992 (37) 221-59-00',
            ],
            'languages' => [
                'enabled' => ['tg', 'ru', 'en'],
                'default' => 'ru',
                'require_translation' => ['tg', 'ru'],
            ],
            'seo' => [
                'meta_title' => 'КЧС и ГО Республики Таджикистан',
                'meta_description' => 'Официальный сайт Комитета по чрезвычайным ситуациям и гражданской обороне.',
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
