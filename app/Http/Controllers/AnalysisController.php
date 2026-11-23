<?php

namespace App\Http\Controllers;

use App\Analysis\AnalysisCreator;
use App\Analysis\RejectedSource;
use App\Analysis\SourceIntake;
use App\Http\Requests\StoreAnalysisRequest;
use App\Jobs\RunAnalysis;
use App\Models\Analysis;
use App\Models\Finding;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AnalysisController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:80'],
            'status' => ['nullable', 'in:queued,running,completed,failed'],
        ]);

        $analyses = $request->user()->analyses()
            ->when($filters['q'] ?? null, function ($q, $term) {
                $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], Str::lower($term)).'%';
                $q->where(fn ($w) => $w->whereRaw('lower(title) like ?', [$like])
                    ->orWhereHas('files', fn ($f) => $f->whereRaw('lower(name) like ?', [$like])));
            })
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->with('files:id,analysis_id,name,language')
            ->withCount('findings')
            ->latest()
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Analysis $a) => self::row($a));

        return Inertia::render('analyses/index', ['analyses' => $analyses, 'filters' => ['q' => $filters['q'] ?? '', 'status' => $filters['status'] ?? '']]);
    }

    public function create(SourceIntake $intake): Response
    {
        return Inertia::render('analyses/create', [
            'languages' => $intake->languages(),
            'limits' => config('codesentinel.limits'),
        ]);
    }

    public function store(StoreAnalysisRequest $request, SourceIntake $intake, AnalysisCreator $creator): RedirectResponse
    {
        $user = $request->user();
        $key = 'analyses:'.$user->id;
        if (RateLimiter::tooManyAttempts($key, config('codesentinel.limits.analyses_per_hour'))) {
            throw ValidationException::withMessages(['mode' => 'You have reached the hourly limit for new analyses. Try again in '.ceil(RateLimiter::availableIn($key) / 60).' minutes.']);
        }

        try {
            if ($request->input('mode') === 'paste') {
                $field = 'code';
                $files = [$intake->accept('pasted.'.$request->input('language'), (string) $request->input('code'))];
            } else {
                $field = 'files';
                $files = [];
                foreach ($request->file('files', []) as $upload) {
                    // Read as bytes. The upload is never moved under public/, included or executed.
                    $files[] = $intake->accept($upload->getClientOriginalName(), (string) file_get_contents($upload->getRealPath()));
                }
            }
            $title = trim((string) $request->input('title')) ?: ($files[0]->name ?? 'Analysis').(count($files) > 1 ? ' + '.(count($files) - 1).' more' : '');
            $analysis = $creator->create($user, Str::limit($title, 150, ''), $request->input('mode'), $files);
        } catch (RejectedSource $e) {
            throw ValidationException::withMessages([$field ?? 'mode' => $e->getMessage()]);
        }

        RateLimiter::hit($key, 3600);

        return to_route('analyses.show', $analysis);
    }

    public function show(Request $request, Analysis $analysis): Response
    {
        Gate::authorize('view', $analysis);
        $analysis->load(['files', 'findings']);

        return Inertia::render('analyses/show', [
            'analysis' => [
                ...self::row($analysis),
                'error' => $analysis->error,
                'scanners' => $analysis->scanners,
                'ai_mode' => $analysis->ai_mode,
                'ai_summary' => $analysis->ai_summary,
                'ai_usage' => $analysis->ai_usage,
                'can_retry' => $request->user()->can('retry', $analysis),
            ],
            'files' => $analysis->files->map(fn ($f) => [
                'id' => $f->id, 'name' => $f->name, 'language' => $f->language, 'line_count' => $f->line_count, 'size_bytes' => $f->size_bytes,
                'contents' => $f->contents(),
            ])->values(),
            'findings' => $analysis->findings
                ->sortBy(fn (Finding $f) => [Finding::rank($f->severity), $f->verified ? 0 : 1, $f->analysis_file_id, $f->line_start])
                ->map(fn (Finding $f) => $f->only(['id', 'analysis_file_id', 'source', 'verified', 'also_reported_by', 'rule_id', 'category', 'severity', 'title', 'explanation', 'remediation', 'suggested_code', 'line_start', 'line_end']))
                ->values(),
        ]);
    }

    /** Polled by the detail page while an analysis is queued or running. */
    public function status(Analysis $analysis): JsonResponse
    {
        Gate::authorize('view', $analysis);

        return response()->json(['status' => $analysis->status])->header('Cache-Control', 'no-store');
    }

    public function retry(Analysis $analysis): RedirectResponse
    {
        Gate::authorize('retry', $analysis);
        $analysis->update(['status' => Analysis::QUEUED, 'error' => null, 'started_at' => null, 'finished_at' => null]);
        RunAnalysis::dispatch($analysis->id);

        return to_route('analyses.show', $analysis);
    }

    public function destroy(Analysis $analysis): RedirectResponse
    {
        Gate::authorize('delete', $analysis);
        Storage::disk('local')->deleteDirectory('analyses/'.$analysis->public_id);
        $analysis->delete();

        return to_route('analyses.index');
    }

    public static function row(Analysis $a): array
    {
        return [
            'id' => $a->public_id,
            'title' => $a->title,
            'origin' => $a->origin,
            'status' => $a->status,
            'is_demo' => $a->is_demo,
            'created_at' => $a->created_at->toIso8601String(),
            'seconds' => $a->started_at && $a->finished_at ? round($a->started_at->diffInMilliseconds($a->finished_at) / 1000, 1) : null,
            'files' => $a->files->map(fn ($f) => ['name' => $f->name, 'language' => $f->language])->values(),
            'findings_count' => $a->findings_count ?? $a->findings->count(),
        ];
    }
}
