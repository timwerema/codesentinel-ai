<?php

namespace App\Http\Controllers;

use App\Ai\ReviewProviders;
use App\Models\Analysis;
use App\Models\Finding;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /** Every number here is counted from this user's own rows. Nothing is invented. */
    public function __invoke(Request $request, ReviewProviders $providers): Response
    {
        $user = $request->user();
        $ids = $user->analyses()->pluck('id');

        $bySeverity = Finding::whereIn('analysis_id', $ids)->selectRaw('severity, count(*) as n')->groupBy('severity')->pluck('n', 'severity');
        $byStatus = $user->analyses()->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');

        return Inertia::render('dashboard', [
            'counts' => [
                'analyses' => $ids->count(),
                'completed' => (int) ($byStatus[Analysis::COMPLETED] ?? 0),
                'failed' => (int) ($byStatus[Analysis::FAILED] ?? 0),
                'in_progress' => (int) (($byStatus[Analysis::QUEUED] ?? 0) + ($byStatus[Analysis::RUNNING] ?? 0)),
                'findings' => (int) $bySeverity->sum(),
                'verified' => Finding::whereIn('analysis_id', $ids)->where('verified', true)->count(),
            ],
            'severities' => collect(Finding::SEVERITIES)->mapWithKeys(fn ($s) => [$s => (int) ($bySeverity[$s] ?? 0)]),
            'recent' => $user->analyses()->with('files:id,analysis_id,name,language')->withCount('findings')->latest()->limit(6)->get()
                ->map(fn (Analysis $a) => AnalysisController::row($a)),
            'ai_mode' => $providers->current()?->mode() ?? 'off',
        ]);
    }
}
