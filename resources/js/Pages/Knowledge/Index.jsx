import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';

const SOURCE_TYPES = [
    { value: 'text', label: 'Text', description: 'Paste documentation or notes directly.' },
    { value: 'faq', label: 'FAQ', description: 'Add a question and answer pair.' },
    { value: 'pdf', label: 'PDF', description: 'Upload a PDF up to 10 MB.' },
    { value: 'docx', label: 'Word', description: 'Upload a .docx file up to 10 MB.' },
];

export default function Index({ sources, bots }) {
    const stats = useMemo(() => {
        const ready = sources.filter((s) => s.status === 'ready').length;
        const processing = sources.filter((s) => s.status === 'processing' || s.status === 'draft').length;
        const failed = sources.filter((s) => s.status === 'failed').length;
        const chunks = sources.reduce((sum, s) => sum + (s.chunks_count || 0), 0);

        return { total: sources.length, ready, processing, failed, chunks };
    }, [sources]);

    return (
        <AuthenticatedLayout
            header={
                <div>
                    <h2 className="text-xl font-semibold text-gray-800 dark:text-gray-200">Knowledge</h2>
                    <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Add content for your bots to retrieve during conversations. Sources are chunked and embedded automatically.
                    </p>
                </div>
            }
        >
            <Head title="Knowledge" />

            <div className="mx-auto max-w-7xl space-y-6 p-6">
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard label="Total sources" value={stats.total} />
                    <StatCard label="Ready" value={stats.ready} />
                    <StatCard label="Processing" value={stats.processing} hint={stats.failed ? `${stats.failed} failed` : undefined} />
                    <StatCard label="Indexed chunks" value={stats.chunks} />
                </div>

                <div className="grid gap-6 xl:grid-cols-[400px_1fr]">
                    <AddSourceForm bots={bots} />
                    <SourcesList sources={sources} />
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function StatCard({ label, value, hint }) {
    return (
        <div className="rounded-lg bg-white p-5 shadow dark:bg-gray-800">
            <div className="text-sm text-gray-500 dark:text-gray-400">{label}</div>
            <div className="mt-2 text-3xl font-semibold text-gray-900 dark:text-white">{value}</div>
            {hint && <div className="mt-1 text-xs text-gray-500 dark:text-gray-400">{hint}</div>}
        </div>
    );
}

function AddSourceForm({ bots }) {
    const { data, setData, post, processing, errors, reset, recentlySuccessful } = useForm({
        bot_id: bots[0]?.id || '',
        type: 'text',
        title: '',
        raw_text: '',
        question: '',
        answer: '',
        file: null,
    });

    const selectedType = SOURCE_TYPES.find((t) => t.value === data.type);

    const submit = (e) => {
        e.preventDefault();
        post(route('knowledge-sources.store'), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => reset('title', 'raw_text', 'question', 'answer', 'file'),
        });
    };

    if (bots.length === 0) {
        return (
            <section className="rounded-lg border border-dashed border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
                <h3 className="text-base font-semibold text-gray-900 dark:text-white">Add source</h3>
                <p className="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    Create an active bot before adding knowledge sources.
                </p>
                <Link
                    href={route('bots.create')}
                    className="mt-4 inline-flex items-center rounded-md border border-transparent bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-gray-700 dark:bg-gray-200 dark:text-gray-800 dark:hover:bg-white"
                >
                    Create bot
                </Link>
            </section>
        );
    }

    return (
        <section className="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
            <h3 className="text-base font-semibold text-gray-900 dark:text-white">Add source</h3>
            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Content is queued for chunking and embedding after you submit.
            </p>

            <form onSubmit={submit} className="mt-5 space-y-5">
                <div>
                    <InputLabel htmlFor="bot_id" value="Bot" />
                    <select
                        id="bot_id"
                        value={data.bot_id}
                        onChange={(e) => setData('bot_id', e.target.value)}
                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                    >
                        {bots.map((bot) => (
                            <option key={bot.id} value={bot.id}>
                                {bot.name}
                            </option>
                        ))}
                    </select>
                    <InputError message={errors.bot_id} className="mt-1" />
                </div>

                <div>
                    <InputLabel value="Source type" />
                    <div className="mt-2 grid grid-cols-2 gap-2">
                        {SOURCE_TYPES.map((type) => (
                            <button
                                key={type.value}
                                type="button"
                                onClick={() => setData('type', type.value)}
                                className={`rounded-lg border px-3 py-2.5 text-left transition ${
                                    data.type === type.value
                                        ? 'border-indigo-500 bg-indigo-50 dark:border-indigo-500 dark:bg-indigo-950/40'
                                        : 'border-gray-200 hover:border-gray-300 dark:border-gray-700 dark:hover:border-gray-600'
                                }`}
                            >
                                <span className="block text-sm font-semibold text-gray-900 dark:text-white">{type.label}</span>
                                <span className="mt-0.5 block text-xs text-gray-500 dark:text-gray-400">{type.description}</span>
                            </button>
                        ))}
                    </div>
                    <InputError message={errors.type} className="mt-1" />
                </div>

                <div>
                    <InputLabel htmlFor="title" value="Title" />
                    <TextInput
                        id="title"
                        value={data.title}
                        onChange={(e) => setData('title', e.target.value)}
                        className="mt-1 block w-full"
                        placeholder="e.g. Returns policy"
                    />
                    <InputError message={errors.title} className="mt-1" />
                </div>

                {data.type === 'text' && (
                    <TextAreaField
                        id="raw_text"
                        label="Content"
                        value={data.raw_text}
                        onChange={(value) => setData('raw_text', value)}
                        error={errors.raw_text}
                        rows={8}
                        placeholder="Paste the knowledge text here…"
                    />
                )}

                {data.type === 'faq' && (
                    <>
                        <div>
                            <InputLabel htmlFor="question" value="Question" />
                            <TextInput
                                id="question"
                                value={data.question}
                                onChange={(e) => setData('question', e.target.value)}
                                className="mt-1 block w-full"
                                placeholder="How do I reset my password?"
                            />
                            <InputError message={errors.question} className="mt-1" />
                        </div>
                        <TextAreaField
                            id="answer"
                            label="Answer"
                            value={data.answer}
                            onChange={(value) => setData('answer', value)}
                            error={errors.answer}
                            rows={5}
                        />
                    </>
                )}

                {(data.type === 'pdf' || data.type === 'docx') && (
                    <div>
                        <InputLabel htmlFor="file" value="File" />
                        <input
                            id="file"
                            type="file"
                            accept={data.type === 'pdf' ? '.pdf,application/pdf' : '.docx,application/vnd.openxmlformats-officedocument.wordprocessingml.document'}
                            onChange={(e) => setData('file', e.target.files[0] || null)}
                            className="mt-1 block w-full text-sm text-gray-600 file:mr-3 file:rounded-md file:border-0 file:bg-gray-100 file:px-3 file:py-2 file:text-sm file:font-medium file:text-gray-700 hover:file:bg-gray-200 dark:text-gray-300 dark:file:bg-gray-700 dark:file:text-gray-200"
                        />
                        <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            {selectedType?.description}
                        </p>
                        {data.file && (
                            <p className="mt-1 text-xs text-gray-600 dark:text-gray-300">
                                Selected: {data.file.name} ({formatFileSize(data.file.size)})
                            </p>
                        )}
                        <InputError message={errors.file} className="mt-1" />
                    </div>
                )}

                <div className="flex flex-wrap items-center gap-3">
                    <PrimaryButton disabled={processing}>
                        {processing ? 'Queuing…' : 'Queue processing'}
                    </PrimaryButton>
                    {recentlySuccessful && (
                        <span className="text-sm text-emerald-600 dark:text-emerald-400">Queued.</span>
                    )}
                </div>
            </form>
        </section>
    );
}

function SourcesList({ sources }) {
    if (sources.length === 0) {
        return (
            <section className="flex min-h-[320px] flex-col items-center justify-center rounded-lg border border-dashed border-gray-200 bg-white px-6 py-12 text-center dark:border-gray-700 dark:bg-gray-800">
                <h3 className="text-lg font-semibold text-gray-900 dark:text-white">No knowledge sources yet</h3>
                <p className="mx-auto mt-2 max-w-md text-sm text-gray-500 dark:text-gray-400">
                    Add text, FAQs, or documents using the form. They will appear here once queued for processing.
                </p>
            </section>
        );
    }

    return (
        <section className="space-y-4">
            <div className="flex items-center justify-between gap-4">
                <h3 className="text-base font-semibold text-gray-900 dark:text-white">Sources</h3>
                <span className="text-sm text-gray-500 dark:text-gray-400">{sources.length} total</span>
            </div>

            <div className="hidden overflow-hidden rounded-lg bg-white shadow dark:bg-gray-800 lg:block">
                <table className="w-full text-left text-sm">
                    <thead className="border-b border-gray-100 bg-gray-50 text-xs uppercase tracking-wide text-gray-500 dark:border-gray-700 dark:bg-gray-900/50 dark:text-gray-400">
                        <tr>
                            <th className="px-5 py-3">Title</th>
                            <th className="px-5 py-3">Bot</th>
                            <th className="px-5 py-3">Type</th>
                            <th className="px-5 py-3">Status</th>
                            <th className="px-5 py-3">Chunks</th>
                            <th className="px-5 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                        {sources.map((source) => (
                            <SourceRow key={source.id} source={source} layout="table" />
                        ))}
                    </tbody>
                </table>
            </div>

            <div className="grid gap-4 lg:hidden">
                {sources.map((source) => (
                    <SourceRow key={source.id} source={source} layout="card" />
                ))}
            </div>
        </section>
    );
}

