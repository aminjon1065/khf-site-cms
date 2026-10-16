<?php

namespace App\Http\Controllers\Api;

use App\Enums\SubmissionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Submission\SubmissionRequest;
use App\Http\Requests\Submission\SubmissionStatusRequest;
use App\Models\Region;
use App\Models\Submission;
use App\Support\PublicApiLabels;
use App\Support\SubmissionAccessCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

/**
 * Public citizen submission intake (electronic reception). Rate-limited at the
 * route; a honeypot silently absorbs bots. Returns a real tracking number.
 */
class SubmissionController extends Controller
{
    /**
     * Checked when there is no such appeal, so the answer takes as long as
     * a wrong code does (a bcrypt hash of a random string).
     */
    private const DECOY_HASH = '$2y$12$kRFG35565w2zHfuo.ukZhumgDW9ZFLmwhq0roM1ju2OPM1796HBM6';

    public function store(SubmissionRequest $request): JsonResponse
    {
        // Honeypot: a real user never fills this hidden field. Return a
        // plausible success without persisting anything.
        if (filled($request->input('website'))) {
            return response()->json([
                'tracking_number' => $this->fakeTracking(),
                'access_code' => SubmissionAccessCode::generate(),
            ], 201);
        }

        $accessCode = SubmissionAccessCode::generate();

        /** @var array<string, mixed> $data */
        $data = $request->safe()->only(['name', 'email', 'phone', 'topic', 'message', 'region_id']);

        if ($request->filled('region')) {
            $data['region_id'] = Region::query()->where('code', $request->string('region')->toString())->value('id');
        }

        $submission = new Submission([
            ...$data,
            'consent' => true,
            'status' => SubmissionStatus::New,
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
        ]);
        $submission->access_code_hash = Hash::make(SubmissionAccessCode::normalize($accessCode));
        $submission->save();

        return response()->json([
            'tracking_number' => $submission->tracking_number,
            // Shown once, never stored in the clear: with the number it lets
            // the citizen check the status on the site.
            'access_code' => $accessCode,
        ], 201);
    }

    /**
     * Status of an appeal for the citizen: by its number and check code,
     * never by the number alone — numbers go in sequence. A wrong code and
     * an unknown number get the same answer in the same time, so neither
     * tells whether such an appeal exists.
     */
    public function status(SubmissionStatusRequest $request): JsonResponse
    {
        $number = SubmissionAccessCode::trackingNumber($request->string('tracking_number')->toString());
        $code = SubmissionAccessCode::normalize($request->string('access_code')->toString());

        $submission = $number !== null
            ? Submission::query()->where('tracking_number', $number)->whereNotNull('access_code_hash')->first()
            : null;

        $matches = Hash::check($code, $submission->access_code_hash ?? self::DECOY_HASH);

        if (! $submission instanceof Submission || ! $matches) {
            return response()->json(['message' => $this->notFoundMessage()], 404);
        }

        $locale = app()->getLocale();
        $stage = $submission->status->publicStage();

        return response()->json([
            'data' => [
                'tracking_number' => $submission->tracking_number,
                'status_code' => $stage,
                'status' => PublicApiLabels::get('submission_status', $stage, $locale),
                'received_at' => $submission->created_at?->toIso8601String(),
                'updated_at' => $submission->updated_at?->toIso8601String(),
            ],
        ]);
    }

    private function notFoundMessage(): string
    {
        return match (app()->getLocale()) {
            'tg' => 'Муроҷиат ёфт нашуд. Рақам ва рамзи санҷишро тафтиш кунед.',
            'en' => 'Appeal not found. Check the number and the check code.',
            default => 'Обращение не найдено. Проверьте номер и код проверки.',
        };
    }

    private function fakeTracking(): string
    {
        return 'КЧС-'.now()->format('Y').'-'.str_pad((string) random_int(1, 99999), 5, '0', STR_PAD_LEFT);
    }
}
