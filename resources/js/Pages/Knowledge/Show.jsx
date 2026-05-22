import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

export default function Show({ source, chunks }) {
    const [busy, setBusy] = useState(false);
    const [expandedChunks, setExpandedChunks] = useState({});

    const reprocess = () => {
        setBusy(true);
        router.post(route('knowledge-sources.reprocess', source.id), {
            preserveScroll: true,
            onFinish: () => setBusy(false),
        });
    };

    const destroy = () => {
        if (!window.confirm(`Delete "${source.title}"? This cannot be undone.`)) {
            return;
        }

        router.delete(route('knowledge-sources.destroy', source.id));
    };

    const toggleChunk = (id) => {
        setExpandedChunks((prev) => ({ ...prev, [id]: !prev[id] }));
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div className="min-w-0">
                        <h2 className="truncate text-xl font-semibold text-gray-800 dark:text-gray-200">{source.title}</h2>
                        <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Review indexing status and generated chunks for this source.
                        </p>
                    </div>
                    <Link
                        href={route('knowledge-sources.index')}
                        className="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold normal-case tracking-normal text-gray-700 shadow-sm transition hover:bg-gray-50 dark:border-gray-500 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                    >
                        Back to knowledge
                    </Link>
                </div>
            }
        >
            <Head title={source.title} />

            <div className="mx-auto max-w-5xl space-y-6 p-6">
                <section className="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                    <div className="flex flex-wrap items-start justify-between gap-4">
                        <div className="flex flex-wrap gap-2">
                            <StatusBadge status={source.status} />
                            <TypeBadge type={source.type} />
                        </div>
                        <div className="flex flex-wrap gap-2">
                            <SecondaryButton type="button" onClick={reprocess} disabled={busy} className="!normal-case !tracking-normal">
                                {busy ? 'Queuing…' : 'Reprocess'}
                            </SecondaryButton>
                            <button
                                type="button"
                                onClick={destroy}
                                disabled={busy}
                                className="inline-flex items-center rounded-md border border-transparent px-4 py-2 text-xs font-semibold uppercase tracking-widest text-red-600 transition hover:text-red-500 focus:outline-none disabled:opacity-50 dark:text-red-400"
                            >
                                Delete
                            </button>
                        </div>
                    </div>

                    <dl className="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <MetaItem label="Bot" value={source.bot?.name || '—'} />
                        <MetaItem label="Chunks" value={source.chunks_count ?? 0} />
                        <MetaItem label="Last indexed" value={formatDateTime(source.last_indexed_at)} />
                        {source.original_file_name && (
                            <MetaItem label="File" value={source.original_file_name} />
                        )}
                        {source.file_size != null && (
                            <MetaItem label="File size" value={formatFileSize(source.file_size)} />
                        )}
                        {source.mime_type && <MetaItem label="MIME type" value={source.mime_type} />}
                    </dl>

                    {source.error_message && (
                        <div className="mt-5 rounded-lg border border-red-200 bg-red-50 p-4 dark:border-red-900/50 dark:bg-red-950/40">
                            <h4 className="text-sm font-semibold text-red-800 dark:text-red-200">Processing error</h4>
                            <pre className="mt-2 overflow-x-auto whitespace-pre-wrap text-sm text-red-700 dark:text-red-300">
                                {source.error_message}
                            </pre>
                        </div>
                    )}

                    {source.status === 'processing' && (
                        <div className="mt-5 flex items-center gap-3 rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800 dark:border-blue-900/50 dark:bg-blue-950/30 dark:text-blue-200">
                            <span className="inline-block h-4 w-4 animate-spin rounded-full border-2 border-blue-600 border-t-transparent dark:border-blue-300" />
                            This source is being processed. Refresh the page to see updated chunks.
                        </div>
                    )}
                </section>

                <section>
                    <div className="mb-4 flex items-center justify-between gap-4">
                        <h3 className="text-base font-semibold text-gray-900 dark:text-white">
                            Chunks ({chunks.length})
                        </h3>
                        {chunks.length > 0 && (
                            <button
                                type="button"
                                onClick={() => {
                                    const allExpanded = chunks.every((c) => expandedChunks[c.id]);
                                    const next = {};
                                    chunks.forEach((c) => {
                                        next[c.id] = !allExpanded;
                                    });
                                    setExpandedChunks(next);
                                }}
                                className="text-xs font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400"
                            >
                                {chunks.every((c) => expandedChunks[c.id]) ? 'Collapse all' : 'Expand all'}
                            </button>
                        )}
                    </div>

                    {chunks.length === 0 ? (
                        <div className="rounded-lg border border-dashed border-gray-200 bg-white px-6 py-10 text-center dark:border-gray-700 dark:bg-gray-800">
                            <p className="text-sm text-gray-500 dark:text-gray-400">
                                {source.status === 'ready'
                                    ? 'No chunks were stored for this source.'
                                    : 'Chunks will appear here after processing completes.'}
                            </p>
                            {source.status !== 'processing' && (
                                <PrimaryButton type="button" onClick={reprocess} disabled={busy} className="mt-4">
                                    {busy ? 'Queuing…' : 'Reprocess now'}
                                </PrimaryButton>
                            )}
                        </div>
                    ) : (
                        <div className="space-y-3">
                            {chunks.map((chunk) => {
                                const expanded = expandedChunks[chunk.id] ?? false;
                                const preview = chunk.content.length > 280 && !expanded;

                                return (
                                    <article
                                        key={chunk.id}
                                        className="rounded-lg bg-white p-5 shadow dark:bg-gray-800"
                                    >
                                        <div className="flex items-center justify-between gap-3">
                                            <h4 className="text-sm font-semibold text-gray-900 dark:text-white">
                                                Chunk {chunk.chunk_index}
                                            </h4>
                                            <button
                                                type="button"
                                                onClick={() => toggleChunk(chunk.id)}
                                                className="text-xs font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400"
                                            >
                                                {expanded ? 'Show less' : 'Show more'}
                                            </button>
                                        </div>
                                        <p
                                            className={`mt-3 whitespace-pre-wrap text-sm text-gray-700 dark:text-gray-300 ${
                                                preview ? 'line-clamp-4' : ''
                                            }`}
                                        >
                                            {chunk.content}
                                        </p>
                                        {preview && (
                                            <p className="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                                {chunk.content.length.toLocaleString()} characters
                                            </p>
                                        )}
                                    </article>
                                );
                            })}
                        </div>
                    )}
                </section>
            </div>
        </AuthenticatedLayout>
    );
}

function MetaItem({ label, value }) {
    return (
        <div className="rounded-lg border border-gray-100 bg-gray-50 px-4 py-3 dark:border-gray-700 dark:bg-gray-900/50">
            <dt className="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{label}</dt>
            <dd className="mt-1 text-sm font-medium text-gray-900 dark:text-white">{value}</dd>
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
        <span className="inline-flex rounded-md bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-700 dark:bg-gray-700 dark:text-gray-300">
            {labels[type] || type}
        </span>
    );
}

function formatDateTime(value) {
    if (!value) return '—';

    try {
        return new Intl.DateTimeFormat(undefined, {
            dateStyle: 'medium',
            timeStyle: 'short',
        }).format(new Date(value));
    } catch {
        return value;
    }
}

function formatFileSize(bytes) {
    if (!bytes) return '—';
    const units = ['B', 'KB', 'MB'];
    let size = bytes;
    let unit = 0;
    while (size >= 1024 && unit < units.length - 1) {
        size /= 1024;
        unit += 1;
    }
    return `${size.toFixed(unit === 0 ? 0 : 1)} ${units[unit]}`;
}
