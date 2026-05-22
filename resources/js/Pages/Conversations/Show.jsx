import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';

export default function Show({ conversation }) {
    const [expandedLogs, setExpandedLogs] = useState({});

    const toggleLog = (id) => {
        setExpandedLogs((prev) => ({ ...prev, [id]: !prev[id] }));
    };

    const visitorMessages = conversation.messages.filter((m) => m.role === 'user').length;
    const assistantMessages = conversation.messages.filter((m) => m.role === 'assistant').length;

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h2 className="text-xl font-semibold text-gray-800 dark:text-gray-200">
                            Conversation #{conversation.id}
                        </h2>
                        <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            {conversation.bot?.name} · {formatDateTime(conversation.created_at)}
                        </p>
                    </div>
                    <Link
                        href={route('conversations.index')}
                        className="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold normal-case tracking-normal text-gray-700 shadow-sm transition hover:bg-gray-50 dark:border-gray-500 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                    >
                        Back to conversations
                    </Link>
                </div>
            }
        >
            <Head title={`Conversation ${conversation.id}`} />

            <div className="mx-auto grid max-w-7xl gap-6 p-6 lg:grid-cols-[1fr_360px]">
                <section className="space-y-4">
                    <div className="flex items-center justify-between gap-4">
                        <h3 className="text-base font-semibold text-gray-900 dark:text-white">
                            Messages ({conversation.messages.length})
                        </h3>
                        <span className="text-xs text-gray-500 dark:text-gray-400">
                            {visitorMessages} visitor · {assistantMessages} assistant
                        </span>
                    </div>

                    {conversation.messages.length === 0 ? (
                        <div className="rounded-lg border border-dashed border-gray-200 bg-white px-6 py-10 text-center dark:border-gray-700 dark:bg-gray-800">
                            <p className="text-sm text-gray-500 dark:text-gray-400">No messages in this conversation yet.</p>
                        </div>
                    ) : (
                        <div className="space-y-3">
                            {conversation.messages.map((message) => (
                                <MessageBubble key={message.id} message={message} />
                            ))}
                        </div>
                    )}
                </section>

                <aside className="space-y-4">
                    <section className="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                        <div className="flex flex-wrap items-center gap-2">
                            <StatusBadge status={conversation.status} />
                        </div>

                        <dl className="mt-5 space-y-4">
                            <MetaItem label="Bot" value={conversation.bot?.name || '—'} />
                            <MetaItem label="Visitor name" value={conversation.visitor_name || '—'} />
                            <MetaItem label="Email" value={conversation.visitor_email || '—'} />
                            <MetaItem label="Phone" value={conversation.visitor_phone || '—'} />
                            <MetaItem
                                label="Visitor ID"
                                value={conversation.visitor_id ? (
                                    <span className="font-mono text-xs">{conversation.visitor_id}</span>
                                ) : (
                                    '—'
                                )}
                            />
                            <MetaItem label="Started" value={formatDateTime(conversation.created_at)} />
                            <MetaItem label="Last updated" value={formatDateTime(conversation.updated_at)} />
                        </dl>

                        {conversation.source_url && (
                            <div className="mt-5 border-t border-gray-100 pt-5 dark:border-gray-700">
                                <dt className="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Source URL</dt>
                                <a
                                    href={conversation.source_url}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="mt-1 block break-all text-sm text-indigo-600 hover:text-indigo-500 dark:text-indigo-400"
                                >
                                    {conversation.source_url}
                                </a>
                            </div>
                        )}
                    </section>

                    <section className="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                        <h3 className="text-base font-semibold text-gray-900 dark:text-white">
                            Retrieval logs ({conversation.retrieval_logs.length})
                        </h3>
                        <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Knowledge chunks used when generating assistant replies.
                        </p>

                        {conversation.retrieval_logs.length === 0 ? (
                            <p className="mt-4 text-sm text-gray-500 dark:text-gray-400">No retrieval activity recorded.</p>
                        ) : (
                            <div className="mt-4 space-y-3">
                                {conversation.retrieval_logs.map((log, index) => (
                                    <RetrievalLogCard
                                        key={log.id}
                                        log={log}
                                        index={index}
                                        expanded={expandedLogs[log.id] ?? false}
                                        onToggle={() => toggleLog(log.id)}
                                    />
                                ))}
                            </div>
                        )}
                    </section>
                </aside>
            </div>
        </AuthenticatedLayout>
    );
}

