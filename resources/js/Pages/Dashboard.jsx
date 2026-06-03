import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';

function formatTokens(value) {
    if (value === null || value === undefined) {
        return '-';
    }

    return Number(value).toLocaleString();
}

export default function Dashboard({ stats }) {
    const cards = [
        ['Tenants', stats.tenants],
        ['Bots', stats.bots],
        ['Knowledge sources', stats.knowledge_sources],
        ['Conversations', stats.conversations],
        ['Total tokens', stats.total_tokens],
    ].filter(([, value]) => value !== null);

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-gray-800 dark:text-gray-200">Dashboard</h2>}>
            <Head title="Dashboard" />
            <div className="mx-auto max-w-7xl space-y-6 p-6">
                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-5">
                    {cards.map(([label, value]) => (
                        <div key={label} className="rounded-lg bg-white p-5 shadow dark:bg-gray-800">
                            <div className="text-sm text-gray-500 dark:text-gray-400">{label}</div>
                            <div className="mt-2 text-3xl font-semibold text-gray-900 dark:text-white">
                                {label === 'Total tokens' ? formatTokens(value) : value}
                            </div>
                        </div>
                    ))}
                </div>

                <div className="rounded-lg bg-white p-5 shadow dark:bg-gray-800">
                    <h3 className="font-semibold text-gray-900 dark:text-white">Token usage by bot</h3>
                    <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Cumulative tokens from chat completions and embeddings for each bot.
                    </p>
                    <div className="mt-4 overflow-x-auto text-sm">
                        {stats.bot_token_usage?.length ? (
                            <table className="w-full text-left">
                                <thead className="text-gray-500 dark:text-gray-400">
                                    <tr>
                                        <th className="pb-2">Bot</th>
                                        <th className="pb-2">Input</th>
                                        <th className="pb-2">Output</th>
                                        <th className="pb-2">Total</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y dark:divide-gray-700">
                                    {stats.bot_token_usage.map((bot) => (
                                        <tr key={bot.id}>
                                            <td className="py-2 font-medium text-gray-900 dark:text-white">{bot.name}</td>
                                            <td className="py-2">{formatTokens(bot.input_tokens)}</td>
                                            <td className="py-2">{formatTokens(bot.output_tokens)}</td>
                                            <td className="py-2 font-semibold">{formatTokens(bot.total_tokens)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        ) : (
                            <p className="text-gray-500 dark:text-gray-400">No bots yet. Token usage will appear after AI requests run.</p>
                        )}
                    </div>
                </div>

                <div className="rounded-lg bg-white p-5 shadow dark:bg-gray-800">
                    <h3 className="font-semibold text-gray-900 dark:text-white">Recent AI usage</h3>
                    <div className="mt-4 overflow-x-auto text-sm">
                        <table className="w-full text-left">
                            <thead className="text-gray-500 dark:text-gray-400">
                                <tr>
                                    <th>Bot</th>
                                    <th>Type</th>
                                    <th>Model</th>
                                    <th>Tokens</th>
                                    <th>Error</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y dark:divide-gray-700">
                                {stats.usage_logs?.length ? (
                                    stats.usage_logs.map((log) => (
                                        <tr key={log.id}>
                                            <td className="py-2">{log.bot?.name ?? '-'}</td>
                                            <td className="py-2">{log.type}</td>
                                            <td>{log.model}</td>
                                            <td>{formatTokens(log.total_tokens)}</td>
                                            <td>{log.error_message || '-'}</td>
                                        </tr>
                                    ))
                                ) : (
                                    <tr>
                                        <td colSpan={5} className="py-4 text-gray-500 dark:text-gray-400">
                                            No AI usage recorded yet.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
