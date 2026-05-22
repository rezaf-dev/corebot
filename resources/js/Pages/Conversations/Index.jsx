import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

export default function Index({ conversations }) {
    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-gray-800 dark:text-gray-200">Conversations</h2>}>
            <Head title="Conversations" />
            <div className="mx-auto max-w-7xl p-6">
                <div className="rounded-lg bg-white p-5 shadow dark:bg-gray-800">
                    <table className="w-full text-left text-sm">
                        <thead className="text-gray-500 dark:text-gray-400"><tr><th>ID</th><th>Bot</th><th>Status</th><th>Visitor</th><th>Messages</th><th>Source URL</th></tr></thead>
                        <tbody className="divide-y dark:divide-gray-700">{conversations.map((c) => <tr key={c.id}><td className="py-2"><Link className="text-indigo-600 dark:text-indigo-400" href={route('conversations.show', c.id)}>{c.id}</Link></td><td>{c.bot?.name}</td><td>{c.status}</td><td>{c.visitor_email || c.visitor_id || '-'}</td><td>{c.messages_count}</td><td className="max-w-xs truncate">{c.source_url}</td></tr>)}</tbody>
                    </table>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
