import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import Modal from '@/Components/Modal';
import Pagination from '@/Components/Pagination';
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

const SOURCE_TYPES = [
    { value: 'text', label: 'Text', description: 'Paste documentation or notes directly.' },
    { value: 'faq', label: 'FAQ', description: 'Add a question and answer pair.' },
    { value: 'pdf', label: 'PDF', description: 'Upload a PDF up to 10 MB.' },
    { value: 'docx', label: 'Word', description: 'Upload a .docx file up to 10 MB.' },
];

export default function Index({ sources, bots, filters, stats, hasActiveSources }) {
    const sourceItems = sources.data ?? [];
    const [showAddModal, setShowAddModal] = useState(false);
    const canAddSources = bots.length > 0;

    useEffect(() => {
        if (!hasActiveSources) {
            return undefined;
        }

        const interval = window.setInterval(() => {
            router.reload({
                only: ['sources', 'stats', 'hasActiveSources'],
                preserveScroll: true,
                preserveState: true,
            });
        }, 4000);

        return () => window.clearInterval(interval);
    }, [hasActiveSources]);

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
                {hasActiveSources && (
                    <div className="flex items-center gap-3 rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800 dark:border-blue-900/50 dark:bg-blue-950/30 dark:text-blue-200">
                        <span className="inline-block h-4 w-4 shrink-0 animate-spin rounded-full border-2 border-blue-600 border-t-transparent dark:border-blue-300" />
                        Sources are indexing in the background. This page refreshes every few seconds until they finish.
                    </div>
                )}

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard label="Total sources" value={stats.total} />
                    <StatCard label="Ready" value={stats.ready} />
                    <StatCard label="In progress" value={stats.processing} hint={stats.failed ? `${stats.failed} failed` : undefined} />
                    <StatCard label="Indexed chunks" value={stats.chunks} />
                </div>

                {!canAddSources && (
                    <section className="rounded-lg border border-dashed border-amber-200 bg-amber-50 px-5 py-4 dark:border-amber-900/50 dark:bg-amber-950/30">
                        <p className="text-sm text-amber-900 dark:text-amber-200">
                            Create an active bot before adding knowledge sources.
                        </p>
                        <Link
                            href={route('bots.create')}
                            className="mt-3 inline-flex text-sm font-semibold text-amber-800 hover:text-amber-700 dark:text-amber-300"
                        >
                            Create bot →
                        </Link>
                    </section>
                )}

                <SourcesList
                    sources={sourceItems}
                    pagination={sources}
                    filters={filters}
                    canAdd={canAddSources}
                    onAddSource={() => setShowAddModal(true)}
                />

                <AddSourceModal
                    show={showAddModal}
                    onClose={() => setShowAddModal(false)}
                    bots={bots}
                />
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

function AddSourceModal({ show, onClose, bots }) {
    const { data, setData, post, processing, errors, reset, clearErrors } = useForm({
        bot_id: bots[0]?.id || '',
        type: 'text',
        title: '',
        raw_text: '',
        question: '',
        answer: '',
        file: null,
    });

    const selectedType = SOURCE_TYPES.find((t) => t.value === data.type);

    const close = () => {
        onClose();
        clearErrors();
        reset();
    };

    const submit = (e) => {
        e.preventDefault();
        post(route('knowledge-sources.store'), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                reset('title', 'raw_text', 'question', 'answer', 'file');
                onClose();
            },
        });
    };

    if (bots.length === 0) {
        return null;
    }

    return (
        <Modal show={show} onClose={close} maxWidth="3xl">
            <form onSubmit={submit} className="flex min-h-0 flex-1 flex-col">
                <div className="shrink-0 border-b border-gray-100 px-4 py-4 sm:px-6 dark:border-gray-700">
                    <h3 className="text-lg font-semibold text-gray-900 dark:text-white">Add knowledge source</h3>
                    <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Content is queued for chunking and embedding after you submit.
                    </p>
                </div>

                <div className="min-h-0 flex-1 space-y-5 overflow-y-auto px-4 py-5 sm:px-6">
                    <div>
                        <InputLabel htmlFor="add-bot_id" value="Bot" />
                        <select
                            id="add-bot_id"
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
                        <div className="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-2">
                            {SOURCE_TYPES.map((type) => (
                                <button
                                    key={type.value}
                                    type="button"
                                    onClick={() => setData('type', type.value)}
                                    className={`rounded-lg border px-3 py-3 text-left transition ${
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
                        <InputLabel htmlFor="add-title" value="Title" />
                        <TextInput
                            id="add-title"
                            value={data.title}
                            onChange={(e) => setData('title', e.target.value)}
                            className="mt-1 block w-full"
                            placeholder="e.g. Returns policy"
                        />
                        <InputError message={errors.title} className="mt-1" />
                    </div>

                    {data.type === 'text' && (
                        <TextAreaField
                            id="add-raw_text"
                            label="Content"
                            value={data.raw_text}
                            onChange={(value) => setData('raw_text', value)}
                            error={errors.raw_text}
                            rows={6}
                            placeholder="Paste the knowledge text here…"
                        />
                    )}

                    {data.type === 'faq' && (
                        <>
                            <div>
                                <InputLabel htmlFor="add-question" value="Question" />
                                <TextInput
                                    id="add-question"
                                    value={data.question}
                                    onChange={(e) => setData('question', e.target.value)}
                                    className="mt-1 block w-full"
                                    placeholder="How do I reset my password?"
                                />
                                <InputError message={errors.question} className="mt-1" />
                            </div>
                            <TextAreaField
                                id="add-answer"
                                label="Answer"
                                value={data.answer}
                                onChange={(value) => setData('answer', value)}
                                error={errors.answer}
                                rows={4}
                            />
                        </>
                    )}

                    {(data.type === 'pdf' || data.type === 'docx') && (
                        <div>
                            <InputLabel htmlFor="add-file" value="File" />
                            <input
                                id="add-file"
                                type="file"
                                accept={
                                    data.type === 'pdf'
                                        ? '.pdf,application/pdf'
                                        : '.docx,application/vnd.openxmlformats-officedocument.wordprocessingml.document'
                                }
                                onChange={(e) => setData('file', e.target.files[0] || null)}
                                className="mt-1 block w-full text-sm text-gray-600 file:mr-3 file:rounded-md file:border-0 file:bg-gray-100 file:px-3 file:py-2.5 file:text-sm file:font-medium file:text-gray-700 hover:file:bg-gray-200 dark:text-gray-300 dark:file:bg-gray-700 dark:file:text-gray-200"
                            />
                            <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">{selectedType?.description}</p>
                            {data.file && (
                                <p className="mt-1 text-xs text-gray-600 dark:text-gray-300">
                                    Selected: {data.file.name} ({formatFileSize(data.file.size)})
                                </p>
                            )}
                            <InputError message={errors.file} className="mt-1" />
                        </div>
                    )}
                </div>

                <div className="flex shrink-0 flex-col-reverse gap-2 border-t border-gray-100 bg-gray-50 px-4 py-4 sm:flex-row sm:justify-end sm:gap-3 sm:px-6 dark:border-gray-700 dark:bg-gray-900/50">
                    <SecondaryButton
                        type="button"
                        onClick={close}
                        disabled={processing}
                        className="w-full justify-center !normal-case !tracking-normal sm:w-auto"
                    >
                        Cancel
                    </SecondaryButton>
                    <PrimaryButton disabled={processing} className="w-full justify-center sm:w-auto">
                        {processing ? 'Queuing…' : 'Add & index'}
                    </PrimaryButton>
                </div>
            </form>
        </Modal>
    );
}

function SourcesList({ sources, pagination, filters, canAdd, onAddSource }) {
    const hasSearch = Boolean(filters?.search?.trim());

    return (
        <section className="space-y-4">
            <div className="flex flex-col gap-4">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h3 className="text-base font-semibold text-gray-900 dark:text-white">Sources</h3>
                        <p className="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                            {pagination.total} {pagination.total === 1 ? 'source' : 'sources'}
                            {hasSearch ? ' matching your search' : ''}
                        </p>
                    </div>
                    {canAdd && (
                        <PrimaryButton
                            type="button"
                            onClick={onAddSource}
                            className="w-full shrink-0 justify-center sm:w-auto"
                        >
                            + Add source
                        </PrimaryButton>
                    )}
                </div>
                <SourcesSearch filters={filters} />
            </div>

            {sources.length === 0 ? (
                <div className="flex min-h-[280px] flex-col items-center justify-center rounded-lg border border-dashed border-gray-200 bg-white px-6 py-12 text-center dark:border-gray-700 dark:bg-gray-800">
                    <h3 className="text-lg font-semibold text-gray-900 dark:text-white">
                        {hasSearch ? 'No matching sources' : 'No knowledge sources yet'}
                    </h3>
                    <p className="mx-auto mt-2 max-w-md text-sm text-gray-500 dark:text-gray-400">
                        {hasSearch
                            ? 'Try a different search term, or clear the search to see all sources.'
                            : 'Add text, FAQs, or documents. They will appear here once queued for indexing.'}
                    </p>
                    {hasSearch ? (
                        <ClearSearchButton className="mt-4" />
                    ) : (
                        canAdd && (
                            <PrimaryButton type="button" onClick={onAddSource} className="mt-4">
                                Add your first source
                            </PrimaryButton>
                        )
                    )}
                </div>
            ) : (
                <>

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

            <Pagination links={pagination.links} meta={pagination} />
                </>
            )}
        </section>
    );
}

function SourcesSearch({ filters }) {
    const [search, setSearch] = useState(filters?.search ?? '');

    useEffect(() => {
        setSearch(filters?.search ?? '');
    }, [filters?.search]);

    useEffect(() => {
        const normalized = search.trim();
        const current = (filters?.search ?? '').trim();

        if (normalized === current) {
            return undefined;
        }

        const timeout = window.setTimeout(() => {
            router.get(
                route('knowledge-sources.index'),
                { search: normalized || undefined, page: 1 },
                {
                    preserveState: true,
                    preserveScroll: true,
                    replace: true,
                    only: ['sources', 'filters', 'hasActiveSources'],
                },
            );
        }, 300);

        return () => window.clearTimeout(timeout);
    }, [search, filters?.search]);

    return (
        <div className="w-full sm:max-w-md">
            <InputLabel htmlFor="source-search" value="Search" className="sr-only" />
            <div className="relative">
                <TextInput
                    id="source-search"
                    type="search"
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                    placeholder="Search by title, bot, type…"
                    className="block w-full pr-20"
                />
                {search && (
                    <button
                        type="button"
                        onClick={() => setSearch('')}
                        className="absolute inset-y-0 right-2 my-auto rounded px-2 py-1 text-xs font-medium text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                    >
                        Clear
                    </button>
                )}
            </div>
        </div>
    );
}

function ClearSearchButton({ className = '' }) {
    return (
        <button
            type="button"
            onClick={() =>
                router.get(route('knowledge-sources.index'), {}, {
                    preserveState: true,
                    preserveScroll: true,
                    replace: true,
                    only: ['sources', 'filters', 'hasActiveSources'],
                })
            }
            className={`text-sm font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 ${className}`}
        >
            Clear search
        </button>
    );
}

function SourceRow({ source, layout }) {
    const [busy, setBusy] = useState(false);
    const [editing, setEditing] = useState(false);
    const status = normalizeStatus(source.status);
    const inProgress = isInProgress(source.status);

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

    const cancel = () => {
        if (!window.confirm(`Cancel indexing for "${source.title}"?`)) {
            return;
        }

        runAction((options) => router.post(route('knowledge-sources.cancel', source.id), {}, options));
    };

    const destroy = () => {
        if (!window.confirm(`Delete "${source.title}"? This cannot be undone.`)) {
            return;
        }

        runAction((options) => router.delete(route('knowledge-sources.destroy', source.id), options));
    };

    const statusCell = (
        <div className="space-y-2">
            <StatusBadge status={source.status} />
            {inProgress && <ProcessingProgress status={status} />}
        </div>
    );

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
                {inProgress && <ProcessingProgress status={status} className="mt-3" />}
                <div className="mt-3 flex flex-wrap items-center gap-2">
                    <TypeBadge type={source.type} />
                    <span className="text-xs text-gray-500 dark:text-gray-400">{source.chunks_count} chunks</span>
                </div>
                {source.error_message && (
                    <p className="mt-2 text-xs text-red-600 dark:text-red-400 line-clamp-2">{source.error_message}</p>
                )}
                <SourceActions
                    source={source}
                    busy={busy}
                    editing={editing}
                    onToggleEdit={() => setEditing((v) => !v)}
                    onReprocess={reprocess}
                    onCancel={cancel}
                    onDelete={destroy}
                    className="mt-4"
                />
                {editing && canEdit(source.status) && (
                    <EditSourceForm source={source} onClose={() => setEditing(false)} className="mt-4 border-t border-gray-100 pt-4 dark:border-gray-700" />
                )}
            </article>
        );
    }

    return (
        <>
            <tr className="hover:bg-gray-50/80 dark:hover:bg-gray-900/40">
                <td className="px-5 py-4">
                    <Link
                        href={route('knowledge-sources.show', source.id)}
                        className="font-medium text-gray-900 hover:text-indigo-600 dark:text-white dark:hover:text-indigo-400"
                    >
                        {source.title}
                    </Link>
                    {source.error_message && (
                        <p className="mt-1 max-w-xs truncate text-xs text-red-600 dark:text-red-400" title={source.error_message}>
                            {source.error_message}
                        </p>
                    )}
                </td>
                <td className="px-5 py-4 text-gray-700 dark:text-gray-300">{source.bot?.name}</td>
                <td className="px-5 py-4">
                    <TypeBadge type={source.type} />
                </td>
                <td className="px-5 py-4">{statusCell}</td>
                <td className="px-5 py-4 text-gray-700 dark:text-gray-300">{source.chunks_count}</td>
                <td className="px-5 py-4 text-right">
                    <SourceActions
                        source={source}
                        busy={busy}
                        editing={editing}
                        onToggleEdit={() => setEditing((v) => !v)}
                        onReprocess={reprocess}
                        onCancel={cancel}
                        onDelete={destroy}
                    />
                </td>
            </tr>
            {editing && canEdit(source.status) && (source.type === 'text' || source.type === 'faq') && (
                <tr>
                    <td colSpan={6} className="bg-gray-50/80 px-5 py-4 dark:bg-gray-900/40">
                        <EditSourceForm source={source} onClose={() => setEditing(false)} />
                    </td>
                </tr>
            )}
        </>
    );
}

function SourceActions({ source, busy, editing, onToggleEdit, onReprocess, onCancel, onDelete, className = '' }) {
    const showReprocess = canReprocess(source.status);
    const showEdit = canEdit(source.status) && (source.type === 'text' || source.type === 'faq');
    const showCancel = canCancel(source.status);

    return (
        <div className={`flex flex-wrap justify-end gap-2 ${className}`}>
            <Link
                href={route('knowledge-sources.show', source.id)}
                className="text-xs font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400"
            >
                View
            </Link>
            {showEdit && (
                <button
                    type="button"
                    disabled={busy}
                    onClick={onToggleEdit}
                    className="text-xs font-medium text-indigo-600 hover:text-indigo-500 disabled:opacity-50 dark:text-indigo-400"
                >
                    {editing ? 'Close' : 'Edit'}
                </button>
            )}
            {showReprocess && (
                <button
                    type="button"
                    disabled={busy}
                    onClick={onReprocess}
                    className="text-xs font-medium text-indigo-600 hover:text-indigo-500 disabled:opacity-50 dark:text-indigo-400"
                >
                    {busy ? 'Working…' : 'Reindex'}
                </button>
            )}
            {showCancel && (
                <button
                    type="button"
                    disabled={busy}
                    onClick={onCancel}
                    className="text-xs font-medium text-amber-700 hover:text-amber-600 disabled:opacity-50 dark:text-amber-300"
                >
                    Cancel
                </button>
            )}
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

function EditSourceForm({ source, onClose, className = '' }) {
    const faq = source.type === 'faq' ? parseFaqRawText(source.raw_text) : null;
    const { data, setData, put, processing, errors, recentlySuccessful } = useForm({
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
        <form onSubmit={submit} className={`space-y-3 ${className}`}>
            <p className="text-xs text-gray-500 dark:text-gray-400">Saving will requeue this source for indexing.</p>
            <div>
                <InputLabel htmlFor={`edit-title-${source.id}`} value="Title" />
                <TextInput
                    id={`edit-title-${source.id}`}
                    value={data.title}
                    onChange={(e) => setData('title', e.target.value)}
                    className="mt-1 block w-full"
                />
                <InputError message={errors.title} className="mt-1" />
            </div>
            {source.type === 'text' && (
                <TextAreaField
                    id={`edit-text-${source.id}`}
                    label="Content"
                    value={data.raw_text}
                    onChange={(value) => setData('raw_text', value)}
                    error={errors.raw_text}
                    rows={5}
                />
            )}
            {source.type === 'faq' && (
                <>
                    <div>
                        <InputLabel htmlFor={`edit-q-${source.id}`} value="Question" />
                        <TextInput
                            id={`edit-q-${source.id}`}
                            value={data.question}
                            onChange={(e) => setData('question', e.target.value)}
                            className="mt-1 block w-full"
                        />
                        <InputError message={errors.question} className="mt-1" />
                    </div>
                    <TextAreaField
                        id={`edit-a-${source.id}`}
                        label="Answer"
                        value={data.answer}
                        onChange={(value) => setData('answer', value)}
                        error={errors.answer}
                        rows={4}
                    />
                </>
            )}
            <div className="flex flex-wrap items-center gap-2">
                <PrimaryButton disabled={processing} className="!text-xs">
                    {processing ? 'Saving…' : 'Save & reindex'}
                </PrimaryButton>
                <button type="button" onClick={onClose} className="text-xs text-gray-500 hover:text-gray-700 dark:text-gray-400">
                    Cancel
                </button>
                {recentlySuccessful && <span className="text-xs text-emerald-600 dark:text-emerald-400">Saved.</span>}
            </div>
        </form>
    );
}

function StatusBadge({ status }) {
    const key = normalizeStatus(status);

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

function ProcessingProgress({ status, className = '' }) {
    const isIndexing = status === 'processing';

    return (
        <div className={className}>
            <div className="h-1.5 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                <div
                    className={`h-full rounded-full bg-indigo-500 ${isIndexing ? 'w-2/3 animate-pulse' : 'w-1/3'}`}
                />
            </div>
            <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">{statusDescription(status)}</p>
        </div>
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

function parseFaqRawText(rawText) {
    const match = String(rawText || '').match(/^Question:\s*(.*?)\nAnswer:\s*(.*)$/s);

    if (match) {
        return { question: match[1], answer: match[2] };
    }

    return { question: '', answer: rawText || '' };
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
