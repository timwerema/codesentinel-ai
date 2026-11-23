<?php

namespace App\Scanners;

use Throwable;

/** Secret scanning with Gitleaks. Secrets are redacted by Gitleaks itself: we never store one. */
class GitleaksScanner implements Scanner
{
    public function __construct(private Sandbox $sandbox) {}

    public function name(): string
    {
        return 'gitleaks';
    }

    public function scan(string $workspace): ScanResult
    {
        $started = microtime(true);
        $binary = config('codesentinel.scanners.gitleaks.binary');

        try {
            $run = $this->sandbox->run([
                $binary, 'dir', 'src',
                '--report-format', 'json', '--report-path', '-',
                '--no-banner', '--redact', '--exit-code', '0',
                '--max-target-megabytes', '2',
            ], $workspace, $workspace.'/home');
        } catch (Throwable $e) {
            return new ScanResult($this->name(), false, seconds: microtime(true) - $started, error: SemgrepScanner::reason($e));
        }

        $data = json_decode($run->output(), true);
        if (! is_array($data) || ! array_is_list($data)) {
            return new ScanResult($this->name(), false, seconds: microtime(true) - $started, error: 'Gitleaks did not return a readable report (exit code '.$run->exitCode().').');
        }

        $findings = [];
        foreach ($data as $leak) {
            if (! is_array($leak) || ! isset($leak['RuleID'], $leak['File'])) {
                continue;
            }
            $description = trim((string) ($leak['Description'] ?? ''));
            $findings[] = new RawFinding(
                source: 'gitleaks',
                verified: true,
                file: basename((string) $leak['File']),
                lineStart: isset($leak['StartLine']) ? (int) $leak['StartLine'] : null,
                lineEnd: isset($leak['EndLine']) ? (int) $leak['EndLine'] : null,
                ruleId: (string) $leak['RuleID'],
                category: 'secret',
                severity: 'high',
                title: 'Hard-coded secret ('.str_replace('-', ' ', (string) $leak['RuleID']).')',
                explanation: ($description !== '' ? rtrim($description, '.').'. ' : '').'A credential in source code is exposed to everyone who can read the code or its history.',
                remediation: 'Revoke and replace this credential now, then load it from an environment variable or a secret manager instead of the source file.',
            );
        }

        return new ScanResult($this->name(), true, $findings, $this->version($workspace, $binary), microtime(true) - $started);
    }

    private function version(string $workspace, string $binary): ?string
    {
        try {
            $v = trim($this->sandbox->run([$binary, 'version'], $workspace, $workspace.'/home')->output());

            return preg_match('/^v?\d+\.\d+/', $v) ? $v : null;
        } catch (Throwable) {
            return null;
        }
    }
}
