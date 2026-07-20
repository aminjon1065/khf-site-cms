<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\PublicInstructionResource;
use App\Models\Instruction;
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

        if ($request->boolean('priority')) {
            $query->where('is_priority', true);
        }

        if ($hazard = $request->string('hazard')->toString()) {
            $query->where('hazard_type', $hazard);
        }

        return PublicInstructionResource::collection($query->get());
    }

    public function show(string $slug): JsonResource
    {
        $instruction = Instruction::query()
            ->public()
            ->with('media')
            ->where('slug', $slug)
            ->firstOrFail();

        return (new PublicInstructionResource($instruction))->withSections();
    }
}
