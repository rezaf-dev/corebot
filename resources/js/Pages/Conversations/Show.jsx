import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';

export default function Show({ conversation }) {
    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-gray-800 dark:text-gray-200">Conversation #{conversation.id}</h2>}>
            <Head title={`Conversation ${conversation.id}`} />
            <div className="mx-auto grid max-w-7xl gap-6 p-6 lg:grid-cols-[1fr_420px]">
                <div className="space-y-3">{conversation.messages.map((m) => <div key={m.id} className="rounded-lg bg-white p-4 shadow dark:bg-gray-800"><div className="text-xs font-semibold uppercase text-gray-500">{m.role}</div><p className="mt-2 whitespace-pre-wrap text-sm text-gray-800 dark:text-gray-200">{m.content}</p></div>)}</div>
                <div className="space-y-4">
                    <div className="rounded-lg bg-white p-5 text-sm shadow dark:bg-gray-800"><div>Bot: {conversation.bot?.name}</div><div>Status: {conversation.status}</div><div>Email: {conversation.visitor_email || '-'}</div><div>Phone: {conversation.visitor_phone || '-'}</div><div className="break-all">URL: {conversation.source_url || '-'}</div></div>
                    {conversation.retrieval_logs.map((log) => <div key={log.id} className="rounded-lg bg-white p-5 text-sm shadow dark:bg-gray-800"><div className="font-semibold text-gray-900 dark:text-gray-100">Retrieval</div><div>Query: {log.query}</div><div>Chunks: {(log.selected_chunk_ids || []).join(', ') || '-'}</div><pre className="mt-2 max-h-64 overflow-auto whitespace-pre-wrap text-xs text-gray-700 dark:text-gray-300">{log.context_text}</pre></div>)}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
