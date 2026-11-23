import { DemoBadge, SEVERITY_BAR, SeverityBadge, StatusBadge } from '@/components/badges';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { type AnalysisRow, type Severity, SEVERITIES } from '@/types/analysis';
import { Head, Link } from '@inertiajs/react';
import { FilePlus2 } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Overview', href: '/dashboard' }];

interface Props {
    counts: { analyses: number; completed: number; failed: number; in_progress: number; findings: number; verified: number };
    severities: Record<Severity, number>;
    recent: AnalysisRow[];
    ai_mode: 'demo' | 'claude' | 'off';
}

const AI_NOTE: Record<Props['ai_mode'], string> = {
    claude: 'AI review is on (Claude). Its suggestions are always marked unverified.',
    demo: 'AI review is in demo mode: no API key is set, so no model is called. Scanner results are real.',
    off: 'AI review is switched off. Scanner results are real.',
};

export default function Dashboard({ counts, severities, recent, ai_mode }: Props) {
    const most = Math.max(1, ...SEVERITIES.map((s) => severities[s]));
    const figures: [string, number, string][] = [
        ['Analyses', counts.analyses, `${counts.completed} completed · ${counts.in_progress} in progress · ${counts.failed} failed`],
        ['Findings', counts.findings, `${counts.verified} verified by a scanner · ${counts.findings - counts.verified} AI suggestions`],
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Overview" />
            <div className="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-8 p-4 md:p-6">
                <header className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">Overview</h1>
                        <p className="text-muted-foreground mt-1 max-w-xl text-sm">{AI_NOTE[ai_mode]}</p>
                    </div>
                    <Button asChild>
                        <Link href={route('analyses.create')}>
                            <FilePlus2 className="size-4" aria-hidden="true" /> New analysis
                        </Link>
                    </Button>
                </header>

                {counts.analyses === 0 ? (
                    <section className="rounded-lg border border-dashed p-8 text-center">
                        <h2 className="font-medium">No analyses yet</h2>
                        <p className="text-muted-foreground mx-auto mt-1 max-w-md text-sm">
                            Paste a snippet or upload source files. Semgrep and Gitleaks scan them, and the findings appear here.
                        </p>
                        <Button asChild className="mt-4">
                            <Link href={route('analyses.create')}>Run your first analysis</Link>
                        </Button>
                    </section>
                ) : (
                    <>
                        <section
                            aria-label="Totals"
                            className="grid gap-px overflow-hidden rounded-lg border bg-[var(--border)] md:grid-cols-[1fr_1fr_1.4fr]"
                        >
                            {figures.map(([label, value, note]) => (
                                <div key={label} className="bg-card p-5">
                                    <p className="text-muted-foreground font-mono text-[11px] tracking-widest uppercase">{label}</p>
                                    <p className="mt-2 font-mono text-4xl font-semibold tabular-nums">{value}</p>
                                    <p className="text-muted-foreground mt-2 text-xs">{note}</p>
                                </div>
                            ))}
                            <div className="bg-card p-5">
                                <p className="text-muted-foreground font-mono text-[11px] tracking-widest uppercase">Findings by severity</p>
                                <dl className="mt-3 space-y-2">
                                    {SEVERITIES.map((s) => (
                                        <div key={s} className="grid grid-cols-[5.5rem_1fr_2.5rem] items-center gap-3">
                                            <dt>
                                                <SeverityBadge severity={s} />
                                            </dt>
                                            <dd className="bg-muted h-2 overflow-hidden rounded-full" aria-hidden="true">
                                                <div
                                                    className={`h-full rounded-full ${SEVERITY_BAR[s]}`}
                                                    style={{ width: `${(severities[s] / most) * 100}%` }}
                                                />
                                            </dd>
                                            <dd className="text-right font-mono text-sm tabular-nums">{severities[s]}</dd>
                                        </div>
                                    ))}
                                </dl>
                            </div>
                        </section>

                        <section>
                            <div className="mb-3 flex items-center justify-between">
                                <h2 className="font-medium">Recent analyses</h2>
                                <Link
                                    href={route('analyses.index')}
                                    className="text-muted-foreground hover:text-foreground text-sm underline underline-offset-4"
                                >
                                    View all
                                </Link>
                            </div>
                            <ul className="divide-y rounded-lg border">
                                {recent.map((a) => (
                                    <li key={a.id}>
                                        <Link
                                            href={route('analyses.show', a.id)}
                                            className="hover:bg-muted/60 focus-visible:ring-ring flex flex-wrap items-center gap-x-4 gap-y-2 p-4 focus-visible:ring-2 focus-visible:outline-none"
                                        >
                                            <span className="min-w-0 flex-1">
                                                <span className="block truncate font-medium">{a.title}</span>
                                                <span className="text-muted-foreground block truncate font-mono text-xs">
                                                    {a.files.map((f) => f.name).join(', ')}
                                                </span>
                                            </span>
                                            {a.is_demo && <DemoBadge />}
                                            <span className="text-muted-foreground font-mono text-xs tabular-nums">
                                                {a.status === 'completed' ? `${a.findings_count} finding${a.findings_count === 1 ? '' : 's'}` : ''}
                                            </span>
                                            <StatusBadge status={a.status} />
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        </section>
                    </>
                )}
            </div>
        </AppLayout>
    );
}
