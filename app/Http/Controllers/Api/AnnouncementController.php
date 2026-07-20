<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\PublicAnnouncementResource;
use App\Models\Announcement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Public announcements (vacancies & tenders) for the Next.js /announcements
 * page. Returns only publicly-visible items, open ones first.
 */
class AnnouncementController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Announcement::query()->public()->ordered();

        if ($kind = $request->string('kind')->toString()) {
            $query->where('kind', $kind);
        }

        return PublicAnnouncementResource::collection($query->get());
    }
}
