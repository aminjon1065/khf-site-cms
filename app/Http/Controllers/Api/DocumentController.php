<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\PublicDocumentResource;
use App\Models\Document;
use App\Support\PublicLocale;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Public document library for the Next.js /documents page. Returns only
 * publicly-visible documents (newest first) with per-language download links.
 */
class DocumentController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Document::query()->public()->ordered()->with('media');
        PublicLocale::available($query, 'name');

        if ($type = $request->string('type')->toString()) {
            $query->where('doc_type', $type);
        }

        if ($section = $request->string('section')->toString()) {
            $query->where('section', $section);
        }

        if ($search = $request->string('q')->toString()) {
            $locale = app()->getLocale();
            $query->where(function (Builder $q) use ($search, $locale): void {
                $q->where("name->{$locale}", 'like', "%{$search}%")
                    ->orWhere('number', 'like', "%{$search}%");
            });
        }

        $perPage = min(max($request->integer('per_page', 20), 1), 50);

        return PublicDocumentResource::collection($query->paginate($perPage)->withQueryString());
    }
}