function MessageBubble({ message }) {
    const isUser = message.role === 'user';
    const isAssistant = message.role === 'assistant';
    const isSystem = message.role === 'system';

    return (
        <div className={`flex ${isUser ? 'justify-end' : 'justify-start'}`}>
            <div
                className={`max-w-[85%] rounded-2xl px-4 py-3 ${
                    isUser
                        ? 'bg-indigo-600 text-white'
                        : isAssistant
                          ? 'border border-gray-200 bg-white text-gray-900 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100'
                          : 'border border-dashed border-gray-300 bg-gray-50 text-gray-600 dark:border-gray-600 dark:bg-gray-900/50 dark:text-gray-400'
                }`}
            >
                <div className="flex items-center justify-between gap-4">
                    <span
                        className={`text-xs font-semibold uppercase tracking-wide ${
                            isUser ? 'text-indigo-200' : 'text-gray-500 dark:text-gray-400'
                        }`}
                    >
                        {message.role}
                    </span>
                    {message.created_at && (
                        <span className={`text-xs ${isUser ? 'text-indigo-200' : 'text-gray-400'}`}>
                            {formatTime(message.created_at)}
                        </span>
                    )}
                </div>
                <p className={`mt-2 whitespace-pre-wrap text-sm ${isUser ? 'text-white' : ''}`}>{message.content}</p>
                {isSystem && (
                    <p className="mt-2 text-xs opacity-75">System instructions (not shown to visitors)</p>
                )}
            </div>
        </div>
    );
}

function RetrievalLogCard({ log, index, expanded, onToggle }) {
    const chunkCount = (log.selected_chunk_ids || []).length;

    return (
        <article className="rounded-lg border border-gray-100 bg-gray-50 dark:border-gray-700 dark:bg-gray-900/50">
            <button
                type="button"
                onClick={onToggle}
                className="flex w-full items-start justify-between gap-3 px-4 py-3 text-left"
            >
                <div className="min-w-0">
                    <div className="text-sm font-semibold text-gray-900 dark:text-white">Retrieval #{index + 1}</div>
                    <p className="mt-1 truncate text-xs text-gray-600 dark:text-gray-400">
                        Query: {log.query || '—'}
                    </p>
                    <p className="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                        {chunkCount} chunk{chunkCount === 1 ? '' : 's'} selected
                    </p>
                </div>
                <span className="shrink-0 text-xs font-medium text-indigo-600 dark:text-indigo-400">
                    {expanded ? 'Hide' : 'Show'}
                </span>
            </button>

            {expanded && (
                <div className="border-t border-gray-200 px-4 py-3 dark:border-gray-700">
                    {(log.selected_chunk_ids || []).length > 0 && (
                        <p className="text-xs text-gray-600 dark:text-gray-400">
                            Chunk IDs: {(log.selected_chunk_ids || []).join(', ')}
                        </p>
                    )}
                    {log.context_text ? (
                        <pre className="mt-3 max-h-64 overflow-auto whitespace-pre-wrap rounded-md bg-white p-3 text-xs text-gray-700 dark:bg-gray-950 dark:text-gray-300">
                            {log.context_text}
                        </pre>
                    ) : (
                        <p className="mt-2 text-xs text-gray-500 dark:text-gray-400">No context text stored.</p>
                    )}
                </div>
            )}
        </article>
    );
}

function MetaItem({ label, value }) {
    return (
        <div>
            <dt className="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{label}</dt>
            <dd className="mt-1 text-sm font-medium text-gray-900 dark:text-white">{value}</dd>
        </div>
    );
}

function StatusBadge({ status }) {
    const styles = {
        open: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300',
        escalated: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
    };

    return (
        <span className={`inline-flex rounded-full px-2.5 py-1 text-xs font-semibold capitalize ${styles[status] || 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300'}`}>
            {status}
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

function formatTime(value) {
    try {
        return new Intl.DateTimeFormat(undefined, {
            timeStyle: 'short',
        }).format(new Date(value));
    } catch {
        return '';
    }
}
