<?php

namespace App\Scanners;

use Throwable;

/** Static analysis with Semgrep, using only the rules bundled in scanner/rules (no network). */
class SemgrepScanner implements Scanner
{
    private const SEVERITY = ['CRITICAL' => 'critical', 'ERROR' => 'high', 'HIGH' => 'high', 'WARNING' => 'medium', 'MEDIUM' => 'medium', 'LOW' => 'low', 'INFO' => 'info'];

    public function __construct(private Sandbox $sandbox) {}

    public function name(): string
    {
        return 'semgrep';
    }

    public function scan(string $workspace): ScanResult
    {
        $started = microtime(true);
        $c = config('codesentinel.scanners');

        try {
            $run = $this->sandbox->run([
                $c['semgrep']['binary'], 'scan',
                '--config', $c['semgrep']['rules'],
                '--json', '--quiet',
                '--metrics=off', '--disable-version-check',
                '--no-git-ignore',
                '--timeout', '20',
                '--max-target-bytes', (string) config('codesentinel.limits.max_file_bytes'),
                '--jobs', '2',
                '--max-memory', (string) $c['memory_mb'],
                'src',
            ], $workspace, $workspace.'/home', ['SEMGREP_SEND_METRICS' => 'off']);
        } catch (Throwable $e) {
            return new ScanResult($this->name(), false, seconds: microtime(true) - $started, error: self::reason($e));
        }

        $data = json_decode($run->output(), true);
        if (! is_array($data) || ! isset($data['results']) || ! is_array($data['results'])) {
            return new ScanResult($this->name(), false, seconds: microtime(true) - $started, error: 'Semgrep did not return a readable report (exit code '.$run->exitCode().').');
        }

        // A killed or crashed engine still prints JSON, with no results and an error entry.
        $fatal = collect($data['errors'] ?? [])->first(fn ($e) => is_array($e) && ($e['level'] ?? '') === 'error' && ($e['type'] ?? '') !== 'Syntax error' && ! isset($e['path']));
        if ($run->exitCode() !== 0 && $data['results'] === [] && $fatal) {
            return new ScanResult($this->name(), false, seconds: microtime(true) - $started, error: 'Semgrep stopped with an error before finishing (exit code '.$run->exitCode().').');
        }

        $findings = [];
        foreach ($data['results'] as $r) {
            if (! is_array($r) || ! isset($r['check_id'], $r['path'])) {
                continue;
            }
            $extra = is_array($r['extra'] ?? null) ? $r['extra'] : [];
            $meta = is_array($extra['metadata'] ?? null) ? $extra['metadata'] : [];
            $rule = (string) last(explode('.', (string) $r['check_id']));
            $message = trim((string) ($extra['message'] ?? ''));

            $findings[] = new RawFinding(
                source: 'semgrep',
                verified: true,
                file: basename((string) $r['path']),
                lineStart: isset($r['start']['line']) ? (int) $r['start']['line'] : null,
                lineEnd: isset($r['end']['line']) ? (int) $r['end']['line'] : null,
                ruleId: $rule,
                category: in_array($meta['category'] ?? null, ['security', 'logic', 'maintainability', 'testing'], true) ? $meta['category'] : 'security',
                severity: self::SEVERITY[strtoupper((string) ($extra['severity'] ?? ''))] ?? 'medium',
                title: self::title($rule),
                explanation: $message !== '' ? $message : 'Reported by Semgrep rule '.$rule.'.',
                remediation: isset($meta['fix']) ? (string) $meta['fix'] : null,
            );
        }

        return new ScanResult($this->name(), true, $findings, isset($data['version']) ? (string) $data['version'] : null, microtime(true) - $started);
    }

    /** "py-subprocess-shell-true" -> "Subprocess shell true" */
    private static function title(string $rule): string
    {
        $words = preg_replace('/^(py|js|php|java|c)-/', '', $rule) ?? $rule;

        return ucfirst(str_replace('-', ' ', $words));
    }

    public static function reason(Throwable $e): string
    {
        return str_contains($e::class, 'Timed') ? 'The scanner took too long and was stopped.' : 'The scanner could not be started. Is it installed?';
    }
}
