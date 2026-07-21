<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Models\Alert;
use App\Models\User;
use App\Services\AlertMapService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ControlController extends Controller
{
    public function __construct(private readonly AlertMapService $map) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('alerts.view'), 403);

        $snapshot = $this->map->snapshot('ru', $user);
        $alerts = Alert::query()->accessibleTo($user)
            ->active()
            ->with('regions')
            ->latest('starts_at')
            ->get();

        return Inertia::render('control/index', [
            'state' => $snapshot['state'],
            'regions' => $snapshot['regions'],
            'metrics' => [
                'active' => $alerts->count(),
                'critical' => $alerts->filter(fn (Alert $alert): bool => $alert->severity->value === 'critical')->count(),
                'ending_soon' => $alerts->filter(fn (Alert $alert): bool => $alert->ends_at !== null && $alert->ends_at->isBefore(now()->addDay()))->count(),
                'affected_regions' => collect($snapshot['regions'])->where('count', '>', 0)->count(),
            ],
            'alerts' => $alerts->map(fn (Alert $alert): array => [
                'id' => $alert->id,
                'title' => $alert->getTranslation('title', 'ru', false) ?: $alert->internal_title,
                'severity' => $alert->severity->value,
                'severity_label' => $alert->severity->label(),
                'regions' => $alert->territory_type === 'country'
                    ? 'Вся страна'
                    : $alert->regions->map(fn ($region): string => $region->getTranslation('name', 'ru'))->implode(', '),
                'ends_at' => $alert->ends_at?->isoFormat('D MMMM, HH:mm'),
                'url' => "/alerts/{$alert->id}/edit",
            ])->values()->all(),
        ]);
    }
}
