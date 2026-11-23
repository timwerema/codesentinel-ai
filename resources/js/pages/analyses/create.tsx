import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { formatBytes } from '@/lib/findings';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { LoaderCircle, Upload, X } from 'lucide-react';
import { type FormEvent, useRef } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Analyses', href: '/analyses' },
    { title: 'New', href: '/analyses/new' },
];

interface Props {
    languages: Record<string, string>;
    limits: { max_files: number; max_file_bytes: number; max_total_bytes: number };
}

export default function AnalysesCreate({ languages, limits }: Props) {
    const extensions = Object.keys(languages);
    const picker = useRef<HTMLInputElement>(null);
    const { data, setData, post, processing, errors, clearErrors } = useForm<{
        title: string;
        mode: 'paste' | 'upload';
        code: string;
        language: string;
        files: File[];
    }>({
        title: '',
        mode: 'paste',
        code: '',
        language: 'py',
        files: [],
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post(route('analyses.store'), { forceFormData: true });
    };

    const addFiles = (list: FileList | null) => {
        if (!list) return;
        const names = new Set(data.files.map((f) => f.name + f.size));
        setData('files', [...data.files, ...[...list].filter((f) => !names.has(f.name + f.size))].slice(0, limits.max_files));
        clearErrors('files');
        if (picker.current) picker.current.value = '';
    };

    const fileErrors = Object.entries(errors)
        .filter(([k]) => k === 'files' || k.startsWith('files.'))
        .map(([, v]) => v);
    const tab =
        'aria-selected:border-primary aria-selected:text-foreground text-muted-foreground hover:text-foreground focus-visible:ring-ring -mb-px min-h-10 border-b-2 border-transparent px-4 text-sm font-medium focus-visible:ring-2 focus-visible:outline-none';

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="New analysis" />
            <form onSubmit={submit} className="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-6 p-4 md:p-6">
                <header>
                    <h1 className="text-2xl font-semibold tracking-tight">New analysis</h1>
                    <p className="text-muted-foreground mt-1 text-sm">
                        Your code is scanned as text by Semgrep and Gitleaks. It is never run, and only you can see it.
                    </p>
                </header>

                <div className="grid gap-2">
                    <Label htmlFor="title">
                        Title <span className="text-muted-foreground font-normal">(optional)</span>
                    </Label>
                    <Input
                        id="title"
                        value={data.title}
                        onChange={(e) => setData('title', e.target.value)}
                        maxLength={120}
                        placeholder="e.g. Login handler"
                    />
                    <InputError message={errors.title} />
                </div>

                <div>
                    <div role="tablist" aria-label="How to add code" className="flex border-b">
                        {(['paste', 'upload'] as const).map((m) => (
                            <button
                                key={m}
                                type="button"
                                role="tab"
                                id={`tab-${m}`}
                                aria-selected={data.mode === m}
                                aria-controls={`panel-${m}`}
                                className={tab}
                                onClick={() => setData('mode', m)}
                            >
                                {m === 'paste' ? 'Paste code' : 'Upload files'}
                            </button>
                        ))}
                    </div>

                    {data.mode === 'paste' ? (
                        <div role="tabpanel" id="panel-paste" aria-labelledby="tab-paste" className="grid gap-4 pt-4">
                            <div className="grid gap-2 sm:max-w-xs">
                                <Label htmlFor="language">Language</Label>
                                <select
                                    id="language"
                                    value={data.language}
                                    onChange={(e) => setData('language', e.target.value)}
                                    className="border-input bg-background focus-visible:ring-ring h-10 rounded-md border px-3 text-sm focus-visible:ring-2 focus-visible:outline-none"
                                >
                                    {extensions.map((ext) => (
                                        <option key={ext} value={ext}>
                                            {languages[ext]} (.{ext})
                                        </option>
                                    ))}
                                </select>
                                <InputError message={errors.language} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="code">Code</Label>
                                <textarea
                                    id="code"
                                    value={data.code}
                                    onChange={(e) => setData('code', e.target.value)}
                                    spellCheck={false}
                                    rows={16}
                                    className="code-pane border-input bg-card focus-visible:ring-ring w-full rounded-md border p-3 focus-visible:ring-2 focus-visible:outline-none"
                                    placeholder="Paste source code here"
                                    aria-describedby="code-help"
                                />
                                <p id="code-help" className="text-muted-foreground text-xs">
                                    {formatBytes(new Blob([data.code]).size)} of {formatBytes(limits.max_file_bytes)}
                                </p>
                                <InputError message={errors.code} />
                            </div>
                        </div>
                    ) : (
                        <div role="tabpanel" id="panel-upload" aria-labelledby="tab-upload" className="grid gap-4 pt-4">
                            <input
                                ref={picker}
                                id="files"
                                type="file"
                                multiple
                                accept={extensions.map((e) => `.${e}`).join(',')}
                                className="sr-only"
                                onChange={(e) => addFiles(e.target.files)}
                            />
                            <label
                                htmlFor="files"
                                onDragOver={(e) => e.preventDefault()}
                                onDrop={(e) => {
                                    e.preventDefault();
                                    addFiles(e.dataTransfer.files);
                                }}
                                className="hover:border-primary focus-within:ring-ring flex cursor-pointer flex-col items-center gap-2 rounded-lg border border-dashed p-8 text-center focus-within:ring-2"
                            >
                                <Upload className="text-muted-foreground size-6" aria-hidden="true" />
                                <span className="font-medium">Choose files or drop them here</span>
                                <span className="text-muted-foreground text-xs">
                                    {extensions.map((e) => `.${e}`).join(' ')} · up to {limits.max_files} files, {formatBytes(limits.max_file_bytes)}{' '}
                                    each
                                </span>
                            </label>
                            {data.files.length > 0 && (
                                <ul className="divide-y rounded-lg border" aria-label="Files to analyse">
                                    {data.files.map((f, i) => (
                                        <li key={f.name + f.size} className="flex items-center gap-3 p-3">
                                            <span className="min-w-0 flex-1 truncate font-mono text-sm">{f.name}</span>
                                            <span className="text-muted-foreground font-mono text-xs">{formatBytes(f.size)}</span>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                aria-label={`Remove ${f.name}`}
                                                onClick={() =>
                                                    setData(
                                                        'files',
                                                        data.files.filter((_, j) => j !== i),
                                                    )
                                                }
                                            >
                                                <X className="size-4" aria-hidden="true" />
                                            </Button>
                                        </li>
                                    ))}
                                </ul>
                            )}
                            {fileErrors.map((m) => (
                                <InputError key={m} message={m} />
                            ))}
                        </div>
                    )}
                </div>

                <InputError message={errors.mode} />

                <div className="flex items-center gap-3">
                    <Button disabled={processing || (data.mode === 'paste' ? data.code.trim() === '' : data.files.length === 0)}>
                        {processing && <LoaderCircle className="size-4 animate-spin" aria-hidden="true" />}
                        Analyse
                    </Button>
                    <p className="text-muted-foreground text-xs">Usually takes a few seconds.</p>
                </div>
            </form>
        </AppLayout>
    );
}
