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
use Illuminate\Http\JsonResponse;

/**
 * Home-page composition endpoint. Returns the enabled home blocks (in editor
 * order, with their per-block limits) plus the denormalized data each block
 * renders — a single request that fully drives the public home page.
 */
class HomeController extends Controller
{
    public function __construct(private readonly AlertMapService $map) {}

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

        $activeAlerts = Alert::query()->active()->with('regions')->get()
            ->sortByDesc(fn (Alert $alert): int => $alert->severity->weight())
            ->take($limitOf('active_alerts', 3))
            ->values();

        $news = News::query()->public()->with(['category', 'media'])
            ->orderByDesc('is_pinned')->orderByDesc('published_at')
            ->limit($limitOf('latest_news', 5))->get();

        $instructions = Instruction::query()->public()->ordered()->with('media')
            ->limit($limitOf('instructions', 4))->get();

        $documents = Document::query()->public()->ordered()->with('media')
            ->limit($limitOf('documents', 3))->get();

        $announcements = Announcement::query()->public()->ordered()
            ->limit($limitOf('announcements', 3))->get();

        $projects = Project::query()->public()->ordered()->with('media')
            ->limit($limitOf('projects', 2))->get();

        return response()->json(['data' => [
            'blocks' => $blocks->map(fn (HomeBlock $block): array => [
                'type' => $block->type,
                'title' => $block->getTranslation('title', $locale, true),
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
        ]]);
    }
}
