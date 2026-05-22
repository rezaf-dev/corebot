import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

export default function Index({ bots }) {
    const totalKnowledge = bots.reduce((sum, bot) => sum + bot.knowledge_sources_count, 0);
    const totalConversations = bots.reduce((sum, bot) => sum + bot.conversations_count, 0);
    const activeBots = bots.filter((bot) => bot.status === 'active').length;

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h2 className="text-xl font-semibold text-gray-800 dark:text-gray-200">Bots</h2>
                        <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Manage chatbots, embed keys, and knowledge scoped to each bot.
                        </p>
                    </div>
                    <Link
                        href={route('bots.create')}
                        className="inline-flex items-center rounded-md border border-transparent bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition duration-150 ease-in-out hover:bg-gray-700 focus:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 active:bg-gray-900 dark:bg-gray-200 dark:text-gray-800 dark:hover:bg-white dark:focus:bg-white dark:focus:ring-offset-gray-800"
                    >
                        New bot
                    </Link>
                </div>
            }
        >
            <Head title="Bots" />

            <div className="mx-auto max-w-7xl space-y-6 p-6">
                <div className="grid gap-4 sm:grid-cols-3">
                    <StatCard label="Total bots" value={bots.length} />
                    <StatCard label="Active bots" value={activeBots} />
                    <StatCard label="Knowledge sources" value={totalKnowledge} hint={`${totalConversations} conversations`} />
                </div>

                {bots.length === 0 ? (
                    <EmptyState />
                ) : (
                    <>
                        <div className="hidden overflow-hidden rounded-lg bg-white shadow dark:bg-gray-800 md:block">
                            <table className="w-full text-left text-sm">
                                <thead className="border-b border-gray-100 bg-gray-50 text-xs uppercase tracking-wide text-gray-500 dark:border-gray-700 dark:bg-gray-900/50 dark:text-gray-400">
                                    <tr>
                                        <th className="px-5 py-3">Bot</th>
                                        <th className="px-5 py-3">Status</th>
                                        <th className="px-5 py-3">Public key</th>
                                        <th className="px-5 py-3">Knowledge</th>
                                        <th className="px-5 py-3">Conversations</th>
                                        <th className="px-5 py-3 text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                                    {bots.map((bot) => (
                                        <BotRow key={bot.id} bot={bot} layout="table" />
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        <div className="grid gap-4 md:hidden">
                            {bots.map((bot) => (
                                <BotRow key={bot.id} bot={bot} layout="card" />
                            ))}
                        </div>
                    </>
                )}

                <p className="text-sm text-gray-500 dark:text-gray-400">
                    Need the embed snippet? Visit{' '}
                    <Link href={route('widget.install')} className="font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">
                        Widget install
                    </Link>
                    .
                </p>
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

function EmptyState() {
    return (
        <div className="rounded-lg border border-dashed border-gray-200 bg-white px-6 py-12 text-center dark:border-gray-700 dark:bg-gray-800">
            <h3 className="text-lg font-semibold text-gray-900 dark:text-white">No bots yet</h3>
            <p className="mx-auto mt-2 max-w-md text-sm text-gray-500 dark:text-gray-400">
                Create your first bot to start adding knowledge, embedding the chat widget, and collecting conversations.
            </p>
            <Link
                href={route('bots.create')}
                className="mt-6 inline-flex items-center rounded-md border border-transparent bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-gray-700 dark:bg-gray-200 dark:text-gray-800 dark:hover:bg-white"
            >
                Create your first bot
            </Link>
        </div>
    );
}

