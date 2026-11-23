<?php

namespace Tests\Feature\Analysis;

use App\Scanners\GitleaksScanner;
use App\Scanners\Sandbox;
use App\Scanners\SemgrepScanner;
use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\ExecutableFinder;
use Tests\TestCase;

/**
 * Runs the real Semgrep and Gitleaks programs. Skipped (not failed) where they are not installed,
 * so the rest of the suite still runs on a machine without them. CI installs both.
 */
class RealScannersTest extends TestCase
{
    private string $workspace;

    protected function setUp(): void
    {
        parent::setUp();
        $this->workspace = sys_get_temp_dir().'/codesentinel-test-'.bin2hex(random_bytes(6));
        File::makeDirectory($this->workspace.'/src', 0755, true);
        File::makeDirectory($this->workspace.'/home', 0777, true);
        chmod($this->workspace.'/home', 0777);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->workspace);
        parent::tearDown();
    }

    private function needs(string $binary): void
    {
        if ((new ExecutableFinder)->find($binary) === null) {
            $this->markTestSkipped("{$binary} is not installed.");
        }
    }

    private function source(string $name, string $contents): void
    {
        File::put($this->workspace.'/src/'.$name, $contents);
    }

    public function test_semgrep_reports_real_findings_with_file_line_severity_and_fix(): void
    {
        $this->needs('semgrep');
        $this->source('app.py', "import subprocess\n\ndef run(cmd):\n    return subprocess.call(cmd, shell=True)\n");
        $this->source('clean.py', "def add(a, b):\n    return a + b\n");

        $result = app(SemgrepScanner::class)->scan($this->workspace);

        $this->assertTrue($result->ok, (string) $result->error);
        $this->assertCount(1, $result->findings);
        $f = $result->findings[0];
        $this->assertSame(['app.py', 4, 'py-subprocess-shell-true', 'high', 'security', true], [$f->file, $f->lineStart, $f->ruleId, $f->severity, $f->category, $f->verified]);
        $this->assertNotEmpty($f->remediation);
        $this->assertMatchesRegularExpression('/^\d+\.\d+/', (string) $result->version);
    }

    public function test_gitleaks_reports_a_hard_coded_token_without_storing_the_secret(): void
    {
        $this->needs('gitleaks');
        $token = 'ghp_'.substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789'), 0, 36);
        $this->source('config.js', "const githubToken = \"{$token}\";\n");

        $result = app(GitleaksScanner::class)->scan($this->workspace);

        $this->assertTrue($result->ok, (string) $result->error);
        $this->assertCount(1, $result->findings);
        $this->assertSame(['config.js', 1, 'secret'], [$result->findings[0]->file, $result->findings[0]->lineStart, $result->findings[0]->category]);
        $this->assertStringNotContainsString($token, json_encode($result->findings));
    }

    public function test_a_file_whose_content_is_hostile_to_a_shell_is_just_scanned(): void
    {
        $this->needs('semgrep');
        $this->source('weird.py', "x = \"\$(touch {$this->workspace}/pwned); `id`; ' ; rm -rf /\"\n");

        $result = app(SemgrepScanner::class)->scan($this->workspace);

        $this->assertTrue($result->ok);
        $this->assertFileDoesNotExist($this->workspace.'/pwned');
    }

    public function test_scanner_processes_do_not_inherit_application_secrets(): void
    {
        putenv('CODESENTINEL_TEST_SECRET=do-not-leak');
        $_ENV['CODESENTINEL_TEST_SECRET'] = 'do-not-leak';
        $_SERVER['CODESENTINEL_TEST_SECRET'] = 'do-not-leak';

        try {
            $env = app(Sandbox::class)->run(['env'], $this->workspace, $this->workspace.'/home')->output();
        } finally {
            putenv('CODESENTINEL_TEST_SECRET');
            unset($_ENV['CODESENTINEL_TEST_SECRET'], $_SERVER['CODESENTINEL_TEST_SECRET']);
        }

        $this->assertStringContainsString('HOME='.$this->workspace.'/home', $env);
        foreach (['do-not-leak', 'APP_KEY', 'DB_PASSWORD', 'ANTHROPIC_API_KEY'] as $secret) {
            $this->assertStringNotContainsString($secret, $env);
        }
    }

    public function test_scanners_do_not_run_as_root(): void
    {
        if (! app(Sandbox::class)->shouldDropPrivileges() || (new ExecutableFinder)->find('setpriv') === null) {
            $this->markTestSkipped('The test suite is not running as root, so there are no privileges to drop.');
        }

        $uid = trim(app(Sandbox::class)->run(['id', '-u'], $this->workspace, $this->workspace.'/home')->output());

        $this->assertSame((string) config('codesentinel.scanners.drop_to_uid'), $uid);
    }

    public function test_a_scanner_that_runs_too_long_is_stopped(): void
    {
        config(['codesentinel.scanners.timeout_seconds' => 1]);
        $this->expectException(ProcessTimedOutException::class);

        app(Sandbox::class)->run(['sleep', '10'], $this->workspace, $this->workspace.'/home');
    }

    public function test_a_missing_or_broken_scanner_is_reported_not_thrown(): void
    {
        config(['codesentinel.scanners.semgrep.binary' => '/nonexistent/semgrep', 'codesentinel.scanners.gitleaks.binary' => 'false']);
        $this->source('a.py', "x = 1\n");

        $semgrep = app(SemgrepScanner::class)->scan($this->workspace);
        $gitleaks = app(GitleaksScanner::class)->scan($this->workspace);

        $this->assertFalse($semgrep->ok);
        $this->assertNotEmpty($semgrep->error);
        $this->assertSame([], $semgrep->findings);
        $this->assertFalse($gitleaks->ok);
    }
}
