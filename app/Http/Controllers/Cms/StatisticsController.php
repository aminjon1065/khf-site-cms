<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ContentViewRecorder;
use App\Services\ContentViewsReport;
use App\Support\ContentTypes;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * «Статистика просмотров» — our own count of the site's readers, without
 * Google Analytics (owner decision, 2026-10-16).
 */
class StatisticsController extends Controller
{
    public function index(Request $request, ContentViewsReport $report): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User && collect(ContentViewRecorder::TYPES)
            ->contains(fn (string $type): bool => $user->can(ContentTypes::META[$type]['module'].'.view')), 403);

        return Inertia::render('statistics/index', [
            'report' => $report->summary($user, $request->integer('days', ContentViewsReport::PERIODS[0])),
            'periods' => ContentViewsReport::PERIODS,
        ]);
    }
}
