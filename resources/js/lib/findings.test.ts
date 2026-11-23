import { applySuggestion, countBySeverity, filterFindings, formatBytes, snippet } from '@/lib/findings';
import { describe, expect, it } from 'vitest';

const findings = [
    {
        severity: 'high',
        verified: true,
        analysis_file_id: 1,
        title: 'Subprocess shell true',
        explanation: 'Runs through the shell.',
        rule_id: 'py-subprocess-shell-true',
    },
    { severity: 'medium', verified: true, analysis_file_id: 2, title: 'Weak hash', explanation: 'MD5 is broken.', rule_id: 'py-weak-hash' },
    { severity: 'info', verified: false, analysis_file_id: 1, title: 'Missing tests', explanation: 'This may be untested.', rule_id: 'ai-123' },
] as const;
const all = { severity: 'all', kind: 'all', fileId: 'all', query: '' } as const;

describe('countBySeverity', () => {
    it('counts every severity and keeps zeroes', () => {
        expect(countBySeverity([...findings])).toEqual({ critical: 0, high: 1, medium: 1, low: 0, info: 1 });
    });
    it('is all zeroes for no findings', () => {
        expect(countBySeverity([])).toEqual({ critical: 0, high: 0, medium: 0, low: 0, info: 0 });
    });
});

describe('filterFindings', () => {
    it('returns everything with no filters', () => {
        expect(filterFindings([...findings], all)).toHaveLength(3);
    });
    it('separates verified scanner findings from AI suggestions', () => {
        expect(filterFindings([...findings], { ...all, kind: 'verified' }).map((f) => f.rule_id)).toEqual([
            'py-subprocess-shell-true',
            'py-weak-hash',
        ]);
        expect(filterFindings([...findings], { ...all, kind: 'ai' }).map((f) => f.rule_id)).toEqual(['ai-123']);
    });
    it('filters by severity, file and text together', () => {
        expect(filterFindings([...findings], { ...all, severity: 'high' })).toHaveLength(1);
        expect(filterFindings([...findings], { ...all, fileId: 1 })).toHaveLength(2);
        expect(filterFindings([...findings], { ...all, fileId: 1, query: '  SHELL ' })).toHaveLength(1);
        expect(filterFindings([...findings], { ...all, query: 'md5' })).toHaveLength(1);
        expect(filterFindings([...findings], { ...all, severity: 'critical' })).toEqual([]);
    });
});

describe('snippet', () => {
    const source = ['a', 'b', 'c', 'd', 'e', 'f'].join('\n');
    it('shows context around the lines and marks the finding', () => {
        expect(snippet(source, 3, 4, 1)).toEqual([
            { n: 2, text: 'b', hit: false },
            { n: 3, text: 'c', hit: true },
            { n: 4, text: 'd', hit: true },
            { n: 5, text: 'e', hit: false },
        ]);
    });
    it('stays inside the file at both ends', () => {
        expect(snippet(source, 1, 1, 3).map((l) => l.n)).toEqual([1, 2, 3, 4]);
        expect(snippet(source, 6, 99, 2).map((l) => l.n)).toEqual([4, 5, 6]);
    });
    it('is empty when the finding has no line', () => {
        expect(snippet(source, null, null)).toEqual([]);
    });
});

describe('applySuggestion', () => {
    it('replaces exactly the given lines, including with more or fewer lines', () => {
        expect(applySuggestion('a\nb\nc\nd', 2, 3, 'X')).toBe('a\nX\nd');
        expect(applySuggestion('a\nb\nc', 2, 2, 'X\nY\n')).toBe('a\nX\nY\nc');
        expect(applySuggestion('a\nb', 1, 1, 'X')).toBe('X\nb');
    });
});

describe('formatBytes', () => {
    it('formats sizes', () => {
        expect(formatBytes(143)).toBe('143 B');
        expect(formatBytes(2048)).toBe('2.0 KB');
        expect(formatBytes(204800)).toBe('200 KB');
    });
});
