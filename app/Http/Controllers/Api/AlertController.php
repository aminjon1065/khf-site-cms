<?php

namespace App\Http\Controllers\Api;

use App\Enums\Channel;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\PublicAlertResource;
use App\Models\Alert;
use App\Services\AlertMapService;
use App\Support\PublicLocale;
use App\Support\PublicSlug;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Validation\Rule;

/**
 * Public emergency alerts for the Next.js /alerts pages, plus the aggregate
 * "active" snapshot used by the home banner and the region map.
 */
class AlertController extends Controller
{
    public function __construct(private readonly AlertMapService $map) {}

    /**
     * Currently-active alerts, most severe first.
     *
     * `channel` narrows them to the alerts the operator sent to that channel
     * in the wizard: the SOS app reads `?channel=sos_app`, the site's RSS feed
     * `?channel=rss`. Without it the list is every active alert, as on the
     * site itself.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $channel = $request->validate([
            'channel' => ['nullable', Rule::enum(Channel::class)],
        ])['channel'] ?? null;

        $query = Alert::query()
            ->select([
                'id',
                'slug',
                'severity',
                'hazard_type',
                'status',
                'title',
                'summary',
                'territory_type',
                'territory_note',
                'published_at',
                'starts_at',
                'ends_at',
            ])
            ->active()
            ->when(
                is_string($channel),
                fn (Builder $alerts): Builder => $alerts->whereJsonContains('channels', $channel),
            )
            ->with('regions:id,code,name');

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
            ->select([
                'id',
                'slug',
                'severity',
                'hazard_type',
                'status',
                'title',
                'summary',
                'body',
                'updates',
                'instructions',
                'contacts',
                'source',
                'territory_type',
                'territory_note',
                'published_at',
                'starts_at',
                'ends_at',
            ])
            ->public()
            ->with('regions:id,code,name');

        PublicLocale::available($query, 'title');

        $alert = PublicSlug::find($query, $slug);
        $this->loadDetailRelations($alert);

        return (new PublicAlertResource($alert))->withDetail();
    }

    /**
     * The districts named inside the affected regions and the safety guides
     * the operator linked. Only guides a citizen can open on this language
     * version: a link must not lead to a 404 or to another language.
     */
    private function loadDetailRelations(Alert $alert): void
    {
        $alert->setRelation('districts', $alert->districts()
            ->select(['districts.id', 'districts.region_id', 'districts.name'])
            ->orderBy('districts.sort')
            ->orderBy('districts.id')
            ->get());

        $instructions = $alert->relatedInstructions()
            ->select(['instructions.id', 'instructions.slug', 'instructions.name', 'instructions.summary', 'instructions.hazard_type'])
            ->public()
            ->ordered();
        PublicLocale::available($instructions->getQuery(), 'name');

        $alert->setRelation('relatedInstructions', $instructions->get());
    }
}
