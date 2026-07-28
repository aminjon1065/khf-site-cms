<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cms\StoreUsabilitySessionRequest;
use App\Models\UsabilitySession;
use App\Models\User;
use App\Services\UsabilityReportService;
use App\Support\UsabilityStudy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UsabilityController extends Controller
{
    public function index(Request $request, UsabilityReportService $report): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('users.view'), 403);

        return Inertia::render('usability/index', [
            'tasks' => collect(UsabilityStudy::TASKS)
                ->map(fn (array $definition, string $key): array => ['key' => $key, ...$definition])
                ->values()
                ->all(),
            ...$report->report(),
        ]);
    }

    public function store(
        StoreUsabilitySessionRequest $request,
        UsabilityReportService $report,
    ): RedirectResponse {
        /** @var array{
         *     participant_code: string,
         *     role: string,
         *     experience_level: string,
         *     tasks: array<string, array{completed: bool, assisted: bool, duration_seconds: int, irreversible_error: bool}>,
         *     sus_responses: list<int>,
         *     notes?: string|null,
         *     started_at: string,
         *     completed_at: string
         * } $validated
         */
        $validated = $request->validated();

        UsabilitySession::query()->create([
            ...$validated,
            'sus_score' => UsabilityStudy::susScore($validated['sus_responses']),
            'facilitator_id' => $request->user()?->getKey(),
        ]);
        $report->forget();

        return back()->with('success', 'Сессия проверки удобства сохранена.');
    }
}
