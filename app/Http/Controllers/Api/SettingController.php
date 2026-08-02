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

    /**
     * Кэширование живёт внутри `PublicSettingsService` — в общем
     * `PublicReadModelCache` с версионными ключами и инвалидацией по
     * наблюдателю. Здесь раньше стоял ещё один слой `Cache::remember` на 60
     * секунд поверх него: он перехватывал ответ первым, поэтому обращение к
     * общему кэшу не происходило вовсе — и метрика попаданий видела не
     * попадание, а «кэш не использовался». Двух механизмов с разной
     * инвалидацией для одного и того же чтения быть не должно.
     */
    public function index(): JsonResponse
    {
        return response()->json($this->settings->resolve(app()->getLocale()));
    }
}
