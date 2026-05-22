import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';

export default function Form({ bot }) {
    const { data, setData, post, put, processing, errors } = useForm({
        name: bot?.name || '',
        status: bot?.status || 'active',
        welcome_message: bot?.welcome_message || '',
        fallback_message: bot?.fallback_message || '',
        system_prompt: bot?.system_prompt || '',
        allowed_domains: (bot?.allowed_domains || []).join('\n'),
        temperature: bot?.temperature || '0.20',
        max_context_chunks: bot?.max_context_chunks || 6,
        similarity_threshold: bot?.similarity_threshold || '0.550',
        collect_visitor_email: bot?.collect_visitor_email ?? true,
        collect_visitor_phone: bot?.collect_visitor_phone ?? false,
    });
    const submit = (e) => { e.preventDefault(); bot ? put(route('bots.update', bot.id)) : post(route('bots.store')); };

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-gray-800 dark:text-gray-200">{bot ? 'Edit bot' : 'New bot'}</h2>}>
            <Head title="Bot" />
            <form onSubmit={submit} className="mx-auto max-w-4xl space-y-4 p-6">
                <div className="rounded-lg bg-white p-5 shadow dark:bg-gray-800">
                    {['name', 'welcome_message', 'fallback_message'].map((field) => <Field key={field} field={field} data={data} setData={setData} errors={errors} />)}
                    <label className="mt-4 block text-sm text-gray-700 dark:text-gray-300">System prompt<textarea value={data.system_prompt} onChange={(e) => setData('system_prompt', e.target.value)} className="mt-1 h-36 w-full rounded border-gray-300 dark:border-gray-700 dark:bg-gray-900" /></label>
                    <label className="mt-4 block text-sm text-gray-700 dark:text-gray-300">Allowed domains<textarea value={data.allowed_domains} onChange={(e) => setData('allowed_domains', e.target.value)} className="mt-1 h-20 w-full rounded border-gray-300 dark:border-gray-700 dark:bg-gray-900" /></label>
                    <div className="mt-4 grid gap-4 md:grid-cols-4">
                        <Field field="status" data={data} setData={setData} errors={errors} />
                        <Field field="temperature" data={data} setData={setData} errors={errors} />
                        <Field field="max_context_chunks" data={data} setData={setData} errors={errors} />
                        <Field field="similarity_threshold" data={data} setData={setData} errors={errors} />
                    </div>
                    <label className="mt-4 block text-sm"><input type="checkbox" checked={data.collect_visitor_email} onChange={(e) => setData('collect_visitor_email', e.target.checked)} /> Collect email</label>
                    <label className="mt-2 block text-sm"><input type="checkbox" checked={data.collect_visitor_phone} onChange={(e) => setData('collect_visitor_phone', e.target.checked)} /> Collect phone</label>
                    <button disabled={processing} className="mt-5 rounded bg-gray-900 px-4 py-2 text-sm text-white">Save</button>
                </div>
            </form>
        </AuthenticatedLayout>
    );
}

function Field({ field, data, setData, errors }) {
    return <label className="mt-4 block text-sm text-gray-700 dark:text-gray-300">{field.replaceAll('_', ' ')}<input value={data[field]} onChange={(e) => setData(field, e.target.value)} className="mt-1 w-full rounded border-gray-300 dark:border-gray-700 dark:bg-gray-900" />{errors[field] && <span className="text-xs text-red-600">{errors[field]}</span>}</label>;
}
