<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Setting\SettingRequest;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingController extends Controller
{
    /**
     * The editable (public-facing) settings schema. Sensitive groups
     * (security, integrations, backup) are intentionally not editable here.
     *
     * @var list<array{group: string, label: string, fields: list<array{key: string, label: string, type: string}>}>
     */
    private const SECTIONS = [
        ['group' => 'org', 'label' => 'Организация', 'fields' => [
            ['key' => 'name_tg', 'label' => 'Полное название · ТҶ', 'type' => 'text'],
            ['key' => 'name_ru', 'label' => 'Полное название · РУ', 'type' => 'text'],
            ['key' => 'name_en', 'label' => 'Полное название · EN', 'type' => 'text'],
            ['key' => 'short_name_tg', 'label' => 'Короткое название · ТҶ', 'type' => 'text'],
            ['key' => 'short_name_ru', 'label' => 'Короткое название · РУ', 'type' => 'text'],
            ['key' => 'short_name_en', 'label' => 'Короткое название · EN', 'type' => 'text'],
            ['key' => 'about_tg', 'label' => 'Описание для подвала · ТҶ', 'type' => 'textarea'],
            ['key' => 'about_ru', 'label' => 'Описание для подвала · РУ', 'type' => 'textarea'],
            ['key' => 'about_en', 'label' => 'Описание для подвала · EN', 'type' => 'textarea'],
            ['key' => 'address_tg', 'label' => 'Адрес · ТҶ', 'type' => 'text'],
            ['key' => 'address_ru', 'label' => 'Адрес · РУ', 'type' => 'text'],
            ['key' => 'address_en', 'label' => 'Адрес · EN', 'type' => 'text'],
            ['key' => 'email', 'label' => 'E-mail', 'type' => 'text'],
            ['key' => 'emergency_number', 'label' => 'Экстренный номер', 'type' => 'text'],
            ['key' => 'trust_phone', 'label' => 'Телефон доверия', 'type' => 'text'],
        ]],
        ['group' => 'contacts', 'label' => 'Пресс-служба и дежурная часть', 'fields' => [
            ['key' => 'press_email', 'label' => 'E-mail пресс-службы', 'type' => 'text'],
            ['key' => 'press_phone', 'label' => 'Телефон пресс-службы', 'type' => 'text'],
            ['key' => 'duty_phone', 'label' => 'Дежурная часть', 'type' => 'text'],
        ]],
        ['group' => 'social', 'label' => 'Социальные сети', 'fields' => [
            ['key' => 'telegram', 'label' => 'Telegram', 'type' => 'text'],
            ['key' => 'facebook', 'label' => 'Facebook', 'type' => 'text'],
            ['key' => 'instagram', 'label' => 'Instagram', 'type' => 'text'],
            ['key' => 'youtube', 'label' => 'YouTube', 'type' => 'text'],
        ]],
        ['group' => 'seo', 'label' => 'SEO по умолчанию', 'fields' => [
            ['key' => 'meta_title_tg', 'label' => 'Meta title · ТҶ', 'type' => 'text'],
            ['key' => 'meta_title_ru', 'label' => 'Meta title · РУ', 'type' => 'text'],
            ['key' => 'meta_title_en', 'label' => 'Meta title · EN', 'type' => 'text'],
            ['key' => 'meta_description_tg', 'label' => 'Meta description · ТҶ', 'type' => 'textarea'],
            ['key' => 'meta_description_ru', 'label' => 'Meta description · РУ', 'type' => 'textarea'],
            ['key' => 'meta_description_en', 'label' => 'Meta description · EN', 'type' => 'textarea'],
        ]],
        ['group' => 'footer', 'label' => 'Подвал', 'fields' => [
            ['key' => 'copyright_tg', 'label' => 'Копирайт · ТҶ', 'type' => 'textarea'],
            ['key' => 'copyright_ru', 'label' => 'Копирайт · РУ', 'type' => 'textarea'],
            ['key' => 'copyright_en', 'label' => 'Копирайт · EN', 'type' => 'textarea'],
            ['key' => 'emergency_112_tg', 'label' => '112 · ТҶ', 'type' => 'text'],
            ['key' => 'emergency_112_ru', 'label' => '112 · РУ', 'type' => 'text'],
            ['key' => 'emergency_112_en', 'label' => '112 · EN', 'type' => 'text'],
            ['key' => 'emergency_101_tg', 'label' => '101 · ТҶ', 'type' => 'text'],
            ['key' => 'emergency_101_ru', 'label' => '101 · РУ', 'type' => 'text'],
            ['key' => 'emergency_101_en', 'label' => '101 · EN', 'type' => 'text'],
            ['key' => 'emergency_102_tg', 'label' => '102 · ТҶ', 'type' => 'text'],
            ['key' => 'emergency_102_ru', 'label' => '102 · РУ', 'type' => 'text'],
            ['key' => 'emergency_102_en', 'label' => '102 · EN', 'type' => 'text'],
            ['key' => 'emergency_103_tg', 'label' => '103 · ТҶ', 'type' => 'text'],
            ['key' => 'emergency_103_ru', 'label' => '103 · РУ', 'type' => 'text'],
            ['key' => 'emergency_103_en', 'label' => '103 · EN', 'type' => 'text'],
        ]],
    ];

    public function index(Request $request): Response
    {
        abort_unless((bool) $request->user()?->can('settings.view'), 403);

        $grouped = Setting::grouped();

        $sections = array_map(function (array $section) use ($grouped): array {
            $section['fields'] = array_map(function (array $field) use ($section, $grouped): array {
                $value = $grouped[$section['group']][$field['key']] ?? '';
                $field['value'] = is_string($value) ? $value : '';

                return $field;
            }, $section['fields']);

            return $section;
        }, self::SECTIONS);

        return Inertia::render('settings/index', ['sections' => $sections]);
    }

    public function update(SettingRequest $request): RedirectResponse
    {
        abort_unless((bool) $request->user()?->can('settings.edit'), 403);

        $allowed = $this->allowedKeys();

        /** @var array<string, mixed> $input */
        $input = $request->input('settings', []);

        foreach ($input as $group => $pairs) {
            if (! is_array($pairs)) {
                continue;
            }

            foreach ($pairs as $key => $value) {
                if (! in_array("{$group}.{$key}", $allowed, true)) {
                    continue; // ignore anything outside the whitelist
                }

                Setting::updateOrCreate(
                    ['group' => $group, 'key' => $key],
                    ['value' => is_string($value) ? $value : ''],
                );
            }
        }

        return back()->with('success', 'Настройки сохранены.');
    }

    /**
     * @return list<string>
     */
    private function allowedKeys(): array
    {
        $keys = [];

        foreach (self::SECTIONS as $section) {
            foreach ($section['fields'] as $field) {
                $keys[] = $section['group'].'.'.$field['key'];
            }
        }

        return $keys;
    }
}
