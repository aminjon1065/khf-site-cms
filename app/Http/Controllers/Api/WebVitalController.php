<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreWebVitalRequest;
use App\Services\WebVitalRecorder;
use Illuminate\Http\JsonResponse;

class WebVitalController extends Controller
{
    public function __invoke(StoreWebVitalRequest $request, WebVitalRecorder $recorder): JsonResponse
    {
        /** @var array{name: string, value: float|int, id: string, path: string, locale: string, device: string, navigation_type: string} $payload */
        $payload = $request->validated();
        $recorder->record($payload);

        return response()->json(['accepted' => true], 202);
    }
}
