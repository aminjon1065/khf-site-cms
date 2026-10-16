<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreContentViewsRequest;
use App\Services\ContentViewRecorder;
use Illuminate\Http\JsonResponse;

class ContentViewController extends Controller
{
    public function __invoke(StoreContentViewsRequest $request, ContentViewRecorder $recorder): JsonResponse
    {
        /** @var list<array{type: string, slug: string, locale: string, count: int}> $views */
        $views = $request->validated('views');

        return response()->json(['recorded' => $recorder->record($views)], 202);
    }
}
