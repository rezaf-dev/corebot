import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { useMemo, useState } from 'react';

export default function Index({ conversations }) {
    const [filter, setFilter] = useState('all');

    const stats = useMemo(() => {
        const open = conversations.filter((c) => c.status === 'open').length;
        const escalated = conversations.filter((c) => c.status === 'escalated').length;
        const messages = conversations.reduce((sum, c) => sum + (c.messages_count || 0), 0);
        const withContact = conversations.filter((c) => hasContact(c)).length;
        const needsFollowUp = conversations.filter((c) => c.status === 'escalated' && !hasContact(c)).length;

        return { total: conversations.length, open, escalated, messages, withContact, needsFollowUp };
    }, [conversations]);

    const filtered = useMemo(() => {
        if (filter === 'escalated') {
            return conversations.filter((c) => c.status === 'escalated');
        }

        if (filter === 'leads') {
            return conversations.filter((c) => hasContact(c));
        }

        if (filter === 'needs_follow_up') {
            return conversations.filter((c) => c.status === 'escalated' && !hasContact(c));
        }

        return conversations;
    }, [conversations, filter]);

    return (
        <AuthenticatedLayout
            header={
                <div>
                    <h2 className="text-xl font-semibold text-gray-800 dark:text-gray-200">Conversations</h2>
                    <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Review widget chats, visitor details, and message history across your bots.
                    </p>
                </div>
            }
        >
            <Head title="Conversations" />

            <div className="mx-auto max-w-7xl space-y-6 p-6">
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard label="Total conversations" value={stats.total} />
                    <StatCard label="Open" value={stats.open} />
                    <StatCard label="Escalated" value={stats.escalated} />
                    <StatCard label="Total messages" value={stats.messages} hint={`${stats.withContact} leads`} />
                </div>

                <div className="flex flex-wrap gap-2">
                    <FilterButton active={filter === 'all'} onClick={() => setFilter('all')}>
                        All ({stats.total})
                    </FilterButton>
                    <FilterButton active={filter === 'escalated'} onClick={() => setFilter('escalated')}>
                        Escalated ({stats.escalated})
                    </FilterButton>
                    <FilterButton active={filter === 'leads'} onClick={() => setFilter('leads')}>
                        Has contact ({stats.withContact})
                    </FilterButton>
                    <FilterButton active={filter === 'needs_follow_up'} onClick={() => setFilter('needs_follow_up')}>
                        Needs follow-up ({stats.needsFollowUp})
                    </FilterButton>
                </div>

                {conversations.length === 0 ? (
                    <EmptyState />
                ) : filtered.length === 0 ? (
                    <div className="rounded-lg border border-dashed border-gray-200 bg-white px-6 py-10 text-center dark:border-gray-700 dark:bg-gray-800">
                        <p className="text-sm text-gray-500 dark:text-gray-400">No conversations match this filter.</p>
                    </div>
                ) : (
                    <>
                        <div className="hidden overflow-hidden rounded-lg bg-white shadow dark:bg-gray-800 lg:block">
                            <table className="w-full text-left text-sm">
                                <thead className="border-b border-gray-100 bg-gray-50 text-xs uppercase tracking-wide text-gray-500 dark:border-gray-700 dark:bg-gray-900/50 dark:text-gray-400">
                                    <tr>
                                        <th className="px-5 py-3">Conversation</th>
                                        <th className="px-5 py-3">Bot</th>
                                        <th className="px-5 py-3">Status</th>
                                        <th className="px-5 py-3">Visitor</th>
                                        <th className="px-5 py-3">Country</th>
                                        <th className="px-5 py-3">Messages</th>
                                        <th className="px-5 py-3">Started</th>
                                        <th className="px-5 py-3">Source</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                                    {filtered.map((conversation) => (
                                        <ConversationRow key={conversation.id} conversation={conversation} layout="table" />
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        <div className="grid gap-4 lg:hidden">
                            {filtered.map((conversation) => (
                                <ConversationRow key={conversation.id} conversation={conversation} layout="card" />
                            ))}
                        </div>
                    </>
                )}
            </div>
        </AuthenticatedLayout>
    );
}

function hasContact(conversation) {
    return Boolean(conversation.visitor_email || conversation.visitor_phone || conversation.visitor_name);
}

function FilterButton({ active, onClick, children }) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={
                'rounded-full px-3 py-1.5 text-xs font-semibold transition ' +
                (active
                    ? 'bg-indigo-600 text-white'
                    : 'bg-white text-gray-700 shadow-sm hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700')
            }
        >
            {children}
        </button>
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

function EmptyState() {
    return (
        <div className="rounded-lg border border-dashed border-gray-200 bg-white px-6 py-12 text-center dark:border-gray-700 dark:bg-gray-800">
            <h3 className="text-lg font-semibold text-gray-900 dark:text-white">No conversations yet</h3>
            <p className="mx-auto mt-2 max-w-md text-sm text-gray-500 dark:text-gray-400">
                Conversations appear when visitors use the chat widget on your site. Embed a bot from the Widget install page to get started.
            </p>
            <Link
                href={route('widget.install')}
                className="mt-6 inline-flex items-center rounded-md border border-transparent bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-gray-700 dark:bg-gray-200 dark:text-gray-800 dark:hover:bg-white"
            >
                Widget install
            </Link>
        </div>
    );
}

function ConversationRow({ conversation, layout }) {
    const visitor = formatVisitor(conversation);

    if (layout === 'card') {
        return (
            <article className="rounded-lg bg-white p-5 shadow dark:bg-gray-800">
                <div className="flex items-start justify-between gap-3">
                    <div>
                        <Link
                            href={route('conversations.show', conversation.id)}
                            className="font-semibold text-gray-900 hover:text-indigo-600 dark:text-white dark:hover:text-indigo-400"
                        >
                            Conversation #{conversation.id}
                        </Link>
                        <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">{conversation.bot?.name}</p>
                    </div>
                    <StatusBadge status={conversation.status} />
                </div>
                <dl className="mt-4 grid grid-cols-2 gap-3 text-sm">
                    <div>
                        <dt className="text-xs text-gray-500 dark:text-gray-400">Visitor</dt>
                        <dd className="mt-0.5 font-medium text-gray-900 dark:text-white">{visitor}</dd>
                    </div>
                    <div>
                        <dt className="text-xs text-gray-500 dark:text-gray-400">Messages</dt>
                        <dd className="mt-0.5 font-medium text-gray-900 dark:text-white">{conversation.messages_count}</dd>
                    </div>
                </dl>
                <p className="mt-3 text-xs text-gray-500 dark:text-gray-400">{formatDateTime(conversation.created_at)}</p>
                {conversation.source_url && (
                    <a
                        href={conversation.source_url}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="mt-2 block truncate text-xs text-indigo-600 hover:text-indigo-500 dark:text-indigo-400"
                    >
                        {conversation.source_url}
                    </a>
                )}
            </article>
        );
    }

    return (
        <tr className="hover:bg-gray-50/80 dark:hover:bg-gray-900/40">
            <td className="px-5 py-4">
                <Link
                    href={route('conversations.show', conversation.id)}
                    className="font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400"
                >
                    #{conversation.id}
                </Link>
            </td>
            <td className="px-5 py-4 text-gray-700 dark:text-gray-300">{conversation.bot?.name || '—'}</td>
            <td className="px-5 py-4">
                <StatusBadge status={conversation.status} />
            </td>
            <td className="px-5 py-4 text-gray-700 dark:text-gray-300">{visitor}</td>
            <td className="px-5 py-4 text-gray-700 dark:text-gray-300">
                {conversation.country_name || conversation.country_code || '—'}
            </td>
            <td className="px-5 py-4 text-gray-700 dark:text-gray-300">{conversation.messages_count}</td>
            <td className="px-5 py-4 text-gray-500 dark:text-gray-400">{formatDateTime(conversation.created_at)}</td>
            <td className="max-w-[200px] px-5 py-4">
                {conversation.source_url ? (
                    <a
                        href={conversation.source_url}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="block truncate text-indigo-600 hover:text-indigo-500 dark:text-indigo-400"
                        title={conversation.source_url}
                    >
                        {truncateUrl(conversation.source_url)}
                    </a>
                ) : (
                    <span className="text-gray-400">—</span>
                )}
            </td>
        </tr>
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

function formatVisitor(conversation) {
    if (conversation.visitor_name) {
        return conversation.visitor_name;
    }

    if (conversation.visitor_email) {
        return conversation.visitor_email;
    }

    if (conversation.visitor_phone) {
        return conversation.visitor_phone;
    }

    if (conversation.visitor_id) {
        return `Visitor ${conversation.visitor_id.slice(0, 8)}…`;
    }

    return 'Anonymous';
}

function truncateUrl(url) {
    try {
        const parsed = new URL(url);
        return parsed.hostname + parsed.pathname;
    } catch {
        return url.length > 40 ? `${url.slice(0, 40)}…` : url;
    }
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