function SourceRow({ source, layout }) {
    const [busy, setBusy] = useState(false);

    const reprocess = () => {
        setBusy(true);
        router.post(route('knowledge-sources.reprocess', source.id), {}, {
            preserveScroll: true,
            onFinish: () => setBusy(false),
        });
    };

    const destroy = () => {
        if (!window.confirm(`Delete "${source.title}"? This cannot be undone.`)) {
            return;
        }

        setBusy(true);
        router.delete(route('knowledge-sources.destroy', source.id), {
            preserveScroll: true,
            onFinish: () => setBusy(false),
        });
    };

    if (layout === 'card') {
        return (
            <article className="rounded-lg bg-white p-5 shadow dark:bg-gray-800">
                <div className="flex items-start justify-between gap-3">
                    <div className="min-w-0">
                        <Link
                            href={route('knowledge-sources.show', source.id)}
                            className="font-semibold text-gray-900 hover:text-indigo-600 dark:text-white dark:hover:text-indigo-400"
                        >
                            {source.title}
                        </Link>
                        <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">{source.bot?.name}</p>
                    </div>
                    <StatusBadge status={source.status} />
                </div>
                <div className="mt-3 flex flex-wrap items-center gap-2">
                    <TypeBadge type={source.type} />
                    <span className="text-xs text-gray-500 dark:text-gray-400">{source.chunks_count} chunks</span>
                </div>
                <SourceActions sourceId={source.id} busy={busy} onReprocess={reprocess} onDelete={destroy} className="mt-4" />
            </article>
        );
    }

    return (
        <tr className="hover:bg-gray-50/80 dark:hover:bg-gray-900/40">
            <td className="px-5 py-4">
                <Link
                    href={route('knowledge-sources.show', source.id)}
                    className="font-medium text-gray-900 hover:text-indigo-600 dark:text-white dark:hover:text-indigo-400"
                >
                    {source.title}
                </Link>
            </td>
            <td className="px-5 py-4 text-gray-700 dark:text-gray-300">{source.bot?.name}</td>
            <td className="px-5 py-4">
                <TypeBadge type={source.type} />
            </td>
            <td className="px-5 py-4">
                <StatusBadge status={source.status} />
            </td>
            <td className="px-5 py-4 text-gray-700 dark:text-gray-300">{source.chunks_count}</td>
            <td className="px-5 py-4 text-right">
                <SourceActions sourceId={source.id} busy={busy} onReprocess={reprocess} onDelete={destroy} />
            </td>
        </tr>
    );
}

