import { cn } from '@/lib/utils';
import { type AnalysisStatus, type Severity } from '@/types/analysis';
import { AlertTriangle, CheckCircle2, CircleDashed, LoaderCircle, XCircle } from 'lucide-react';

const SEVERITY: Record<Severity, string> = {
    critical: 'bg-red-500/15 text-red-700 ring-red-500/30 dark:text-red-300',
    high: 'bg-orange-500/15 text-orange-700 ring-orange-500/30 dark:text-orange-300',
    medium: 'bg-amber-500/15 text-amber-700 ring-amber-500/30 dark:text-amber-300',
    low: 'bg-sky-500/15 text-sky-700 ring-sky-500/30 dark:text-sky-300',
    info: 'bg-slate-500/15 text-slate-700 ring-slate-500/30 dark:text-slate-300',
};

export const SEVERITY_BAR: Record<Severity, string> = {
    critical: 'bg-red-500',
    high: 'bg-orange-500',
    medium: 'bg-amber-500',
    low: 'bg-sky-500',
    info: 'bg-slate-500',
};

const pill =
    'inline-flex items-center gap-1.5 rounded px-2 py-0.5 font-mono text-[11px] font-medium uppercase tracking-wide ring-1 ring-inset whitespace-nowrap';

export function SeverityBadge({ severity, className }: { severity: Severity; className?: string }) {
    return <span className={cn(pill, SEVERITY[severity], className)}>{severity}</span>;
}

const STATUS: Record<AnalysisStatus, { label: string; className: string; Icon: typeof CheckCircle2; spin?: boolean }> = {
    queued: { label: 'Queued', className: 'bg-slate-500/15 text-slate-700 ring-slate-500/30 dark:text-slate-300', Icon: CircleDashed },
    running: { label: 'Running', className: 'bg-sky-500/15 text-sky-700 ring-sky-500/30 dark:text-sky-300', Icon: LoaderCircle, spin: true },
    completed: { label: 'Completed', className: 'bg-emerald-500/15 text-emerald-700 ring-emerald-500/30 dark:text-emerald-300', Icon: CheckCircle2 },
    failed: { label: 'Failed', className: 'bg-red-500/15 text-red-700 ring-red-500/30 dark:text-red-300', Icon: XCircle },
};

export function StatusBadge({ status }: { status: AnalysisStatus }) {
    const { label, className, Icon, spin } = STATUS[status];
    return (
        <span className={cn(pill, className)}>
            <Icon className={cn('size-3', spin && 'animate-spin')} aria-hidden="true" />
            {label}
        </span>
    );
}

/** Scanner findings are verified; AI suggestions are not. The difference is always shown. */
export function SourceBadge({ source, verified }: { source: string; verified: boolean }) {
    return verified ? (
        <span className={cn(pill, 'bg-emerald-500/10 text-emerald-700 ring-emerald-500/30 dark:text-emerald-300')}>
            <CheckCircle2 className="size-3" aria-hidden="true" />
            {source}
        </span>
    ) : (
        <span className={cn(pill, 'bg-violet-500/10 text-violet-700 ring-violet-500/30 dark:text-violet-300')}>
            <AlertTriangle className="size-3" aria-hidden="true" />
            AI · unverified
        </span>
    );
}

export function DemoBadge() {
    return <span className={cn(pill, 'bg-fuchsia-500/10 text-fuchsia-700 ring-fuchsia-500/30 dark:text-fuchsia-300')}>Demo data</span>;
}
