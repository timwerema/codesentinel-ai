<?php

namespace Tests\Unit;

use App\Analysis\Deduplicator;
use App\Scanners\RawFinding;
use PHPUnit\Framework\TestCase;

class DeduplicatorTest extends TestCase
{
    private function finding(array $over = []): RawFinding
    {
        return new RawFinding(...array_merge([
            'source' => 'semgrep', 'verified' => true, 'file' => 'a.py', 'lineStart' => 3, 'lineEnd' => 3,
            'ruleId' => 'rule-a', 'category' => 'security', 'severity' => 'medium', 'title' => 'T', 'explanation' => 'E',
        ], $over));
    }

    public function test_the_same_secret_from_two_tools_becomes_one_finding(): void
    {
        $merged = array_values((new Deduplicator)->merge([
            $this->finding(['category' => 'secret', 'ruleId' => 'hardcoded-key', 'severity' => 'medium']),
            $this->finding(['source' => 'gitleaks', 'category' => 'secret', 'ruleId' => 'generic-api-key', 'severity' => 'high', 'remediation' => 'Rotate it.']),
        ]));

        $this->assertCount(1, $merged);
        $this->assertSame('gitleaks', $merged[0]->source, 'the more severe report wins');
        $this->assertSame(['semgrep'], $merged[0]->alsoReportedBy);
        $this->assertSame('Rotate it.', $merged[0]->remediation);
    }

    public function test_different_problems_on_one_line_and_the_same_rule_on_other_lines_all_stay(): void
    {
        $merged = (new Deduplicator)->merge([
            $this->finding(),
            $this->finding(['ruleId' => 'rule-b']),
            $this->finding(['lineStart' => 9, 'lineEnd' => 9]),
            $this->finding(['file' => 'b.py']),
        ]);

        $this->assertCount(4, $merged);
    }

    public function test_exact_repeats_collapse_and_a_verified_finding_beats_an_ai_one(): void
    {
        $merged = array_values((new Deduplicator)->merge([
            $this->finding(['source' => 'ai', 'verified' => false, 'severity' => 'critical']),
            $this->finding(),
            $this->finding(),
        ]));

        $this->assertCount(1, $merged);
        $this->assertTrue($merged[0]->verified);
        $this->assertSame(['ai'], $merged[0]->alsoReportedBy);
    }
}
