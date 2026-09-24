<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Setting\SettingRequest;
use App\Jobs\RevalidateFrontend;
use App\Models\Setting;
use App\Support\FrontendRevalidation;
use App\Support\SituationFreshness;
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
     * @return list<array{group: string, label: string, fields: list<array{key: string, label: string, type: string, hint?: string, default?: string, options?: list<array{value: string, label: string}>}>}>
     */
    private static function sections(): array
    {
        return [
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
                ['key' => 'trust_phone', 'label' => 'Телефон доверия', 'type' => 'text'],
            ]],
            ['group' => 'social', 'label' => 'Социальные сети', 'fields' => [
                ['key' => 'telegram', 'label' => 'Telegram', 'type' => 'text'],
                ['key' => 'facebook', 'label' => 'Facebook', 'type' => 'text'],
                ['key' => 'instagram', 'label' => 'Instagram', 'type' => 'text'],
                ['key' => 'youtube', 'label' => 'YouTube', 'type' => 'text'],
            ]],
            ['group' => 'seo', 'label' => 'Как сайт выглядит в поиске', 'fields' => [
                ['key' => 'meta_title_tg', 'label' => 'Заголовок для поиска · ТҶ', 'type' => 'text'],
                ['key' => 'meta_title_ru', 'label' => 'Заголовок для поиска · РУ', 'type' => 'text'],
                ['key' => 'meta_title_en', 'label' => 'Заголовок для поиска · EN', 'type' => 'text'],
                ['key' => 'meta_description_tg', 'label' => 'Описание для поиска · ТҶ', 'type' => 'textarea'],
                ['key' => 'meta_description_ru', 'label' => 'Описание для поиска · РУ', 'type' => 'textarea'],
                ['key' => 'meta_description_en', 'label' => 'Описание для поиска · EN', 'type' => 'textarea'],
            ]],
            ['group' => 'structure', 'label' => 'Структура — сводные цифры', 'fields' => [
                ['key' => 'founded_year', 'label' => 'Год образования', 'type' => 'text'],
                ['key' => 'units_count', 'label' => 'Подразделений по стране', 'type' => 'text'],
            ]],
            ['group' => 'footer', 'label' => 'Подвал', 'fields' => [
                ['key' => 'copyright_tg', 'label' => 'Строка об авторских правах · ТҶ', 'type' => 'textarea'],
                ['key' => 'copyright_ru', 'label' => 'Строка об авторских правах · РУ', 'type' => 'textarea'],
                ['key' => 'copyright_en', 'label' => 'Строка об авторских правах · EN', 'type' => 'textarea'],
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
            ['group' => 'situation', 'label' => 'Оперативная обстановка на сайте', 'fields' => [
                [
                    'key' => 'stale_after_minutes',
                    'label' => 'Сведения считаются устаревшими через',
                    'type' => 'select',
                    'hint' => 'Посетители видят, на какое время известна обстановка. Если сведения старше этого срока, сайт предупредит, что они могли устареть, и посоветует звонить 112.',
                    'default' => (string) SituationFreshness::DEFAULT_MINUTES,
                    'options' => SituationFreshness::options(),
                ],
            ]],
        ];
    }

    public function index(Request $request): Response
    {
        abort_unless((bool) $request->user()?->can('settings.view'), 403);

        $grouped = Setting::grouped();

        $sections = array_map(function (array $section) use ($grouped): array {
            $section['fields'] = array_map(function (array $field) use ($section, $grouped): array {
                $value = $grouped[$section['group']][$field['key']] ?? ($field['default'] ?? '');
                $field['value'] = is_scalar($value) ? (string) $value : '';

                return $field;
            }, $section['fields']);

            return $section;
        }, self::sections());

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

        $payload = FrontendRevalidation::forShell();
        RevalidateFrontend::dispatch(
            type: $payload['type'],
            id: $payload['id'],
            slug: $payload['slug'],
            locales: $payload['locales'],
            event: $payload['event'],
        )->afterCommit();

        return back()->with('success', 'Настройки сохранены.');
    }

    /**
     * @return list<string>
     */
    private function allowedKeys(): array
    {
        $keys = [];

        foreach (self::sections() as $section) {
            foreach ($section['fields'] as $field) {
                $keys[] = $section['group'].'.'.$field['key'];
            }
        }

        return $keys;
    }
}
