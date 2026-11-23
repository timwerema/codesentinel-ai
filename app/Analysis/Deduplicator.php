<?php

namespace App\Analysis;

use App\Models\Finding;
use App\Scanners\RawFinding;

/**
 * Two tools often report the same problem on the same line (a hard-coded key is both a Semgrep
 * and a Gitleaks hit). Findings are grouped by file + line + what kind of problem it is; the most
 * severe verified one is kept and the others are recorded in "also reported by".
 */
class Deduplicator
{
    /**
     * @param  list<RawFinding>  $findings
     * @return array<string, RawFinding> keyed by fingerprint
     */
    public function merge(array $findings): array
    {
        $kept = [];
        foreach ($findings as $f) {
            $key = self::fingerprint($f);
            if (! isset($kept[$key])) {
                $kept[$key] = $f;

                continue;
            }
            [$winner, $loser] = self::better($f, $kept[$key]) ? [$f, $kept[$key]] : [$kept[$key], $f];
            $others = array_values(array_unique(array_diff([...$winner->alsoReportedBy, ...$loser->alsoReportedBy, $loser->source], [$winner->source])));
            $kept[$key] = $winner->with([
                'alsoReportedBy' => $others,
                'remediation' => $winner->remediation ?? $loser->remediation,
            ]);
        }

        return $kept;
    }

    public static function fingerprint(RawFinding $f): string
    {
        // Secrets are one problem per line whatever rule matched. Other findings are per rule,
        // so two different problems on one line both stay.
        $what = $f->category === 'secret' ? 'secret' : $f->ruleId;

        return sha1(implode('|', [$f->file, $f->lineStart ?? 0, $what]));
    }

    private static function better(RawFinding $a, RawFinding $b): bool
    {
        if ($a->verified !== $b->verified) {
            return $a->verified;
        }

        return Finding::rank($a->severity) < Finding::rank($b->severity);
    }
}
