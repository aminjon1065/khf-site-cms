<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Models\Region;
use App\Models\Setting;
use Inertia\Inertia;
use Inertia\Response;

class EmergencyContactController extends Controller
{
    public function index(): Response
    {
        $settings = Setting::grouped();
        $services = $settings['footer']['emergency_services'] ?? [];

        return Inertia::render('contacts/index', [
            'central' => [
                'emergency_number' => $settings['org']['emergency_number'] ?? '112',
                'trust_phone' => $settings['org']['trust_phone'] ?? null,
                'duty_phone' => $settings['contacts']['duty_phone'] ?? null,
                'press_phone' => $settings['contacts']['press_phone'] ?? null,
                'press_email' => $settings['contacts']['press_email'] ?? null,
                'address' => $settings['org']['address'] ?? null,
                'email' => $settings['org']['email'] ?? null,
            ],
            'services' => is_array($services) ? array_values($services) : [],
            'regions' => Region::query()->orderBy('sort')->get()->map(fn (Region $region): array => [
                'id' => $region->id,
                'name' => $region->getTranslation('name', 'ru'),
                'center' => $region->regional_center,
                'phone' => $region->phone,
                'duty_phone' => $region->duty_phone,
                'email' => $region->email,
                'address' => $region->getTranslation('address', 'ru', false),
            ])->all(),
        ]);
    }
}
