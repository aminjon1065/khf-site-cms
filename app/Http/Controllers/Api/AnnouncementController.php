<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\PublicAnnouncementResource;
use App\Models\Announcement;
use App\Support\PublicLocale;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public announcements (vacancies & tenders) for the Next.js /announcements
 * page. Returns only publicly-visible items, open ones first.
 */
class AnnouncementController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Announcement::query()->public()->ordered();
        PublicLocale::available($query, 'title');

        if ($kind = $request->string('kind')->toString()) {
            $query->where('kind', $kind);
        }

        $perPage = min(max($request->integer('per_page', 20), 1), 50);

        return PublicAnnouncementResource::collection($query->paginate($perPage)->withQueryString());
    }

    public function show(string $slug): JsonResource
    {
        $query = Announcement::query()
            ->public()
            ->where('slug', $slug);

        PublicLocale::available($query, 'title');

        $announcement = $query->firstOrFail();

        return new PublicAnnouncementResource($announcement);
    }
}
