import { type Finding, type Severity, SEVERITIES } from '@/types/analysis';

/** How many findings there are at each severity, always with all five keys. */
export function countBySeverity(findings: Pick<Finding, 'severity'>[]): Record<Severity, number> {
    const counts = Object.fromEntries(SEVERITIES.map((s) => [s, 0])) as Record<Severity, number>;
    for (const f of findings) {
        if (f.severity in counts) counts[f.severity]++;
    }
    return counts;
}

export interface FindingFilters {
    severity: Severity | 'all';
    kind: 'all' | 'verified' | 'ai';
    fileId: number | 'all';
    query: string;
}

export function filterFindings<T extends Pick<Finding, 'severity' | 'verified' | 'analysis_file_id' | 'title' | 'explanation' | 'rule_id'>>(
    findings: T[],
    filters: FindingFilters,
): T[] {
    const q = filters.query.trim().toLowerCase();
    return findings.filter(
        (f) =>
            (filters.severity === 'all' || f.severity === filters.severity) &&
            (filters.kind === 'all' || (filters.kind === 'verified' ? f.verified : !f.verified)) &&
            (filters.fileId === 'all' || f.analysis_file_id === filters.fileId) &&
            (q === '' || `${f.title} ${f.explanation} ${f.rule_id}`.toLowerCase().includes(q)),
    );
}

/** The lines around a finding, with their real line numbers. */
export function snippet(source: string, start: number | null, end: number | null, context = 2): { n: number; text: string; hit: boolean }[] {
    if (start === null) return [];
    const lines = source.split('\n');
    const last = Math.min(end ?? start, lines.length);
    const from = Math.max(1, start - context);
    const to = Math.min(lines.length, last + context);
    const out = [];
    for (let n = from; n <= to; n++) out.push({ n, text: lines[n - 1] ?? '', hit: n >= start && n <= last });
    return out;
}

/** The file with lines start..end replaced by the suggested code, for the side-by-side view. */
export function applySuggestion(source: string, start: number, end: number, replacement: string): string {
    const lines = source.split('\n');
    return [...lines.slice(0, start - 1), ...replacement.replace(/\n$/, '').split('\n'), ...lines.slice(end)].join('\n');
}

export function formatBytes(bytes: number): string {
    return bytes < 1024 ? `${bytes} B` : `${(bytes / 1024).toFixed(bytes < 10240 ? 1 : 0)} KB`;
}
