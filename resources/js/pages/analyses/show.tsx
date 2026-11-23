import { DemoBadge, SeverityBadge, SourceBadge, StatusBadge } from '@/components/badges';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { applySuggestion, countBySeverity, filterFindings, type FindingFilters, formatBytes, snippet } from '@/lib/findings';
import { type BreadcrumbItem } from '@/types';
import { type AnalysisDetail, type Finding, SEVERITIES, type SourceFile } from '@/types/analysis';
import { Head, Link, router } from '@inertiajs/react';
import { LoaderCircle, RotateCcw, Trash2 } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

interface Props {
    analysis: AnalysisDetail;
    files: SourceFile[];
    findings: Finding[];
}

function CodeLines({ lines, label }: { lines: { n: number; text: string; hit: boolean }[]; label: string }) {
    return (
        <div className="code-pane bg-card overflow-x-auto rounded-md border" role="region" aria-label={label} tabIndex={0}>
            <table className="w-full border-collapse">
                <tbody>
                    {lines.map((l) => (
                        <tr key={l.n} className={l.hit ? 'bg-amber-500/15' : undefined}>
                            <td className="ln w-12 px-2 align-top">{l.n}</td>
                            <td className="pr-4 whitespace-pre">{l.text || ' '}</td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

function FindingDetail({ finding, file }: { finding: Finding; file: SourceFile | undefined }) {
    const lines = file ? snippet(file.contents, finding.line_start, finding.line_end, 3) : [];
    const canCompare = file && finding.suggested_code !== null && finding.line_start !== null;
    const suggested = canCompare
        ? snippet(
              applySuggestion(file.contents, finding.line_start!, finding.line_end ?? finding.line_start!, finding.suggested_code!),
              finding.line_start,
              finding.line_start! + finding.suggested_code!.replace(/\n$/, '').split('\n').length - 1,
              3,
          )
        : [];

    return (
        <article className="space-y-5" aria-labelledby="finding-title">
            <header className="space-y-2">
                <div className="flex flex-wrap items-center gap-2">
                    <SeverityBadge severity={finding.severity} />
                    <SourceBadge source={finding.source} verified={finding.verified} />
                    <span className="text-muted-foreground font-mono text-xs">{finding.category}</span>
                </div>
                <h2 id="finding-title" className="text-lg font-semibold tracking-tight">
                    {finding.title}
                </h2>
                <p className="text-muted-foreground font-mono text-xs break-all">
                    {file ? file.name : 'Whole analysis'}
                    {finding.line_start !== null &&
                        `:${finding.line_start}${finding.line_end && finding.line_end !== finding.line_start ? `–${finding.line_end}` : ''}`}{' '}
                    · rule {finding.rule_id}
                    {finding.also_reported_by && finding.also_reported_by.length > 0 && ` · also reported by ${finding.also_reported_by.join(', ')}`}
                </p>
            </header>

            {!finding.verified && (
                <p className="rounded-md border border-violet-500/30 bg-violet-500/10 p-3 text-sm">
                    This is an AI suggestion. No scanner confirmed it, so check it yourself before acting on it.
                </p>
            )}

            <section>
                <h3 className="text-muted-foreground mb-1 font-mono text-[11px] tracking-widest uppercase">What is wrong</h3>
                <p className="text-sm leading-relaxed">{finding.explanation}</p>
            </section>

            {finding.remediation && (
                <section>
                    <h3 className="text-muted-foreground mb-1 font-mono text-[11px] tracking-widest uppercase">How to fix it</h3>
                    <p className="text-sm leading-relaxed">{finding.remediation}</p>
                </section>
            )}

            {lines.length > 0 && !canCompare && (
                <section>
                    <h3 className="text-muted-foreground mb-1 font-mono text-[11px] tracking-widest uppercase">Code</h3>
                    <CodeLines lines={lines} label="Code around the finding" />
                </section>
            )}

            {canCompare && (
                <section>
                    <h3 className="text-muted-foreground mb-1 font-mono text-[11px] tracking-widest uppercase">Original and suggested</h3>
                    <div className="grid gap-3 xl:grid-cols-2">
                        <div className="min-w-0">
                            <p className="text-muted-foreground mb-1 text-xs">Original</p>
                            <CodeLines lines={lines} label="Original code" />
                        </div>
                        <div className="min-w-0">
                            <p className="text-muted-foreground mb-1 text-xs">Suggested (unverified, not applied)</p>
                            <CodeLines lines={suggested} label="Suggested code" />
                        </div>
                    </div>
                </section>
            )}
        </article>
    );
}

export default function AnalysesShow({ analysis, files, findings }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Analyses', href: '/analyses' },
        { title: analysis.title, href: `/analyses/${analysis.id}` },
    ];
    const pending = analysis.status === 'queued' || analysis.status === 'running';
    const [pollFailed, setPollFailed] = useState(false);
    const [filters, setFilters] = useState<FindingFilters>({ severity: 'all', kind: 'all', fileId: 'all', query: '' });
    const [selectedId, setSelectedId] = useState<number | null>(null);
    const [busy, setBusy] = useState<'retry' | 'delete' | null>(null);

    // While the job is queued or running, ask for its status and reload the page when it changes.
    useEffect(() => {
        if (!pending) return;
        let failures = 0;
        const timer = window.setInterval(async () => {
            try {
                const res = await fetch(route('analyses.status', analysis.id), { headers: { Accept: 'application/json' } });
                if (!res.ok) throw new Error(String(res.status));
                failures = 0;
                setPollFailed(false);
                if ((await res.json()).status !== analysis.status) router.reload();
            } catch {
                if (++failures >= 3) setPollFailed(true);
            }
        }, 2000);
        return () => window.clearInterval(timer);
    }, [pending, analysis.id, analysis.status]);

    const counts = useMemo(() => countBySeverity(findings), [findings]);
    const visible = useMemo(() => filterFindings(findings, filters), [findings, filters]);
    const selected = visible.find((f) => f.id === selectedId) ?? visible[0] ?? null;
    const fileOf = (f: Finding) => files.find((x) => x.id === f.analysis_file_id);
    const verified = findings.filter((f) => f.verified).length;
    const select =
        'border-input bg-background focus-visible:ring-ring h-9 rounded-md border px-2 text-sm focus-visible:ring-2 focus-visible:outline-none';

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={analysis.title} />
            <div className="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-6 p-4 md:p-6">
                <header className="flex flex-wrap items-start justify-between gap-4">
                    <div className="min-w-0">
                        <div className="flex flex-wrap items-center gap-2">
                            <StatusBadge status={analysis.status} />
                            {analysis.is_demo && <DemoBadge />}
                        </div>
                        <h1 className="mt-2 text-2xl font-semibold tracking-tight break-words">{analysis.title}</h1>
                        <p className="text-muted-foreground mt-1 font-mono text-xs">
                            <time dateTime={analysis.created_at}>{new Date(analysis.created_at).toLocaleString()}</time>
                            {analysis.seconds !== null && ` · took ${analysis.seconds}s`} · {files.length} file{files.length === 1 ? '' : 's'}
                        </p>
                    </div>
                    <div className="flex gap-2">
                        {analysis.can_retry && (
                            <Button
                                variant="outline"
                                disabled={busy !== null}
                                onClick={() =>
                                    router.post(
                                        route('analyses.retry', analysis.id),
                                        {},
                                        { onStart: () => setBusy('retry'), onFinish: () => setBusy(null) },
                                    )
                                }
                            >
                                {busy === 'retry' ? (
                                    <LoaderCircle className="size-4 animate-spin" aria-hidden="true" />
                                ) : (
                                    <RotateCcw className="size-4" aria-hidden="true" />
                                )}
                                Run again
                            </Button>
                        )}
                        <Button
                            variant="outline"
                            disabled={busy !== null || pending}
                            onClick={() => {
                                if (window.confirm('Delete this analysis and its stored code? This cannot be undone.')) {
                                    router.delete(route('analyses.destroy', analysis.id), {
                                        onStart: () => setBusy('delete'),
                                        onFinish: () => setBusy(null),
                                    });
                                }
                            }}
                        >
                            <Trash2 className="size-4" aria-hidden="true" /> Delete
                        </Button>
                    </div>
                </header>

                {analysis.is_demo && (
                    <p className="rounded-md border border-fuchsia-500/30 bg-fuchsia-500/10 p-3 text-sm">
                        Demo data. This analysis was created by the demo seeder from a deliberately vulnerable sample file, so you can see the screens
                        with content.
                    </p>
                )}

                {pending && (
                    <section role="status" aria-live="polite" className="flex items-center gap-4 rounded-lg border p-6">
                        <LoaderCircle className="text-primary size-6 animate-spin" aria-hidden="true" />
                        <div>
                            <p className="font-medium">{analysis.status === 'queued' ? 'Waiting for a worker' : 'Scanning your code'}</p>
                            <p className="text-muted-foreground text-sm">
                                {pollFailed
                                    ? 'Could not check progress. Reload the page to try again.'
                                    : analysis.status === 'queued'
                                      ? 'If this stays queued, the queue worker is not running (php artisan queue:work).'
                                      : 'This page updates by itself when the scan finishes.'}
                            </p>
                        </div>
                    </section>
                )}

                {analysis.status === 'failed' && (
                    <section role="alert" className="rounded-lg border border-red-500/40 bg-red-500/10 p-5">
                        <p className="font-medium">The analysis failed</p>
                        <p className="mt-1 text-sm">{analysis.error ?? 'No reason was recorded.'}</p>
                    </section>
                )}

                {analysis.scanners && (
                    <section aria-label="Tools that ran">
                        <dl className="divide-y rounded-lg border text-sm">
                            {Object.entries(analysis.scanners).map(([tool, run]) => (
                                <div key={tool} className="flex flex-wrap items-center gap-x-4 gap-y-1 p-3">
                                    <dt className="w-24 font-mono font-medium">{tool}</dt>
                                    <dd className="text-muted-foreground min-w-0 flex-1 font-mono text-xs">
                                        {run.status === 'ok'
                                            ? `${run.findings} raw finding${run.findings === 1 ? '' : 's'} · ${run.seconds}s${run.version ? ` · v${run.version.replace(/^v/, '')}` : ''}`
                                            : (run.error ?? 'Did not run.')}
                                    </dd>
                                    <dd
                                        className={
                                            run.status === 'ok'
                                                ? 'font-mono text-xs text-emerald-600 dark:text-emerald-400'
                                                : 'font-mono text-xs text-red-600 dark:text-red-400'
                                        }
                                    >
                                        {run.status === 'ok' ? 'ran' : 'failed'}
                                    </dd>
                                </div>
                            ))}
                            {analysis.status === 'completed' && (
                                <div className="flex flex-wrap items-start gap-x-4 gap-y-1 p-3">
                                    <dt className="w-24 font-mono font-medium">AI review</dt>
                                    <dd className="min-w-0 flex-1 text-sm">
                                        {analysis.ai_summary ?? 'Not run.'}
                                        {analysis.ai_usage?.model && (
                                            <span className="text-muted-foreground mt-1 block font-mono text-xs">
                                                {analysis.ai_usage.model} · {analysis.ai_usage.input_tokens} in / {analysis.ai_usage.output_tokens}{' '}
                                                out tokens
                                                {typeof analysis.ai_usage.estimated_cost_usd === 'number' &&
                                                    ` · about $${analysis.ai_usage.estimated_cost_usd.toFixed(4)} (estimate)`}
                                            </span>
                                        )}
                                    </dd>
                                    <dd className="text-muted-foreground font-mono text-xs">
                                        {analysis.ai_mode === 'claude' ? 'Claude' : analysis.ai_mode === 'demo' ? 'demo mode' : 'off'}
                                    </dd>
                                </div>
                            )}
                        </dl>
                    </section>
                )}

                {analysis.status === 'completed' && (
                    <>
                        <section aria-label="Findings by severity" className="flex flex-wrap items-center gap-2">
                            <button
                                type="button"
                                aria-pressed={filters.severity === 'all'}
                                onClick={() => setFilters({ ...filters, severity: 'all' })}
                                className="aria-pressed:bg-secondary focus-visible:ring-ring min-h-9 rounded-md px-3 text-sm focus-visible:ring-2 focus-visible:outline-none"
                            >
                                All <span className="font-mono tabular-nums">{findings.length}</span>
                            </button>
                            {SEVERITIES.map((s) => (
                                <button
                                    key={s}
                                    type="button"
                                    aria-pressed={filters.severity === s}
                                    disabled={counts[s] === 0}
                                    onClick={() => setFilters({ ...filters, severity: s })}
                                    className="aria-pressed:bg-secondary focus-visible:ring-ring flex min-h-9 items-center gap-2 rounded-md px-2 focus-visible:ring-2 focus-visible:outline-none disabled:opacity-40"
                                >
                                    <SeverityBadge severity={s} /> <span className="font-mono text-sm tabular-nums">{counts[s]}</span>
                                </button>
                            ))}
                            <span className="text-muted-foreground ml-auto text-xs">
                                {verified} verified by a scanner · {findings.length - verified} AI suggestion
                                {findings.length - verified === 1 ? '' : 's'}
                            </span>
                        </section>

                        {findings.length === 0 ? (
                            <section className="rounded-lg border border-dashed p-8 text-center">
                                <p className="font-medium">The scanners reported nothing</p>
                                <p className="text-muted-foreground mx-auto mt-1 max-w-lg text-sm">
                                    That means none of the bundled rules matched. It does not mean the code is secure: these tools only find the
                                    patterns they know.
                                </p>
                            </section>
                        ) : (
                            <div className="grid gap-6 lg:grid-cols-[minmax(0,26rem)_minmax(0,1fr)]">
                                <section aria-label="Findings" className="min-w-0 space-y-3">
                                    <div className="flex flex-wrap gap-2">
                                        <label className="sr-only" htmlFor="find">
                                            Search findings
                                        </label>
                                        <Input
                                            id="find"
                                            value={filters.query}
                                            onChange={(e) => setFilters({ ...filters, query: e.target.value })}
                                            placeholder="Search findings"
                                            className="h-9 min-w-0 flex-1 basis-40"
                                        />
                                        <label className="sr-only" htmlFor="kind">
                                            Verified or AI
                                        </label>
                                        <select
                                            id="kind"
                                            className={select}
                                            value={filters.kind}
                                            onChange={(e) => setFilters({ ...filters, kind: e.target.value as FindingFilters['kind'] })}
                                        >
                                            <option value="all">Verified + AI</option>
                                            <option value="verified">Verified only</option>
                                            <option value="ai">AI only</option>
                                        </select>
                                        {files.length > 1 && (
                                            <>
                                                <label className="sr-only" htmlFor="file">
                                                    File
                                                </label>
                                                <select
                                                    id="file"
                                                    className={select}
                                                    value={String(filters.fileId)}
                                                    onChange={(e) =>
                                                        setFilters({ ...filters, fileId: e.target.value === 'all' ? 'all' : Number(e.target.value) })
                                                    }
                                                >
                                                    <option value="all">All files</option>
                                                    {files.map((f) => (
                                                        <option key={f.id} value={f.id}>
                                                            {f.name}
                                                        </option>
                                                    ))}
                                                </select>
                                            </>
                                        )}
                                    </div>

                                    {visible.length === 0 ? (
                                        <div className="rounded-lg border border-dashed p-6 text-center text-sm">
                                            <p>No findings match these filters.</p>
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                className="mt-3"
                                                onClick={() => setFilters({ severity: 'all', kind: 'all', fileId: 'all', query: '' })}
                                            >
                                                Clear filters
                                            </Button>
                                        </div>
                                    ) : (
                                        <ul className="divide-y rounded-lg border">
                                            {visible.map((f) => (
                                                <li key={f.id}>
                                                    <button
                                                        type="button"
                                                        aria-current={selected?.id === f.id}
                                                        onClick={() => setSelectedId(f.id)}
                                                        className="aria-[current=true]:bg-secondary hover:bg-muted/60 focus-visible:ring-ring w-full p-3 text-left focus-visible:ring-2 focus-visible:outline-none focus-visible:ring-inset"
                                                    >
                                                        <span className="flex flex-wrap items-center gap-2">
                                                            <SeverityBadge severity={f.severity} />
                                                            <SourceBadge source={f.source} verified={f.verified} />
                                                        </span>
                                                        <span className="mt-1.5 block text-sm font-medium">{f.title}</span>
                                                        <span className="text-muted-foreground block truncate font-mono text-xs">
                                                            {fileOf(f)?.name ?? 'whole analysis'}
                                                            {f.line_start !== null && `:${f.line_start}`}
                                                        </span>
                                                    </button>
                                                </li>
                                            ))}
                                        </ul>
                                    )}
                                </section>

                                <section className="min-w-0 rounded-lg border p-5 lg:sticky lg:top-4 lg:self-start">
                                    {selected ? (
                                        <FindingDetail finding={selected} file={fileOf(selected)} />
                                    ) : (
                                        <p className="text-muted-foreground text-sm">Choose a finding to see its details.</p>
                                    )}
                                </section>
                            </div>
                        )}
                    </>
                )}

                <section aria-label="Files">
                    <h2 className="mb-2 font-medium">Files</h2>
                    <ul className="divide-y rounded-lg border text-sm">
                        {files.map((f) => (
                            <li key={f.id} className="flex flex-wrap items-center gap-x-4 gap-y-1 p-3">
                                <span className="min-w-0 flex-1 truncate font-mono">{f.name}</span>
                                <span className="text-muted-foreground font-mono text-xs">
                                    {f.language} · {f.line_count} lines · {formatBytes(f.size_bytes)} ·{' '}
                                    {findings.filter((x) => x.analysis_file_id === f.id).length} findings
                                </span>
                            </li>
                        ))}
                    </ul>
                </section>

                <p className="text-muted-foreground text-xs">
                    CodeSentinel reports what its tools find. It cannot prove that code is free of vulnerabilities.{' '}
                    <Link href={route('analyses.create')} className="underline underline-offset-4">
                        Run another analysis
                    </Link>
                </p>
            </div>
        </AppLayout>
    );
}
