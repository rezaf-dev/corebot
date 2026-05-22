import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, useForm } from '@inertiajs/react';

export default function Edit({ settings }) {
    const { data, setData, put, processing, errors } = useForm({ api_key: '', base_url: settings.base_url, chat_model: settings.chat_model, embedding_model: settings.embedding_model });
    const submit = (e) => { e.preventDefault(); put(route('ai-settings.update')); };

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-gray-800 dark:text-gray-200">AI Settings</h2>}>
            <Head title="AI Settings" />
            <div className="mx-auto max-w-3xl p-6">
                <form onSubmit={submit} className="rounded-lg bg-white p-5 shadow dark:bg-gray-800">
                    <div className="text-sm text-gray-600 dark:text-gray-400">Provider: OpenAI | Status: {settings.is_active ? 'Active' : 'Inactive'} | Key: {settings.masked_api_key || 'none'}</div>
                    {['api_key', 'base_url', 'chat_model', 'embedding_model'].map((field) => (
                        <label key={field} className="mt-4 block text-sm text-gray-700 dark:text-gray-300">
                            {field.replace('_', ' ')}
                            <input type={field === 'api_key' ? 'password' : 'text'} value={data[field]} onChange={(e) => setData(field, e.target.value)} placeholder={field === 'api_key' ? 'Leave blank to keep current key' : ''} className="mt-1 w-full rounded border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100" />
                            {errors[field] && <span className="text-xs text-red-600 dark:text-red-400">{errors[field]}</span>}
                        </label>
                    ))}
                    <div className="mt-5 flex gap-3">
                        <button disabled={processing} className="rounded bg-gray-900 px-4 py-2 text-sm text-white">Save</button>
                        <button type="button" onClick={() => router.post(route('ai-settings.test'))} className="rounded border px-4 py-2 text-sm dark:border-gray-700 dark:text-gray-200">Test API Key</button>
                    </div>
                    {settings.last_test_status && <p className="mt-4 text-sm text-gray-600 dark:text-gray-400">Last test: {settings.last_test_status} {settings.last_test_error ? `- ${settings.last_test_error}` : ''}</p>}
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
