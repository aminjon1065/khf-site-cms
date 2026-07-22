<?php

namespace App\Services;

use App\Models\Setting;

/** Builds the whitelisted, locale-aware settings shared by public endpoints. */
class PublicSettingsService
{
    /**
     * @return array{data: array<string, mixed>, meta: array<string, mixed>}
     */
    public function resolve(string $locale): array
    {
        $groups = Setting::grouped();
        $fallbackUsed = false;

        $get = fn (string $group, string $key, mixed $default = '') => $groups[$group][$key] ?? $default;
        $localized = function (string $group, string $key, string $default = '') use ($get, $locale, &$fallbackUsed): string {
            $requested = $get($group, "{$key}_{$locale}");
            if (is_string($requested) && trim($requested) !== '') {
                return $requested;
            }

            $fallbackUsed = true;
            foreach (["{$key}_ru", $key] as $fallbackKey) {
                $fallback = $get($group, $fallbackKey);
                if (is_string($fallback) && trim($fallback) !== '') {
                    return $fallback;
                }
            }

            return $default;
        };

        $legacyServiceValues = $get('footer', 'emergency_services', []);
        $legacyServices = collect(is_array($legacyServiceValues) ? $legacyServiceValues : [])->keyBy('num');
        $emergencyServices = collect(['112', '101', '102', '103'])
            ->map(fn (string $number): array => [
                'num' => $number,
                'label' => $localized(
                    'footer',
                    "emergency_{$number}",
                    (string) data_get($legacyServices->get($number), 'label', ''),
                ),
            ])
            ->all();

        return [
            'data' => [
                'org' => [
                    'name' => $localized('org', 'name'),
                    'short_name' => $localized('org', 'short_name'),
                    'about' => $localized('org', 'about'),
                    'address' => $localized('org', 'address'),
                    'email' => $get('org', 'email'),
                    'emergency_number' => $get('org', 'emergency_number', '112'),
                    'trust_phone' => $get('org', 'trust_phone'),
                ],
                'contacts' => [
                    'press_email' => $get('contacts', 'press_email'),
                    'press_phone' => $get('contacts', 'press_phone'),
                    'duty_phone' => $get('contacts', 'duty_phone'),
                ],
                'social' => is_array($groups['social'] ?? null) ? $groups['social'] : [],
                'emergency_services' => $emergencyServices,
                'copyright' => $localized('footer', 'copyright'),
                'seo' => [
                    'meta_title' => $localized('seo', 'meta_title'),
                    'meta_description' => $localized('seo', 'meta_description'),
                ],
            ],
            'meta' => [
                'requested_locale' => $locale,
                'resolved_locale' => $locale,
                'fallback_used' => $fallbackUsed,
                'available_locales' => ['tg', 'ru', 'en'],
            ],
        ];
    }
}
