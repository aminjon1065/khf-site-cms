<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\PublicAlertResource;
use App\Http\Resources\Api\PublicAnnouncementResource;
use App\Http\Resources\Api\PublicDocumentResource;
use App\Http\Resources\Api\PublicInstructionResource;
use App\Http\Resources\Api\PublicNewsResource;
use App\Http\Resources\Api\PublicProjectResource;
use App\Models\Alert;
use App\Models\Announcement;
use App\Models\Document;
use App\Models\HomeBlock;
use App\Models\Instruction;
use App\Models\News;
use App\Models\Project;
use App\Services\AlertMapService;
use App\Services\PublicSettingsService;
use App\Support\PublicLocale;
use Illuminate\Http\JsonResponse;

/**
 * Home-page composition endpoint. Returns the enabled home blocks (in editor
 * order, with their per-block limits) plus the denormalized data each block
 * renders — a single request that fully drives the public home page.
 */
class HomeController extends Controller
{
    public function __construct(
        private readonly AlertMapService $map,
        private readonly PublicSettingsService $settings,
    ) {}

    public function index(): JsonResponse
    {
        $locale = app()->getLocale();
        $blocks = HomeBlock::query()->where('enabled', true)->orderBy('sort')->get();

        $limitOf = function (string $type, int $default) use ($blocks): int {
            $block = $blocks->firstWhere('type', $type);
            $config = is_array($block?->config) ? $block->config : [];

            return (int) ($config['limit'] ?? $default);
        };

        $snapshot = $this->map->snapshot($locale);
        $settings = $this->settings->resolve($locale)['data'];

        $activeAlertsQuery = Alert::query()->active()->with('regions');
        PublicLocale::available($activeAlertsQuery, 'title', $locale);
        $activeAlerts = $activeAlertsQuery->get()
            ->sortByDesc(fn (Alert $alert): int => $alert->severity->weight())
            ->take($limitOf('active_alerts', 3))
            ->values();

        $newsQuery = News::query()->public()->with(['category', 'media'])
            ->where('show_on_home', true)
            ->orderByDesc('is_pinned')->orderByDesc('published_at');
        PublicLocale::available($newsQuery, 'title', $locale);
        $news = $newsQuery
            ->limit($limitOf('latest_news', 5))->get();

        $instructionsQuery = Instruction::query()->public()->ordered()->with('media');
        PublicLocale::available($instructionsQuery, 'name', $locale);
        $instructions = $instructionsQuery
            ->limit($limitOf('instructions', 4))->get();

        $documentsQuery = Document::query()->public()->ordered()->with('media');
        PublicLocale::available($documentsQuery, 'name', $locale);
        $documents = $documentsQuery
            ->limit($limitOf('documents', 3))->get();

        $announcementsQuery = Announcement::query()->public()->ordered();
        PublicLocale::available($announcementsQuery, 'title', $locale);
        $announcements = $announcementsQuery
            ->limit($limitOf('announcements', 3))->get();

        $projectsQuery = Project::query()->public()->ordered()->with('media');
        PublicLocale::available($projectsQuery, 'title', $locale);
        $projects = $projectsQuery
            ->limit($limitOf('projects', 2))->get();

        return response()->json(['data' => [
            'blocks' => $blocks->map(fn (HomeBlock $block): array => [
                'type' => $block->type,
                'title' => $block->getTranslation('title', $locale, false),
                'config' => $block->config ?? [],
            ])->values()->all(),
            'alerts' => [
                'state' => $snapshot['state'],
                'count' => $snapshot['count'],
                'regions' => $snapshot['regions'],
                'items' => PublicAlertResource::collection($activeAlerts)->resolve(),
            ],
            'news' => PublicNewsResource::collection($news)->resolve(),
            'instructions' => PublicInstructionResource::collection($instructions)->resolve(),
            'documents' => PublicDocumentResource::collection($documents)->resolve(),
            'announcements' => PublicAnnouncementResource::collection($announcements)->resolve(),
            'projects' => PublicProjectResource::collection($projects)->resolve(),
            'emergency_contacts' => [
                'emergency_number' => data_get($settings, 'org.emergency_number', '112'),
                'trust_phone' => data_get($settings, 'org.trust_phone'),
                'duty_phone' => data_get($settings, 'contacts.duty_phone'),
                'email' => data_get($settings, 'org.email'),
                'services' => data_get($settings, 'emergency_services', []),
            ],
        ]]);
    }
}
