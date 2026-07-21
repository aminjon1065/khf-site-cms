<?php

namespace App\Http\Controllers\Api;

use App\Enums\SubmissionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Submission\SubmissionRequest;
use App\Models\Submission;
use Illuminate\Http\JsonResponse;

/**
 * Public citizen submission intake (electronic reception). Rate-limited at the
 * route; a honeypot silently absorbs bots. Returns a real tracking number.
 */
class SubmissionController extends Controller
{
    public function store(SubmissionRequest $request): JsonResponse
    {
        // Honeypot: a real user never fills this hidden field. Return a
        // plausible success without persisting anything.
        if (filled($request->input('website'))) {
            return response()->json(['tracking_number' => $this->fakeTracking()], 201);
        }

        /** @var array<string, mixed> $data */
        $data = $request->safe()->only(['name', 'email', 'phone', 'topic', 'message', 'region_id']);

        $submission = Submission::create([
            ...$data,
            'consent' => true,
            'status' => SubmissionStatus::New,
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
        ]);

        return response()->json([
            'tracking_number' => $submission->tracking_number,
        ], 201);
    }

    private function fakeTracking(): string
    {
        return 'КЧС-'.now()->format('Y').'-'.str_pad((string) random_int(1, 99999), 5, '0', STR_PAD_LEFT);
    }
}
