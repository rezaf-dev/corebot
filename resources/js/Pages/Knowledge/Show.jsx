import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import {
    canCancel,
    canEdit,
    canReprocess,
    isInProgress,
    normalizeStatus,
    statusDescription,
    statusLabel,
    statusStyles,
} from '@/Pages/Knowledge/sourceStatus';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';

export default function Show({ source, faq, chunks }) {
    const [busy, setBusy] = useState(false);
    const [editing, setEditing] = useState(false);
    const [expandedChunks, setExpandedChunks] = useState({});
    const status = normalizeStatus(source.status);
    const inProgress = isInProgress(source.status);
    const editable = canEdit(source.status);
    const fileOnlyEdit = editable && (source.type === 'pdf' || source.type === 'docx');

    useEffect(() => {
        if (!inProgress) {
            return undefined;
        }

        const interval = window.setInterval(() => {
            router.reload({ preserveScroll: true, preserveState: true });
        }, 4000);

        return () => window.clearInterval(interval);
    }, [inProgress, source.status]);

    const runAction = (callback) => {
        setBusy(true);
        callback({
            preserveScroll: true,
            onFinish: () => setBusy(false),
        });
    };

    const reprocess = () => {
        if (!canReprocess(source.status)) {
            return;
        }

        runAction((options) => router.post(route('knowledge-sources.reprocess', source.id), {}, options));
    };

    const cancelProcessing = () => {
        if (!window.confirm('Cancel indexing for this source?')) {
            return;
        }

        runAction((options) => router.post(route('knowledge-sources.cancel', source.id), {}, options));
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
                        <div className="min-w-0 space-y-3">
                            <div className="flex flex-wrap gap-2">
                                <StatusBadge status={source.status} />
                                <TypeBadge type={source.type} />
                            </div>
                            {inProgress && <ProcessingProgress status={status} />}
                        </div>
                        <div className="flex flex-wrap gap-2">
                            {editable && (
                                <SecondaryButton
                                    type="button"
                                    onClick={() => setEditing((v) => !v)}
                                    disabled={busy || inProgress}
                                    className="!normal-case !tracking-normal"
                                >
                                    {editing ? 'Close editor' : 'Edit'}
                                </SecondaryButton>
                            )}
                            {canReprocess(source.status) && (
                                <SecondaryButton type="button" onClick={reprocess} disabled={busy} className="!normal-case !tracking-normal">
                                    {busy ? 'Queuing…' : 'Reindex'}
                                </SecondaryButton>
                            )}
                            {canCancel(source.status) && (
                                <SecondaryButton type="button" onClick={cancelProcessing} disabled={busy} className="!normal-case !tracking-normal">
                                    Cancel indexing
                                </SecondaryButton>
                            )}
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

                    {editing && editable && (
                        <EditSourcePanel
                            source={source}
                            faq={faq}
                            fileOnly={fileOnlyEdit}
                            onClose={() => setEditing(false)}
                            className="mt-5 border-t border-gray-100 pt-5 dark:border-gray-700"
                        />
                    )}

                    {!editing && (
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
                    )}

                    {source.error_message && (
                        <div className="mt-5 rounded-lg border border-red-200 bg-red-50 p-4 dark:border-red-900/50 dark:bg-red-950/40">
                            <h4 className="text-sm font-semibold text-red-800 dark:text-red-200">Indexing error</h4>
                            <pre className="mt-2 overflow-x-auto whitespace-pre-wrap text-sm text-red-700 dark:text-red-300">
                                {source.error_message}
                            </pre>
                        </div>
                    )}

                    {inProgress && (
                        <div className="mt-5 flex items-center gap-3 rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800 dark:border-blue-900/50 dark:bg-blue-950/30 dark:text-blue-200">
                            <span className="inline-block h-4 w-4 shrink-0 animate-spin rounded-full border-2 border-blue-600 border-t-transparent dark:border-blue-300" />
                            {status === 'queued'
                                ? 'Waiting in the queue. This page updates automatically.'
                                : 'Extracting text and building embeddings. This page updates automatically.'}
                        </div>
                    )}

                    {status === 'cancelled' && (
                        <div className="mt-5 rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-700 dark:border-gray-600 dark:bg-gray-900/50 dark:text-gray-300">
                            Indexing was cancelled. Use Reindex to try again, or delete this source.
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
                                {status === 'ready'
                                    ? 'No chunks were stored for this source.'
                                    : 'Chunks will appear here after indexing completes.'}
                            </p>
                            {canReprocess(source.status) && !inProgress && (
                                <PrimaryButton type="button" onClick={reprocess} disabled={busy} className="mt-4">
                                    {busy ? 'Queuing…' : 'Reindex now'}
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

function EditSourcePanel({ source, faq, fileOnly, onClose, className = '' }) {
    const { data, setData, put, processing, errors } = useForm({
        title: source.title,
        raw_text: source.raw_text || '',
        question: faq?.question || '',
        answer: faq?.answer || '',
    });

    const submit = (e) => {
        e.preventDefault();
        put(route('knowledge-sources.update', source.id), {
            preserveScroll: true,
            onSuccess: () => onClose(),
        });
    };

    return (
        <form onSubmit={submit} className={`space-y-4 ${className}`}>
            <p className="text-sm text-gray-500 dark:text-gray-400">
                {fileOnly
                    ? 'You can rename this document. To change the file, delete this source and upload a new one.'
                    : 'Saving will requeue this source for indexing.'}
            </p>
            <div>
                <InputLabel htmlFor="edit-title" value="Title" />
                <TextInput
                    id="edit-title"
                    value={data.title}
                    onChange={(e) => setData('title', e.target.value)}
                    className="mt-1 block w-full"
                />
                <InputError message={errors.title} className="mt-1" />
            </div>
            {!fileOnly && source.type === 'text' && (
                <div>
                    <InputLabel htmlFor="edit-raw" value="Content" />
                    <textarea
                        id="edit-raw"
                        rows={10}
                        value={data.raw_text}
                        onChange={(e) => setData('raw_text', e.target.value)}
                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                    />
                    <InputError message={errors.raw_text} className="mt-1" />
                </div>
            )}
            {!fileOnly && source.type === 'faq' && (
                <>
                    <div>
                        <InputLabel htmlFor="edit-question" value="Question" />
                        <TextInput
                            id="edit-question"
                            value={data.question}
                            onChange={(e) => setData('question', e.target.value)}
                            className="mt-1 block w-full"
                        />
                        <InputError message={errors.question} className="mt-1" />
                    </div>
                    <div>
                        <InputLabel htmlFor="edit-answer" value="Answer" />
                        <textarea
                            id="edit-answer"
                            rows={6}
                            value={data.answer}
                            onChange={(e) => setData('answer', e.target.value)}
                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                        />
                        <InputError message={errors.answer} className="mt-1" />
                    </div>
                </>
            )}
            <div className="flex flex-wrap gap-2">
                <PrimaryButton disabled={processing} className="!normal-case !tracking-normal">
                    {processing ? 'Saving…' : fileOnly ? 'Save title' : 'Save & reindex'}
                </PrimaryButton>
                <SecondaryButton type="button" onClick={onClose} className="!normal-case !tracking-normal">
                    Cancel
                </SecondaryButton>
            </div>
        </form>
    );
}

function StatusBadge({ status }) {
    return (
        <span
            className={`inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold ${statusStyles(status)}`}
            title={statusDescription(status)}
        >
            {isInProgress(status) && (
                <span className="inline-block h-2 w-2 animate-pulse rounded-full bg-current opacity-80" />
            )}
            {statusLabel(status)}
        </span>
    );
}

function ProcessingProgress({ status }) {
    const isIndexing = status === 'processing';

    return (
        <div className="max-w-md">
            <div className="h-1.5 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                <div className={`h-full rounded-full bg-indigo-500 ${isIndexing ? 'w-2/3 animate-pulse' : 'w-1/3'}`} />
            </div>
            <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">{statusDescription(status)}</p>
        </div>
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

function MetaItem({ label, value }) {
    return (
        <div className="rounded-lg border border-gray-100 bg-gray-50 px-4 py-3 dark:border-gray-700 dark:bg-gray-900/50">
            <dt className="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{label}</dt>
            <dd className="mt-1 text-sm font-medium text-gray-900 dark:text-white">{value}</dd>
        </div>
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
