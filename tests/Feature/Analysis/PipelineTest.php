<?php

namespace Tests\Feature\Analysis;

use App\Ai\ClaudeGateway;
use App\Analysis\AnalysisCreator;
use App\Analysis\SourceFile;
use App\Jobs\RunAnalysis;
use App\Models\Analysis;
use App\Models\User;
use App\Scanners\GitleaksScanner;
use App\Scanners\ScanResult;
use App\Scanners\SemgrepScanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\Support\FakeGateway;
use Tests\Support\FakeScanner;
use Tests\TestCase;

/** The queued job from start to finish, with the scanner programs replaced by canned results. */
class PipelineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function scanners(ScanResult $semgrep, ScanResult $gitleaks): void
    {
        $this->mock(SemgrepScanner::class, fn ($m) => $m->shouldReceive('scan')->once()->andReturn($semgrep));
        $this->mock(GitleaksScanner::class, fn ($m) => $m->shouldReceive('scan')->once()->andReturn($gitleaks));
    }

    /** The queue is "sync" in tests, so creating the analysis runs the whole job. */
    private function analyse(): Analysis
    {
        return app(AnalysisCreator::class)->create(User::factory()->create(), 'Pipeline', 'upload', [
            new SourceFile('app.py', 'python', "import os\nKEY = 'abc'\nos.system(cmd)\n"),
            new SourceFile('b.js', 'javascript', "eval(x)\n"),
        ])->fresh();
    }

    public function test_findings_from_both_scanners_are_merged_stored_and_linked_to_their_files(): void
    {
        $this->scanners(
            FakeScanner::ok('semgrep', [
                FakeScanner::finding('semgrep', 'app.py', 3, 'py-os-system'),
                FakeScanner::finding('semgrep', 'app.py', 2, 'hardcoded-key', 'medium', 'secret'),
                FakeScanner::finding('semgrep', 'b.js', 1, 'js-eval'),
            ]),
            FakeScanner::ok('gitleaks', [FakeScanner::finding('gitleaks', 'app.py', 2, 'generic-api-key', 'high', 'secret')]),
        );

        $analysis = $this->analyse();

        $this->assertSame(Analysis::COMPLETED, $analysis->status);
        $this->assertNotNull($analysis->started_at);
        $this->assertNotNull($analysis->finished_at);
        $this->assertSame(['ok', 3], [$analysis->scanners['semgrep']['status'], $analysis->scanners['semgrep']['findings']]);

        $verified = $analysis->findings()->where('verified', true)->get();
        $this->assertCount(3, $verified, 'the secret reported by both tools is stored once');
        $secret = $verified->firstWhere('category', 'secret');
        $this->assertSame(['gitleaks', 'high', ['semgrep']], [$secret->source, $secret->severity, $secret->also_reported_by]);
        $this->assertSame('b.js', $verified->firstWhere('rule_id', 'js-eval')->file->name);

        // No API key in tests: the AI step is the labelled demo, stored as unverified.
        $this->assertSame('demo', $analysis->ai_mode);
        $this->assertStringContainsString('no AI model was called', $analysis->ai_summary);
        $this->assertSame(2, $analysis->findings()->where('verified', false)->where('source', 'ai')->count());
    }

    public function test_one_scanner_failing_keeps_the_others_results_and_is_recorded(): void
    {
        $this->scanners(FakeScanner::failed('semgrep', 'The scanner took too long and was stopped.'), FakeScanner::ok('gitleaks', [FakeScanner::finding('gitleaks', 'app.py', 2, 'generic-api-key', 'high', 'secret')]));

        $analysis = $this->analyse();

        $this->assertSame(Analysis::COMPLETED, $analysis->status);
        $this->assertSame(['failed', 'The scanner took too long and was stopped.'], [$analysis->scanners['semgrep']['status'], $analysis->scanners['semgrep']['error']]);
        $this->assertSame(1, $analysis->findings()->where('verified', true)->count());
    }

    public function test_when_no_scanner_can_run_the_analysis_fails_with_the_reason_and_stores_no_findings(): void
    {
        $this->scanners(FakeScanner::failed('semgrep'), FakeScanner::failed('gitleaks'));

        $analysis = $this->analyse();

        $this->assertSame(Analysis::FAILED, $analysis->status);
        $this->assertStringContainsString('No scanner could run', $analysis->error);
        $this->assertSame(0, $analysis->findings()->count(), 'no demo or AI findings are shown for a scan that did not happen');
    }

    public function test_real_ai_suggestions_are_stored_as_unverified_and_a_scanner_finding_wins_a_tie(): void
    {
        config(['codesentinel.ai.mode' => 'auto', 'codesentinel.ai.api_key' => 'sk-test-not-real']);
        $this->app->instance(ClaudeGateway::class, new FakeGateway(['summary' => 'Two things to check.', 'suggestions' => [
            ['file' => 'app.py', 'line_start' => 1, 'line_end' => 1, 'category' => 'maintainability', 'severity' => 'low', 'title' => 'Unused import', 'explanation' => 'This looks unused.', 'remediation' => 'Remove it.', 'suggested_code' => null],
        ]]));
        $this->scanners(FakeScanner::ok('semgrep', [FakeScanner::finding('semgrep', 'app.py', 3, 'py-os-system')]), FakeScanner::ok('gitleaks'));

        $analysis = $this->analyse();

        $this->assertSame('claude', $analysis->ai_mode);
        $this->assertSame('claude-test', $analysis->ai_usage['model']);
        $ai = $analysis->findings()->where('source', 'ai')->sole();
        $this->assertFalse($ai->verified);
        $this->assertSame('Unused import', $ai->title);
        $this->assertSame(1, $analysis->findings()->where('verified', true)->count());
    }

    public function test_an_ai_failure_never_loses_the_scanner_results_or_leaks_details(): void
    {
        config(['codesentinel.ai.mode' => 'auto', 'codesentinel.ai.api_key' => 'sk-test-not-real']);
        $this->app->instance(ClaudeGateway::class, new FakeGateway('I refuse to answer in JSON. Also the key is sk-test-not-real'));
        $this->scanners(FakeScanner::ok('semgrep', [FakeScanner::finding('semgrep', 'app.py', 3, 'py-os-system')]), FakeScanner::ok('gitleaks'));

        $analysis = $this->analyse();

        $this->assertSame(Analysis::COMPLETED, $analysis->status);
        $this->assertSame(1, $analysis->findings()->count());
        $this->assertStringContainsString('AI review did not complete', $analysis->ai_summary);
        $this->assertStringNotContainsString('sk-test', $analysis->ai_summary);
    }

    public function test_real_ai_reviews_are_capped_per_user_per_hour(): void
    {
        config(['codesentinel.ai.mode' => 'auto', 'codesentinel.ai.api_key' => 'sk-test-not-real', 'codesentinel.ai.reviews_per_hour' => 1]);
        $gateway = new FakeGateway(['summary' => 'Fine.', 'suggestions' => []]);
        $this->app->instance(ClaudeGateway::class, $gateway);
        $user = User::factory()->create();
        $submit = function () use ($user) {
            $this->mock(SemgrepScanner::class, fn ($m) => $m->shouldReceive('scan')->andReturn(FakeScanner::ok('semgrep')));
            $this->mock(GitleaksScanner::class, fn ($m) => $m->shouldReceive('scan')->andReturn(FakeScanner::ok('gitleaks')));

            return app(AnalysisCreator::class)->create($user, 'A', 'paste', [new SourceFile('a.py', 'python', "x = 1\n")])->fresh();
        };

        $this->assertSame('Fine.', $submit()->ai_summary);
        $this->assertStringContainsString('hourly limit', $submit()->ai_summary);
        $this->assertSame(1, $gateway->calls);
    }

    public function test_a_crashed_job_marks_the_analysis_failed_and_it_can_be_run_again(): void
    {
        $analysis = Analysis::factory()->create(['status' => Analysis::RUNNING]);

        (new RunAnalysis($analysis->id))->failed(new RuntimeException('worker killed'));

        $this->assertSame(Analysis::FAILED, $analysis->fresh()->status);
        $this->assertStringNotContainsString('worker killed', $analysis->fresh()->error);
        $this->assertTrue($analysis->user->can('retry', $analysis->fresh()));
    }

    public function test_the_scan_workspace_is_removed_afterwards(): void
    {
        $before = glob(sys_get_temp_dir().'/codesentinel-*');
        $this->scanners(FakeScanner::ok('semgrep'), FakeScanner::ok('gitleaks'));

        $this->analyse();

        $this->assertSame($before, glob(sys_get_temp_dir().'/codesentinel-*'));
    }
}
