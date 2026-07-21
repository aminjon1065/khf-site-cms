<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\PublicProjectResource;
use App\Models\Project;
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
        $query = Project::query()->public()->ordered()->with('media');

        if ($lifecycle = $request->string('lifecycle')->toString()) {
            $query->where('lifecycle_status', $lifecycle);
        }

        $perPage = min(max($request->integer('per_page', 20), 1), 50);

        return PublicProjectResource::collection($query->paginate($perPage)->withQueryString());
    }

    public function show(string $slug): JsonResource
    {
        $project = Project::query()
            ->public()
            ->with('media')
            ->where('slug', $slug)
            ->firstOrFail();

        return (new PublicProjectResource($project))->withDetail();
    }
}
