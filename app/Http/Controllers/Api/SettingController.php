<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PublicSettingsService;
use Illuminate\Http\JsonResponse;

/**
 * Public site settings for the Next.js header/footer. Only whitelisted,
 * non-sensitive groups are exposed — never security / integrations / backup.
 */
class SettingController extends Controller
{
    public function __construct(private readonly PublicSettingsService $settings) {}

    public function index(): JsonResponse
    {
        return response()->json($this->settings->resolve(app()->getLocale()));
    }
}
