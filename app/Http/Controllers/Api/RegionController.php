<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\PublicRegionResource;
use App\Models\Region;
use App\Services\AlertMapService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Public region endpoints for the Next.js site: the live alert status per
 * region (risk map) and the regional-management directory (contacts page).
 */
class RegionController extends Controller
{
    public function __construct(private readonly AlertMapService $map) {}

    public function index(): JsonResponse
    {
        return response()->json(['data' => $this->map->regionStatuses(app()->getLocale())]);
    }

    /**
     * Regional-management directory: office name, address and contacts.
     */
    public function directory(Request $request): AnonymousResourceCollection
    {
        $regions = Region::query()
            ->with('districts')
            ->orderBy('sort');

        $perPage = min(max($request->integer('per_page', 20), 1), 50);

        return PublicRegionResource::collection($regions->paginate($perPage)->withQueryString());
    }
}
