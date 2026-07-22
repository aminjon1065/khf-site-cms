<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\PublicInstructionResource;
use App\Models\Instruction;
use App\Support\PublicLocale;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public safety-instruction catalogue for the Next.js /guides pages. Returns
 * only publicly-visible instructions, ordered (pinned first, then sort). The
 * catalogue is small, so the list is returned unpaginated.
 */
class InstructionController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Instruction::query()->public()->ordered()->with('media');
        PublicLocale::available($query, 'name');

        if ($request->boolean('priority')) {
            $query->where('is_priority', true);
        } elseif ($request->boolean('exclude_priority')) {
            $query->where('is_priority', false);
        }

        if ($hazard = $request->string('hazard')->toString()) {
            $query->where('hazard_type', $hazard);
        }

        $perPage = min(max($request->integer('per_page', 20), 1), 50);

        return PublicInstructionResource::collection($query->paginate($perPage)->withQueryString());
    }

    public function show(string $slug): JsonResource
    {
        $query = Instruction::query()
            ->public()
            ->with('media')
            ->where('slug', $slug);

        PublicLocale::available($query, 'name');

        $instruction = $query->firstOrFail();

        return (new PublicInstructionResource($instruction))->withSections();
    }
}
