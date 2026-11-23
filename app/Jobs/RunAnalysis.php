<?php

namespace App\Jobs;

use App\Ai\AiReviewFailed;
use App\Ai\ReviewProviders;
use App\Ai\ReviewRequest;
use App\Analysis\Deduplicator;
use App\Analysis\Workspace;
use App\Models\Analysis;
use App\Scanners\GitleaksScanner;
use App\Scanners\RawFinding;
use App\Scanners\SemgrepScanner;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

/**
 * The whole pipeline for one analysis: copy files to a throwaway workspace, run the scanners,
 * merge duplicates, ask the AI provider for extra suggestions, store everything.
 *
 * A scanner or the AI step failing does not fail the analysis: what worked is kept and what
 * failed is recorded and shown. The analysis only fails when no scanner could run at all.
 */
class RunAnalysis implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(public int $analysisId) {}

    public function handle(SemgrepScanner $semgrep, GitleaksScanner $gitleaks, Deduplicator $deduplicator, ReviewProviders $providers): void
    {
        $analysis = Analysis::with('files')->find($this->analysisId);
        if (! $analysis || $analysis->status !== Analysis::QUEUED) {
            return; // deleted, or already picked up
        }
        $analysis->update(['status' => Analysis::RUNNING, 'started_at' => now(), 'error' => null]);

        $workspace = Workspace::for($analysis);
        try {
            $results = [$semgrep->scan($workspace->path), $gitleaks->scan($workspace->path)];
        } finally {
            $workspace->destroy();
        }

        $scanners = [];
        $raw = [];
        foreach ($results as $result) {
            $scanners[$result->tool] = $result->summary();
            array_push($raw, ...$result->findings);
        }

        if (collect($results)->every(fn ($r) => ! $r->ok)) {
            $analysis->update([
                'status' => Analysis::FAILED, 'finished_at' => now(), 'scanners' => $scanners,
                'error' => 'No scanner could run, so there is nothing to show. '.collect($results)->map(fn ($r) => ucfirst($r->tool).': '.$r->error)->implode(' '),
            ]);

            return;
        }

        $merged = $deduplicator->merge($raw);
        [$aiMode, $aiSummary, $aiUsage, $suggestions] = $this->aiReview($analysis, $providers, array_values($merged));
        $merged = $deduplicator->merge([...array_values($merged), ...$suggestions]);

        DB::transaction(function () use ($analysis, $merged, $scanners, $aiMode, $aiSummary, $aiUsage) {
            $fileIds = $analysis->files->pluck('id', 'name');
            $analysis->findings()->delete();
            foreach ($merged as $fingerprint => $f) {
                $analysis->findings()->create([
                    'analysis_file_id' => $fileIds[$f->file] ?? null,
                    'source' => $f->source, 'verified' => $f->verified,
                    'also_reported_by' => $f->alsoReportedBy ?: null,
                    'rule_id' => $f->ruleId, 'category' => $f->category, 'severity' => $f->severity,
                    'title' => $f->title, 'explanation' => $f->explanation, 'remediation' => $f->remediation,
                    'suggested_code' => $f->suggestedCode,
                    'line_start' => $f->lineStart, 'line_end' => $f->lineEnd ?? $f->lineStart,
                    'fingerprint' => $fingerprint,
                ]);
            }
            $analysis->update([
                'status' => Analysis::COMPLETED, 'finished_at' => now(), 'scanners' => $scanners,
                'ai_mode' => $aiMode, 'ai_summary' => $aiSummary, 'ai_usage' => $aiUsage,
            ]);
        });
    }

    /**
     * @param  list<RawFinding>  $scannerFindings
     * @return array{0: string, 1: ?string, 2: ?array, 3: list<RawFinding>}
     */
    private function aiReview(Analysis $analysis, ReviewProviders $providers, array $scannerFindings): array
    {
        $provider = $providers->current();
        if ($provider === null) {
            return ['none', null, null, []];
        }

        // Real AI calls cost money: cap them per user per hour. Demo mode is free and not capped.
        if ($provider->mode() === 'claude') {
            $key = 'ai-review:'.$analysis->user_id;
            if (RateLimiter::tooManyAttempts($key, config('codesentinel.ai.reviews_per_hour'))) {
                return ['claude', 'AI review skipped: the hourly limit for AI reviews was reached. Scanner results are complete.', ['skipped' => 'rate_limited'], []];
            }
            RateLimiter::hit($key, 3600);
        }

        try {
            $result = $provider->review(new ReviewRequest(
                $analysis->files->map(fn ($f) => ['name' => $f->name, 'language' => $f->language, 'contents' => $f->contents()])->all(),
                $scannerFindings,
            ));

            return [$result->mode, $result->summary, $result->usage, $result->suggestions];
        } catch (AiReviewFailed $e) {
            return [$provider->mode(), 'AI review did not complete: '.$e->getMessage().' Scanner results are complete.', ['failed' => true], []];
        } catch (Throwable $e) {
            Log::error('AI review crashed', ['analysis' => $analysis->public_id, 'exception' => $e::class]);

            return [$provider->mode(), 'AI review did not complete because of an unexpected error. Scanner results are complete.', ['failed' => true], []];
        }
    }

    public function failed(?Throwable $e): void
    {
        Analysis::whereKey($this->analysisId)->whereIn('status', [Analysis::QUEUED, Analysis::RUNNING])->update([
            'status' => Analysis::FAILED, 'finished_at' => now(),
            'error' => 'The analysis stopped unexpectedly. You can run it again.',
        ]);
    }
}
