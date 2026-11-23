<?php

namespace App\Ai;

use App\Models\Finding;

/** The shape an AI review must have, as a JSON schema for the model and as a validator for its reply. */
class ReviewSchema
{
    public const CATEGORIES = ['security', 'logic', 'maintainability', 'testing'];

    public const MAX_SUGGESTIONS = 25;

    public static function schema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['summary', 'suggestions'],
            'properties' => [
                'summary' => ['type' => 'string', 'description' => 'Two to four plain sentences on the overall state of the code. Never say the code is secure or free of vulnerabilities.'],
                'suggestions' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['file', 'line_start', 'line_end', 'category', 'severity', 'title', 'explanation', 'remediation', 'suggested_code'],
                        'properties' => [
                            'file' => ['type' => 'string', 'description' => 'Exactly one of the file names given.'],
                            'line_start' => ['type' => ['integer', 'null']],
                            'line_end' => ['type' => ['integer', 'null']],
                            'category' => ['type' => 'string', 'enum' => self::CATEGORIES],
                            'severity' => ['type' => 'string', 'enum' => Finding::SEVERITIES],
                            'title' => ['type' => 'string'],
                            'explanation' => ['type' => 'string'],
                            'remediation' => ['type' => 'string'],
                            'suggested_code' => ['type' => ['string', 'null'], 'description' => 'Replacement for the lines line_start..line_end, or null.'],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Check a decoded reply ourselves: the model's output is never trusted to match the schema.
     *
     * @param  list<string>  $fileNames  the files that were sent
     * @param  array<string, int>  $lineCounts  file name => number of lines
     * @return array{summary: string, suggestions: list<array<string, mixed>>}
     *
     * @throws AiReviewFailed
     */
    public static function validate(mixed $data, array $fileNames, array $lineCounts): array
    {
        if (! is_array($data) || ! is_string($data['summary'] ?? null) || ! is_array($data['suggestions'] ?? null) || ! array_is_list($data['suggestions'])) {
            throw new AiReviewFailed('The AI reply was not in the expected format, so it was discarded.');
        }
        if (array_diff(array_keys($data), ['summary', 'suggestions']) !== []) {
            throw new AiReviewFailed('The AI reply was not in the expected format, so it was discarded.');
        }

        $clean = [];
        foreach (array_slice($data['suggestions'], 0, self::MAX_SUGGESTIONS) as $s) {
            $ok = is_array($s)
                && in_array($s['file'] ?? null, $fileNames, true)
                && in_array($s['category'] ?? null, self::CATEGORIES, true)
                && in_array($s['severity'] ?? null, Finding::SEVERITIES, true)
                && is_string($s['title'] ?? null) && trim($s['title']) !== ''
                && is_string($s['explanation'] ?? null) && trim($s['explanation']) !== ''
                && (is_string($s['remediation'] ?? null) || ($s['remediation'] ?? null) === null)
                && (is_string($s['suggested_code'] ?? null) || ($s['suggested_code'] ?? null) === null)
                && (is_int($s['line_start'] ?? null) || ($s['line_start'] ?? null) === null)
                && (is_int($s['line_end'] ?? null) || ($s['line_end'] ?? null) === null);

            if (! $ok) {
                throw new AiReviewFailed('The AI reply was not in the expected format, so it was discarded.');
            }

            // Line numbers that point outside the file are dropped rather than trusted.
            $max = $lineCounts[$s['file']] ?? 0;
            $start = is_int($s['line_start']) && $s['line_start'] >= 1 && $s['line_start'] <= $max ? $s['line_start'] : null;
            $end = $start !== null && is_int($s['line_end']) && $s['line_end'] >= $start && $s['line_end'] <= $max ? $s['line_end'] : $start;

            $clean[] = [
                'file' => $s['file'], 'line_start' => $start, 'line_end' => $end,
                'category' => $s['category'], 'severity' => $s['severity'],
                'title' => mb_substr(trim($s['title']), 0, 190),
                'explanation' => mb_substr(trim($s['explanation']), 0, 4000),
                'remediation' => isset($s['remediation']) ? mb_substr(trim($s['remediation']), 0, 4000) : null,
                'suggested_code' => $start !== null && isset($s['suggested_code']) ? mb_substr($s['suggested_code'], 0, 8000) : null,
            ];
        }

        return ['summary' => mb_substr(trim($data['summary']), 0, 2000), 'suggestions' => $clean];
    }
}
