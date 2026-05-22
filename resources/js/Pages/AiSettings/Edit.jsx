import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

const CHAT_MODELS = ['gpt-4o-mini', 'gpt-4o', 'gpt-4.1-mini', 'gpt-4.1'];
const EMBEDDING_MODELS = ['text-embedding-3-small', 'text-embedding-3-large'];

export default function Edit({ settings }) {
    const [testing, setTesting] = useState(false);

    const { data, setData, put, processing, errors, recentlySuccessful } = useForm({
        api_key: '',
        base_url: settings.base_url,
        chat_model: settings.chat_model,
        embedding_model: settings.embedding_model,
    });

    const submit = (e) => {
        e.preventDefault();
        put(route('ai-settings.update'), { preserveScroll: true });
    };

    const testConnection = () => {
        router.post(route('ai-settings.test'), {}, {
            preserveScroll: true,
            onStart: () => setTesting(true),
            onFinish: () => setTesting(false),
        });
    };

    return (
        <AuthenticatedLayout
            header={
                <div>
                    <h2 className="text-xl font-semibold text-gray-800 dark:text-gray-200">AI Settings</h2>
                    <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Configure OpenAI credentials and models used for chat and knowledge embeddings.
                    </p>
                </div>
            }
        >
            <Head title="AI Settings" />

            <div className="mx-auto max-w-3xl space-y-6 p-6">
                <StatusOverview settings={settings} />
                <TestResultCard settings={settings} testing={testing} />

                <form onSubmit={submit} className="space-y-6">
                    <section className="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                        <h3 className="text-base font-semibold text-gray-900 dark:text-white">Connection</h3>
                        <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Your API key is encrypted at rest. Leave the key field blank to keep the current value.
                        </p>

                        <div className="mt-5 space-y-5">
                            <div>
                                <InputLabel htmlFor="api_key" value="API key" />
                                <TextInput
                                    id="api_key"
                                    type="password"
                                    value={data.api_key}
                                    onChange={(e) => setData('api_key', e.target.value)}
                                    className="mt-1 block w-full"
                                    placeholder={settings.masked_api_key ? 'Leave blank to keep current key' : 'sk-...'}
                                    autoComplete="off"
                                />
                                <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    {settings.masked_api_key
                                        ? `Current key: ${settings.masked_api_key}`
                                        : 'No API key stored yet.'}
                                </p>
                                <InputError message={errors.api_key} className="mt-1" />
                            </div>

                            <div>
                                <InputLabel htmlFor="base_url" value="Base URL" />
                                <TextInput
                                    id="base_url"
                                    type="url"
                                    value={data.base_url}
                                    onChange={(e) => setData('base_url', e.target.value)}
                                    className="mt-1 block w-full"
                                    placeholder="https://api.openai.com/v1"
                                />
                                <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    Use a compatible OpenAI API endpoint (e.g. Azure OpenAI or a proxy).
                                </p>
                                <InputError message={errors.base_url} className="mt-1" />
                            </div>
                        </div>
                    </section>

                    <section className="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                        <h3 className="text-base font-semibold text-gray-900 dark:text-white">Models</h3>
                        <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Chat model powers bot replies. Embedding model indexes your knowledge base.
                        </p>

                        <div className="mt-5 grid gap-5 sm:grid-cols-2">
                            <ModelField
                                id="chat_model"
                                label="Chat model"
                                value={data.chat_model}
                                options={CHAT_MODELS}
                                onChange={(value) => setData('chat_model', value)}
                                error={errors.chat_model}
                            />
                            <ModelField
                                id="embedding_model"
                                label="Embedding model"
                                value={data.embedding_model}
                                options={EMBEDDING_MODELS}
                                onChange={(value) => setData('embedding_model', value)}
                                error={errors.embedding_model}
                            />
                        </div>

                        <p className="mt-4 text-xs text-gray-500 dark:text-gray-400">
                            Embedding dimensions are fixed at {settings.embedding_dimensions} for this tenant.
                        </p>
                    </section>

                    <div className="flex flex-wrap items-center gap-3">
                        <PrimaryButton disabled={processing}>
                            {processing ? 'Saving…' : 'Save settings'}
                        </PrimaryButton>
                        <SecondaryButton type="button" onClick={testConnection} disabled={testing || processing}>
                            {testing ? 'Testing…' : 'Test connection'}
                        </SecondaryButton>
                        {recentlySuccessful && (
                            <span className="text-sm text-emerald-600 dark:text-emerald-400">Saved.</span>
                        )}
                    </div>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}

function StatusOverview({ settings }) {
    const items = [
        { label: 'Provider', value: settings.provider },
        { label: 'API key', value: settings.masked_api_key || 'Not set' },
        { label: 'Chat model', value: settings.chat_model },
        { label: 'Embedding model', value: settings.embedding_model },
    ];

    return (
        <section className="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
            <div className="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h3 className="text-base font-semibold text-gray-900 dark:text-white">Overview</h3>
                    <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Bots and knowledge indexing only work when the connection is active.
                    </p>
                </div>
                <StatusBadge active={settings.is_active} />
            </div>

            <dl className="mt-5 grid gap-4 sm:grid-cols-2">
                {items.map(({ label, value }) => (
                    <div key={label} className="rounded-lg border border-gray-100 bg-gray-50 px-4 py-3 dark:border-gray-700 dark:bg-gray-900/50">
                        <dt className="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{label}</dt>
                        <dd className="mt-1 text-sm font-medium text-gray-900 dark:text-white">{value}</dd>
                    </div>
                ))}
            </dl>
        </section>
    );
}

function StatusBadge({ active }) {
    return (
        <span
            className={`inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold ${
                active
                    ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300'
                    : 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300'
            }`}
        >
            <span className={`mr-1.5 inline-block h-2 w-2 rounded-full ${active ? 'bg-emerald-500' : 'bg-amber-500'}`} />
            {active ? 'Active' : 'Inactive'}
        </span>
    );
}

function TestResultCard({ settings, testing }) {
    const hasResult = settings.last_test_status || testing;
    const isSuccess = settings.last_test_status === 'success';
    const isFailed = settings.last_test_status === 'failed';

    if (!hasResult) {
        return (
            <section className="rounded-lg border border-dashed border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
                <h3 className="text-base font-semibold text-gray-900 dark:text-white">Connection test</h3>
                <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Save your settings, then run a test to verify the API key and embedding model.
                </p>
            </section>
        );
    }

    return (
        <section
            className={`rounded-lg border p-6 ${
                testing
                    ? 'border-indigo-200 bg-indigo-50 dark:border-indigo-900/50 dark:bg-indigo-950/30'
                    : isSuccess
                      ? 'border-emerald-200 bg-emerald-50 dark:border-emerald-900/50 dark:bg-emerald-950/30'
                      : isFailed
                        ? 'border-red-200 bg-red-50 dark:border-red-900/50 dark:bg-red-950/30'
                        : 'border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800'
            }`}
        >
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h3 className="text-base font-semibold text-gray-900 dark:text-white">Connection test</h3>
                    {settings.last_tested_at && !testing && (
                        <p className="mt-1 text-sm text-gray-600 dark:text-gray-300">
                            Last run {formatDateTime(settings.last_tested_at)}
                        </p>
                    )}
                </div>
                {!testing && settings.last_test_status && (
                    <span
                        className={`inline-flex rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-wide ${
                            isSuccess
                                ? 'bg-emerald-200 text-emerald-900 dark:bg-emerald-900/60 dark:text-emerald-200'
                                : 'bg-red-200 text-red-900 dark:bg-red-900/60 dark:text-red-200'
                        }`}
                    >
                        {settings.last_test_status}
                    </span>
                )}
            </div>

            {testing ? (
                <div className="mt-4 flex items-center gap-3 text-sm text-indigo-800 dark:text-indigo-200">
                    <span className="inline-block h-4 w-4 animate-spin rounded-full border-2 border-indigo-600 border-t-transparent dark:border-indigo-300" />
                    Running embedding test against your configured endpoint…
                </div>
            ) : (
                <div className="mt-4">
                    {isSuccess && (
                        <p className="text-sm text-emerald-800 dark:text-emerald-200">
                            Connection verified. Chat and embeddings can use these settings.
                        </p>
                    )}
                    {isFailed && (
                        <div className="space-y-2">
                            <p className="text-sm text-red-800 dark:text-red-200">
                                Connection failed. Check your API key, base URL, and embedding model.
                            </p>
                            {settings.last_test_error && (
                                <pre className="overflow-x-auto rounded-md bg-red-100/80 p-3 text-xs text-red-900 dark:bg-red-950/60 dark:text-red-200">
                                    {settings.last_test_error}
                                </pre>
                            )}
                        </div>
                    )}
                </div>
            )}
        </section>
    );
}

function ModelField({ id, label, value, options, onChange, error }) {
    return (
        <div>
            <InputLabel htmlFor={id} value={label} />
            <TextInput
                id={id}
                list={`${id}-suggestions`}
                value={value}
                onChange={(e) => onChange(e.target.value)}
                className="mt-1 block w-full"
            />
            <datalist id={`${id}-suggestions`}>
                {options.map((option) => (
                    <option key={option} value={option} />
                ))}
            </datalist>
            <InputError message={error} className="mt-1" />
        </div>
    );
}

function formatDateTime(value) {
    try {
        return new Intl.DateTimeFormat(undefined, {
            dateStyle: 'medium',
            timeStyle: 'short',
        }).format(new Date(value));
    } catch {
        return value;
    }
}