function BotRow({ bot, layout }) {
    const [copied, setCopied] = useState(false);

    const copyKey = async () => {
        try {
            await navigator.clipboard.writeText(bot.public_key);
            setCopied(true);
            window.setTimeout(() => setCopied(false), 2000);
        } catch {
            window.prompt('Copy this public key:', bot.public_key);
        }
    };

    const confirmDelete = () => {
        if (window.confirm(`Delete "${bot.name}"? This cannot be undone.`)) {
            router.delete(route('bots.destroy', bot.id));
        }
    };

    if (layout === 'card') {
        return (
            <article className="rounded-lg bg-white p-5 shadow dark:bg-gray-800">
                <div className="flex items-start justify-between gap-3">
                    <div>
                        <h3 className="font-semibold text-gray-900 dark:text-white">{bot.name}</h3>
                        <div className="mt-2">
                            <StatusBadge status={bot.status} />
                        </div>
                    </div>
                    <BotActions bot={bot} onDelete={confirmDelete} compact />
                </div>
                <dl className="mt-4 grid grid-cols-2 gap-3 text-sm">
                    <Metric label="Knowledge" value={bot.knowledge_sources_count} />
                    <Metric label="Conversations" value={bot.conversations_count} />
                </dl>
                <div className="mt-4">
                    <PublicKey value={bot.public_key} copied={copied} onCopy={copyKey} />
                </div>
            </article>
        );
    }

    return (
        <tr className="hover:bg-gray-50/80 dark:hover:bg-gray-900/40">
            <td className="px-5 py-4">
                <div className="font-medium text-gray-900 dark:text-white">{bot.name}</div>
            </td>
            <td className="px-5 py-4">
                <StatusBadge status={bot.status} />
            </td>
            <td className="px-5 py-4">
                <PublicKey value={bot.public_key} copied={copied} onCopy={copyKey} />
            </td>
            <td className="px-5 py-4 text-gray-700 dark:text-gray-300">{bot.knowledge_sources_count}</td>
            <td className="px-5 py-4 text-gray-700 dark:text-gray-300">{bot.conversations_count}</td>
            <td className="px-5 py-4 text-right">
                <BotActions bot={bot} onDelete={confirmDelete} />
            </td>
        </tr>
    );
}

function Metric({ label, value }) {
    return (
        <div className="rounded-lg border border-gray-100 bg-gray-50 px-3 py-2 dark:border-gray-700 dark:bg-gray-900/50">
            <dt className="text-xs text-gray-500 dark:text-gray-400">{label}</dt>
            <dd className="mt-0.5 font-semibold text-gray-900 dark:text-white">{value}</dd>
        </div>
    );
}

function StatusBadge({ status }) {
    const active = status === 'active';

    return (
        <span
            className={`inline-flex rounded-full px-2.5 py-1 text-xs font-semibold capitalize ${
                active
                    ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300'
                    : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300'
            }`}
        >
            {status}
        </span>
    );
}

function PublicKey({ value, copied, onCopy }) {
    return (
        <div className="flex flex-wrap items-center gap-2">
            <code className="max-w-[220px] truncate rounded bg-gray-100 px-2 py-1 font-mono text-xs text-gray-800 dark:bg-gray-900 dark:text-gray-200 md:max-w-xs">
                {value}
            </code>
            <button
                type="button"
                onClick={onCopy}
                className="text-xs font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400"
            >
                {copied ? 'Copied' : 'Copy'}
            </button>
        </div>
    );
}

const actionLinkClass =
    'inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold normal-case tracking-normal text-gray-700 shadow-sm transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:border-gray-500 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700 dark:focus:ring-offset-gray-800';

function BotActions({ bot, onDelete, compact = false }) {
    return (
        <div className={`flex flex-wrap gap-2 ${compact ? '' : 'justify-end'}`}>
            <Link href={route('bots.edit', bot.id)} className={actionLinkClass}>
                Edit
            </Link>
            <Link href={route('knowledge-sources.index')} className={actionLinkClass}>
                Knowledge
            </Link>
            {!compact && (
                <button
                    type="button"
                    onClick={onDelete}
                    className="inline-flex items-center rounded-md border border-transparent px-4 py-2 text-xs font-semibold uppercase tracking-widest text-red-600 transition hover:text-red-500 focus:outline-none dark:text-red-400"
                >
                    Delete
                </button>
            )}
            {compact && (
                <button type="button" onClick={onDelete} className="text-xs font-medium text-red-600 dark:text-red-400">
                    Delete
                </button>
            )}
        </div>
    );
}
