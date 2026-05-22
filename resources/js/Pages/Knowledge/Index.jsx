import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';

export default function Index({ sources, bots }) {
    const { data, setData, post, processing, errors, reset } = useForm({ bot_id: bots[0]?.id || '', type: 'text', title: '', raw_text: '', question: '', answer: '', file: null });
    const submit = (e) => { e.preventDefault(); post(route('knowledge-sources.store'), { forceFormData: true, onSuccess: () => reset('title', 'raw_text', 'question', 'answer', 'file') }); };

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-gray-800 dark:text-gray-200">Knowledge</h2>}>
            <Head title="Knowledge" />
            <div className="mx-auto grid max-w-7xl gap-6 p-6 lg:grid-cols-[420px_1fr]">
                <form onSubmit={submit} className="rounded-lg bg-white p-5 shadow dark:bg-gray-800">
                    <h3 className="font-semibold text-gray-900 dark:text-white">Add source</h3>
                    <label className="mt-4 block text-sm">Bot<select value={data.bot_id} onChange={(e) => setData('bot_id', e.target.value)} className="mt-1 w-full rounded border-gray-300 dark:border-gray-700 dark:bg-gray-900">{bots.map((bot) => <option key={bot.id} value={bot.id}>{bot.name}</option>)}</select></label>
                    <label className="mt-4 block text-sm">Type<select value={data.type} onChange={(e) => setData('type', e.target.value)} className="mt-1 w-full rounded border-gray-300 dark:border-gray-700 dark:bg-gray-900">{['text', 'faq', 'pdf', 'docx'].map((type) => <option key={type}>{type}</option>)}</select></label>
                    <Field field="title" data={data} setData={setData} errors={errors} />
                    {data.type === 'text' && <label className="mt-4 block text-sm">Text<textarea value={data.raw_text} onChange={(e) => setData('raw_text', e.target.value)} className="mt-1 h-36 w-full rounded border-gray-300 dark:border-gray-700 dark:bg-gray-900" /></label>}
                    {data.type === 'faq' && <><Field field="question" data={data} setData={setData} errors={errors} /><label className="mt-4 block text-sm">Answer<textarea value={data.answer} onChange={(e) => setData('answer', e.target.value)} className="mt-1 h-28 w-full rounded border-gray-300 dark:border-gray-700 dark:bg-gray-900" /></label></>}
                    {(data.type === 'pdf' || data.type === 'docx') && <label className="mt-4 block text-sm">File<input type="file" onChange={(e) => setData('file', e.target.files[0])} className="mt-1 block w-full text-sm" /></label>}
                    <button disabled={processing || bots.length === 0} className="mt-5 rounded bg-gray-900 px-4 py-2 text-sm text-white">Queue processing</button>
                </form>
                <div className="rounded-lg bg-white p-5 shadow dark:bg-gray-800">
                    <table className="w-full text-left text-sm">
                        <thead className="text-gray-500"><tr><th>Title</th><th>Bot</th><th>Type</th><th>Status</th><th>Chunks</th><th></th></tr></thead>
                        <tbody className="divide-y dark:divide-gray-700">{sources.map((source) => <tr key={source.id}><td className="py-2">{source.title}</td><td>{source.bot?.name}</td><td>{source.type}</td><td>{source.status}</td><td>{source.chunks_count}</td><td><Link href={route('knowledge-sources.show', source.id)} className="text-indigo-600">View</Link><button onClick={() => router.post(route('knowledge-sources.reprocess', source.id))} className="ml-3 text-indigo-600">Reprocess</button><button onClick={() => router.delete(route('knowledge-sources.destroy', source.id))} className="ml-3 text-red-600">Delete</button></td></tr>)}</tbody>
                    </table>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function Field({ field, data, setData, errors }) {
    return <label className="mt-4 block text-sm">{field}<input value={data[field]} onChange={(e) => setData(field, e.target.value)} className="mt-1 w-full rounded border-gray-300 dark:border-gray-700 dark:bg-gray-900" />{errors[field] && <span className="text-xs text-red-600">{errors[field]}</span>}</label>;
}
