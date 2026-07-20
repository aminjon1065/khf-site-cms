<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\PublicDocumentResource;
use App\Models\Document;
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

        if ($type = $request->string('type')->toString()) {
            $query->where('doc_type', $type);
        }

        if ($section = $request->string('section')->toString()) {
            $query->where('section', $section);
        }

        if ($search = $request->string('q')->toString()) {
            $query->where(function (Builder $q) use ($search): void {
                $q->where('name->ru', 'like', "%{$search}%")
                    ->orWhere('name->tg', 'like', "%{$search}%")
                    ->orWhere('number', 'like', "%{$search}%");
            });
        }

        return PublicDocumentResource::collection($query->get());
    }
}
