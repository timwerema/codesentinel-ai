export type Severity = 'critical' | 'high' | 'medium' | 'low' | 'info';
export type AnalysisStatus = 'queued' | 'running' | 'completed' | 'failed';
export type Category = 'security' | 'secret' | 'logic' | 'maintainability' | 'testing';

export interface AnalysisRow {
    id: string;
    title: string;
    origin: 'paste' | 'upload' | 'demo';
    status: AnalysisStatus;
    is_demo: boolean;
    created_at: string;
    seconds: number | null;
    files: { name: string; language: string }[];
    findings_count: number;
}

export interface ScannerRun {
    status: 'ok' | 'failed';
    version: string | null;
    seconds: number;
    findings: number;
    error: string | null;
}

export interface AnalysisDetail extends AnalysisRow {
    error: string | null;
    scanners: Record<string, ScannerRun> | null;
    ai_mode: 'none' | 'demo' | 'claude';
    ai_summary: string | null;
    ai_usage: {
        model?: string | null;
        input_tokens?: number;
        output_tokens?: number;
        estimated_cost_usd?: number | null;
        failed?: boolean;
        skipped?: string;
    } | null;
    can_retry: boolean;
}

export interface SourceFile {
    id: number;
    name: string;
    language: string;
    line_count: number;
    size_bytes: number;
    contents: string;
}

export interface Finding {
    id: number;
    analysis_file_id: number | null;
    source: 'semgrep' | 'gitleaks' | 'ai';
    verified: boolean;
    also_reported_by: string[] | null;
    rule_id: string;
    category: Category;
    severity: Severity;
    title: string;
    explanation: string;
    remediation: string | null;
    suggested_code: string | null;
    line_start: number | null;
    line_end: number | null;
}

export interface Paginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
}

export const SEVERITIES: Severity[] = ['critical', 'high', 'medium', 'low', 'info'];
