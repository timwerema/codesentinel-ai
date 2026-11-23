import { DemoBadge, StatusBadge } from '@/components/badges';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { type AnalysisRow, type Paginated } from '@/types/analysis';
import { Head, Link, router } from '@inertiajs/react';
import { FilePlus2, Search } from 'lucide-react';
import { type FormEvent, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Analyses', href: '/analyses' }];
const STATUSES = ['', 'completed', 'running', 'queued', 'failed'] as const;

export default function AnalysesIndex({ analyses, filters }: { analyses: Paginated<AnalysisRow>; filters: { q: string; status: string } }) {
    const [q, setQ] = useState(filters.q);
    const filtered = filters.q !== '' || filters.status !== '';

    const apply = (next: { q?: string; status?: string }) => {
        const params = { q: next.q ?? q, status: next.status ?? filters.status };
        router.get(route('analyses.index'), Object.fromEntries(Object.entries(params).filter(([, v]) => v !== '')), {
            preserveState: true,
            replace: true,
        });
    };
    const submit = (e: FormEvent) => {
        e.preventDefault();
        apply({});
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Analyses" />
            <div className="mx-auto flex w-full max-w-6xl min-w-0 flex-1 flex-col gap-6 p-4 md:p-6">
                <header className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">Analyses</h1>
                        <p className="text-muted-foreground mt-1 text-sm">
                            {analyses.total} {filtered ? 'matching' : 'in total'}
                        </p>
                    </div>
                    <Button asChild>
                        <Link href={route('analyses.create')}>
                            <FilePlus2 className="size-4" aria-hidden="true" /> New analysis
                        </Link>
                    </Button>
                </header>

                <div className="flex flex-wrap items-center gap-3">
                    <form onSubmit={submit} role="search" className="relative min-w-0 flex-1 basis-64">
                        <label htmlFor="q" className="sr-only">
                            Search by title or file name
                        </label>
                        <Search
                            className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2"
                            aria-hidden="true"
                        />
                        <Input
                            id="q"
                            value={q}
                            onChange={(e) => setQ(e.target.value)}
                            placeholder="Search by title or file name"
                            className="pl-9"
                            maxLength={80}
                        />
                    </form>
                    <div role="group" aria-label="Filter by status" className="flex flex-wrap gap-1">
                        {STATUSES.map((s) => (
                            <button
                                key={s || 'all'}
                                type="button"
                                aria-pressed={filters.status === s}
                                onClick={() => apply({ status: s })}
                                className="aria-pressed:bg-secondary aria-pressed:text-foreground text-muted-foreground hover:text-foreground focus-visible:ring-ring min-h-9 rounded-md px-3 text-sm capitalize focus-visible:ring-2 focus-visible:outline-none"
                            >
                                {s || 'All'}
                            </button>
                        ))}
                    </div>
                </div>

                {analyses.data.length === 0 ? (
                    <div className="rounded-lg border border-dashed p-8 text-center">
                        <p className="font-medium">{filtered ? 'Nothing matches those filters' : 'No analyses yet'}</p>
                        <p className="text-muted-foreground mt-1 text-sm">
                            {filtered ? 'Try a different search or status.' : 'Paste a snippet or upload files to run the first one.'}
                        </p>
                        {filtered ? (
                            <Button variant="outline" className="mt-4" onClick={() => router.get(route('analyses.index'))}>
                                Clear filters
                            </Button>
                        ) : (
                            <Button asChild className="mt-4">
                                <Link href={route('analyses.create')}>New analysis</Link>
                            </Button>
                        )}
                    </div>
                ) : (
                    <div className="max-w-full overflow-x-auto rounded-lg border" role="region" aria-label="Analyses table" tabIndex={0}>
                        <table className="w-full min-w-[640px] text-left text-sm">
                            <thead className="text-muted-foreground border-b font-mono text-[11px] tracking-widest uppercase">
                                <tr>
                                    <th scope="col" className="p-3 font-medium">
                                        Analysis
                                    </th>
                                    <th scope="col" className="p-3 font-medium">
                                        Languages
                                    </th>
                                    <th scope="col" className="p-3 text-right font-medium">
                                        Findings
                                    </th>
                                    <th scope="col" className="p-3 font-medium">
                                        Status
                                    </th>
                                    <th scope="col" className="p-3 font-medium">
                                        Started
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {analyses.data.map((a) => (
                                    <tr key={a.id} className="hover:bg-muted/60">
                                        <td className="max-w-xs p-3">
                                            <Link href={route('analyses.show', a.id)} className="font-medium underline-offset-4 hover:underline">
                                                {a.title}
                                            </Link>
                                            <span className="text-muted-foreground block truncate font-mono text-xs">
                                                {a.files.length} file{a.files.length === 1 ? '' : 's'} · {a.origin}
                                            </span>
                                            {a.is_demo && <DemoBadge />}
                                        </td>
                                        <td className="text-muted-foreground p-3 font-mono text-xs">
                                            {[...new Set(a.files.map((f) => f.language))].join(', ')}
                                        </td>
                                        <td className="p-3 text-right font-mono tabular-nums">{a.status === 'completed' ? a.findings_count : '–'}</td>
                                        <td className="p-3">
                                            <StatusBadge status={a.status} />
                                        </td>
                                        <td className="text-muted-foreground p-3 text-xs whitespace-nowrap">
                                            <time dateTime={a.created_at}>{new Date(a.created_at).toLocaleString()}</time>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                {analyses.last_page > 1 && (
                    <nav aria-label="Pages" className="flex items-center justify-between text-sm">
                        <span className="text-muted-foreground">
                            Page {analyses.current_page} of {analyses.last_page}
                        </span>
                        <span className="flex gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={!analyses.prev_page_url}
                                onClick={() => analyses.prev_page_url && router.get(analyses.prev_page_url)}
                            >
                                Previous
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={!analyses.next_page_url}
                                onClick={() => analyses.next_page_url && router.get(analyses.next_page_url)}
                            >
                                Next
                            </Button>
                        </span>
                    </nav>
                )}
            </div>
        </AppLayout>
    );
}
