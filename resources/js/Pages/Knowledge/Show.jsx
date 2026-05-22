import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';

export default function Show({ source, chunks }) {
    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-gray-800 dark:text-gray-200">{source.title}</h2>}>
            <Head title={source.title} />
            <div className="mx-auto max-w-5xl p-6">
                <div className="rounded-lg bg-white p-5 shadow dark:bg-gray-800">
                    <div className="text-sm text-gray-600 dark:text-gray-400">Bot: {source.bot?.name} | Type: {source.type} | Status: {source.status} | Chunks: {source.chunks_count}</div>
                    {source.error_message && <div className="mt-3 rounded border border-red-200 bg-red-50 p-3 text-sm text-red-700 dark:border-red-900 dark:bg-red-950 dark:text-red-200">{source.error_message}</div>}
                    <button onClick={() => router.post(route('knowledge-sources.reprocess', source.id))} className="mt-4 rounded border px-4 py-2 text-sm dark:border-gray-700 dark:text-gray-200">Reprocess</button>
                </div>
                <div className="mt-6 space-y-4">{chunks.map((chunk) => <div key={chunk.id} className="rounded-lg bg-white p-5 text-sm shadow dark:bg-gray-800"><div className="mb-2 font-semibold text-gray-900 dark:text-gray-100">Chunk {chunk.chunk_index}</div><p className="whitespace-pre-wrap text-gray-700 dark:text-gray-300">{chunk.content}</p></div>)}</div>
            </div>
        </AuthenticatedLayout>
    );
}
