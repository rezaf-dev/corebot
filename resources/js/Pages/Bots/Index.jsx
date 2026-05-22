import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';

export default function Index({ bots }) {
    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-gray-800 dark:text-gray-200">Bots</h2>}>
            <Head title="Bots" />
            <div className="mx-auto max-w-7xl p-6">
                <Link href={route('bots.create')} className="rounded bg-gray-900 px-4 py-2 text-sm text-white">New bot</Link>
                <div className="mt-4 rounded-lg bg-white p-5 shadow dark:bg-gray-800">
                    <table className="w-full text-left text-sm">
                        <thead className="text-gray-500"><tr><th>Name</th><th>Status</th><th>Public key</th><th>Knowledge</th><th>Conversations</th><th></th></tr></thead>
                        <tbody className="divide-y dark:divide-gray-700">{bots.map((bot) => <tr key={bot.id}><td className="py-2">{bot.name}</td><td>{bot.status}</td><td className="font-mono text-xs">{bot.public_key}</td><td>{bot.knowledge_sources_count}</td><td>{bot.conversations_count}</td><td><Link className="text-indigo-600" href={route('bots.edit', bot.id)}>Edit</Link><button onClick={() => router.delete(route('bots.destroy', bot.id))} className="ml-3 text-red-600">Delete</button></td></tr>)}</tbody>
                    </table>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
