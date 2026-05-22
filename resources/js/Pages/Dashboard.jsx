import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';

export default function Dashboard({ stats }) {
    const cards = [
        ['Tenants', stats.tenants],
        ['Bots', stats.bots],
        ['Knowledge sources', stats.knowledge_sources],
        ['Conversations', stats.conversations],
    ].filter(([, value]) => value !== null);

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-gray-800 dark:text-gray-200">Dashboard</h2>}>
            <Head title="Dashboard" />
            <div className="mx-auto max-w-7xl p-6">
                <div className="grid gap-4 md:grid-cols-4">
                    {cards.map(([label, value]) => (
                        <div key={label} className="rounded-lg bg-white p-5 shadow dark:bg-gray-800">
                            <div className="text-sm text-gray-500 dark:text-gray-400">{label}</div>
                            <div className="mt-2 text-3xl font-semibold text-gray-900 dark:text-white">{value}</div>
                        </div>
                    ))}
                </div>
                <div className="mt-6 rounded-lg bg-white p-5 shadow dark:bg-gray-800">
                    <h3 className="font-semibold text-gray-900 dark:text-white">Recent AI usage</h3>
                    <div className="mt-4 overflow-x-auto text-sm">
                        <table className="w-full text-left">
                            <thead className="text-gray-500 dark:text-gray-400"><tr><th>Type</th><th>Model</th><th>Tokens</th><th>Error</th></tr></thead>
                            <tbody className="divide-y dark:divide-gray-700">
                                {stats.usage_logs?.map((log) => (
                                    <tr key={log.id}><td className="py-2">{log.type}</td><td>{log.model}</td><td>{log.total_tokens || '-'}</td><td>{log.error_message || '-'}</td></tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
