<?php

namespace App\Services;

use App\Models\Setting;
use App\Support\SituationFreshness;

/** Builds the whitelisted, locale-aware settings shared by public endpoints. */
class PublicSettingsService
{
    public function __construct(private readonly PublicReadModelCache $cache) {}

    /**
     * @return array{data: array<string, mixed>, meta: array<string, mixed>}
     */
    public function resolve(string $locale): array
    {
        return $this->cache->remember(
            PublicReadModelCache::SETTINGS,
            $locale,
            fn (): array => $this->build($locale),
        );
    }

    /**
     * @return array{data: array<string, mixed>, meta: array<string, mixed>}
     */
    private function build(string $locale): array
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

        // The site shows these only when they exist in the page's language
        // and otherwise uses its own wording for that language; a Russian
        // fallback would put a Russian <title> or address on /tj and /en.
        $exact = function (string $group, string $key) use ($get, $locale): string {
            $keys = $locale === 'ru' ? ["{$key}_ru", $key] : ["{$key}_{$locale}"];

            foreach ($keys as $candidate) {
                $value = $get($group, $candidate);

                if (is_string($value) && trim($value) !== '') {
                    return $value;
                }
            }

            return '';
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
                    'address' => $exact('org', 'address'),
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
                'structure' => [
                    'founded_year' => $get('structure', 'founded_year'),
                    'units_count' => $get('structure', 'units_count'),
                ],
                'copyright' => $localized('footer', 'copyright'),
                // How old the situation may be before the site says it may be
                // out of date (SituationFreshness).
                'situation' => [
                    'stale_after_minutes' => SituationFreshness::minutes($get('situation', 'stale_after_minutes', null)),
                ],
                'seo' => [
                    'meta_title' => $exact('seo', 'meta_title'),
                    'meta_description' => $exact('seo', 'meta_description'),
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
