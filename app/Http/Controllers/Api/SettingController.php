<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;

/**
 * Public site settings for the Next.js header/footer. Only whitelisted,
 * non-sensitive groups are exposed — never security / integrations / backup.
 */
class SettingController extends Controller
{
    public function index(): JsonResponse
    {
        $g = Setting::grouped();

        $get = fn (string $group, string $key, mixed $default = '') => $g[$group][$key] ?? $default;

        return response()->json(['data' => [
            'org' => [
                'name' => $get('org', 'name_ru'),
                'short_name' => $get('org', 'short_name_ru'),
                'about' => $get('org', 'about'),
                'address' => $get('org', 'address'),
                'email' => $get('org', 'email'),
                'emergency_number' => $get('org', 'emergency_number', '112'),
                'trust_phone' => $get('org', 'trust_phone'),
            ],
            'contacts' => [
                'press_email' => $get('contacts', 'press_email'),
                'press_phone' => $get('contacts', 'press_phone'),
                'duty_phone' => $get('contacts', 'duty_phone'),
            ],
            'social' => is_array($g['social'] ?? null) ? $g['social'] : [],
            'emergency_services' => is_array($get('footer', 'emergency_services', [])) ? $get('footer', 'emergency_services', []) : [],
            'copyright' => $get('footer', 'copyright'),
            'seo' => [
                'meta_title' => $get('seo', 'meta_title'),
                'meta_description' => $get('seo', 'meta_description'),
            ],
        ]]);
    }
}
