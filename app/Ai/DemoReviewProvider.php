<?php

namespace App\Ai;

use App\Scanners\RawFinding;

/**
 * Stands in when no API key is configured. It calls no model. Its output is fixed sample text,
 * the same every time, and every item says so. It exists so the review screens can be tried.
 */
class DemoReviewProvider implements ReviewProvider
{
    public const NOTICE = 'Demo mode: no AI model was called. The items marked "Demo sample" are fixed examples that show what an AI review looks like. They are not an analysis of your code.';

    public function mode(): string
    {
        return 'demo';
    }

    public function review(ReviewRequest $request): ReviewResult
    {
        $file = $request->files[0]['name'] ?? 'file';

        return new ReviewResult('demo', self::NOTICE, [
            new RawFinding(
                source: 'ai', verified: false, file: $file, lineStart: null, lineEnd: null,
                ruleId: 'demo-sample-input-validation', category: 'logic', severity: 'info',
                title: 'Demo sample: check inputs at the edges',
                explanation: 'Demo sample, not a real finding. A real AI review would point at a specific function here and explain which inputs (empty, negative, very large) it does not handle.',
                remediation: 'Set ANTHROPIC_API_KEY to get a real review of this file.',
            ),
            new RawFinding(
                source: 'ai', verified: false, file: $file, lineStart: null, lineEnd: null,
                ruleId: 'demo-sample-missing-tests', category: 'testing', severity: 'info',
                title: 'Demo sample: untested branches',
                explanation: 'Demo sample, not a real finding. A real AI review would list the branches and error paths that have no test.',
                remediation: 'Set ANTHROPIC_API_KEY to get a real review of this file.',
            ),
        ], ['model' => null]);
    }
}
