<?php

namespace Tests\Support;

use App\Scanners\RawFinding;
use App\Scanners\ScanResult;

/** Builds canned scanner results for pipeline tests. */
class FakeScanner
{
    public static function finding(string $source, string $file, int $line, string $rule, string $severity = 'high', string $category = 'security'): RawFinding
    {
        return new RawFinding($source, true, $file, $line, $line, $rule, $category, $severity, ucfirst($rule), "Explanation for {$rule}.", 'Fix it.');
    }

    public static function ok(string $tool, array $findings = []): ScanResult
    {
        return new ScanResult($tool, true, $findings, '1.0.0', 0.1);
    }

    public static function failed(string $tool, string $error = 'The scanner could not be started. Is it installed?'): ScanResult
    {
        return new ScanResult($tool, false, error: $error);
    }
}
