<?php

namespace App\Http\Controllers\Api;

use App\Enums\AnnouncementKind;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\PublicProjectResource;
use App\Models\Project;
use App\Support\PublicLocale;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public projects & programmes for the Next.js /projects pages. Returns only
 * publicly-visible projects, in display order.
 */
class ProjectController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Project::query()
            ->select([
                'id',
                'slug',
                'title',
                'summary',
                'lifecycle_status',
                'years',
                'partner',
                'budget',
            ])
            ->public()
            ->ordered()
            ->with('media');
        PublicLocale::available($query, 'title');

        if ($lifecycle = $request->string('lifecycle')->toString()) {
            $query->where('lifecycle_status', $lifecycle);
        }

        $perPage = min(max($request->integer('per_page', 20), 1), 50);

        return PublicProjectResource::collection($query->paginate($perPage)->withQueryString());
    }

    public function show(string $slug): JsonResource
    {
        $query = Project::query()
            ->select([
                'id',
                'slug',
                'title',
                'summary',
                'body',
                'lifecycle_status',
                'code',
                'years',
                'customer',
                'partner',
                'budget',
                'goals',
                'timeline',
                'direction',
            ])
            ->public()
            // Тендеры проекта — только на детальной, в списке они не нужны.
            // Ограничение и порядок задаются здесь, а не в ресурсе: иначе
            // ресурс тянул бы связь запросом на каждую строку.
            ->with(['media', 'announcements' => fn ($query) => $query
                ->select(['id', 'project_id', 'slug', 'kind', 'title', 'deadline'])
                ->where('kind', AnnouncementKind::Tender)
                ->public()
                ->ordered()
                ->limit(5)])
            ->where('slug', $slug);

        PublicLocale::available($query, 'title');

        $project = $query->firstOrFail();

        return (new PublicProjectResource($project))->withDetail();
    }
}
