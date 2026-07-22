<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\PublicAlertResource;
use App\Models\Alert;
use App\Services\AlertMapService;
use App\Support\PublicLocale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public emergency alerts for the Next.js /alerts pages, plus the aggregate
 * "active" snapshot used by the home banner and the region map.
 */
class AlertController extends Controller
{
    public function __construct(private readonly AlertMapService $map) {}

    /**
     * Currently-active alerts, most severe first.
     */
    public function index(): AnonymousResourceCollection
    {
        $query = Alert::query()
            ->active()
            ->with('regions');

        PublicLocale::available($query, 'title');

        $alerts = $query->get()
            ->sortByDesc(fn (Alert $alert): int => $alert->severity->weight())
            ->values();

        return PublicAlertResource::collection($alerts);
    }

    /**
     * Global alert state + per-region status. Must be registered before the
     * `{slug}` route so "active" is not treated as a slug.
     */
    public function active(): JsonResponse
    {
        return response()->json(['data' => $this->map->snapshot(app()->getLocale())]);
    }

    public function show(string $slug): JsonResource
    {
        $query = Alert::query()
            ->public()
            ->with(['regions', 'districts'])
            ->where('slug', $slug);

        PublicLocale::available($query, 'title');

        $alert = $query->firstOrFail();

        return (new PublicAlertResource($alert))->withDetail();
    }
}