function SourceActions({ sourceId, busy, onReprocess, onDelete, className = '' }) {
    return (
        <div className={`flex flex-wrap justify-end gap-2 ${className}`}>
            <Link
                href={route('knowledge-sources.show', sourceId)}
                className="text-xs font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400"
            >
                View
            </Link>
            <button
                type="button"
                disabled={busy}
                onClick={onReprocess}
                className="text-xs font-medium text-indigo-600 hover:text-indigo-500 disabled:opacity-50 dark:text-indigo-400"
            >
                {busy ? 'Working…' : 'Reprocess'}
            </button>
            <button
                type="button"
                disabled={busy}
                onClick={onDelete}
                className="text-xs font-medium text-red-600 hover:text-red-500 disabled:opacity-50 dark:text-red-400"
            >
                Delete
            </button>
        </div>
    );
}

function StatusBadge({ status }) {
    const styles = {
        ready: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300',
        processing: 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300',
        draft: 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
        failed: 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
    };

    return (
        <span className={`inline-flex rounded-full px-2.5 py-1 text-xs font-semibold capitalize ${styles[status] || styles.draft}`}>
            {status}
        </span>
    );
}

function TypeBadge({ type }) {
    const labels = { text: 'Text', faq: 'FAQ', pdf: 'PDF', docx: 'Word' };

    return (
        <span className="inline-flex rounded-md bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700 dark:bg-gray-700 dark:text-gray-300">
            {labels[type] || type}
        </span>
    );
}

function TextAreaField({ id, label, value, onChange, error, rows = 4, placeholder }) {
    return (
        <div>
            <InputLabel htmlFor={id} value={label} />
            <textarea
                id={id}
                rows={rows}
                value={value}
                onChange={(e) => onChange(e.target.value)}
                placeholder={placeholder}
                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
            />
            <InputError message={error} className="mt-1" />
        </div>
    );
}

function formatFileSize(bytes) {
    if (!bytes) return '0 B';
    const units = ['B', 'KB', 'MB'];
    let size = bytes;
    let unit = 0;
    while (size >= 1024 && unit < units.length - 1) {
        size /= 1024;
        unit += 1;
    }
    return `${size.toFixed(unit === 0 ? 0 : 1)} ${units[unit]}`;
}
