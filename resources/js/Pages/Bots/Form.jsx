import Checkbox from '@/Components/Checkbox';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';

export default function Form({ bot, widgetSnippet = null }) {
    const isEditing = Boolean(bot);

    const { data, setData, post, put, processing, errors, recentlySuccessful } = useForm({
        name: bot?.name || '',
        status: bot?.status || 'active',
        welcome_message: bot?.welcome_message || '',
        fallback_message: bot?.fallback_message || '',
        system_prompt: bot?.system_prompt || '',
        allowed_domains: (bot?.allowed_domains || []).join('\n'),
        temperature: bot?.temperature ?? '0.20',
        max_context_chunks: bot?.max_context_chunks ?? 6,
        similarity_threshold: bot?.similarity_threshold ?? '0.550',
        collect_visitor_email: bot?.collect_visitor_email ?? true,
        collect_visitor_phone: bot?.collect_visitor_phone ?? false,
    });

    const submit = (e) => {
        e.preventDefault();
        if (isEditing) {
            put(route('bots.update', bot.id), { preserveScroll: true });
            return;
        }

        post(route('bots.store'));
    };

    const embedSnippet = bot ? widgetSnippet : null;

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h2 className="text-xl font-semibold text-gray-800 dark:text-gray-200">
                            {isEditing ? 'Edit bot' : 'New bot'}
                        </h2>
                        <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            {isEditing
                                ? 'Update messages, retrieval settings, and where the widget may run.'
                                : 'Create a bot, then add knowledge sources and embed the chat widget.'}
                        </p>
                    </div>
                    <Link
                        href={route('bots.index')}
                        className="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold normal-case tracking-normal text-gray-700 shadow-sm transition hover:bg-gray-50 dark:border-gray-500 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                    >
                        Back to bots
                    </Link>
                </div>
            }
        >
            <Head title={isEditing ? `Edit ${bot.name}` : 'New bot'} />

            <form onSubmit={submit} className="mx-auto max-w-4xl space-y-6 p-6">
                {isEditing && embedSnippet && (
                    <EmbedSnippetCard snippet={embedSnippet} publicKey={bot.public_key} />
                )}

                <section className="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                    <SectionHeading
                        title="Basics"
                        description="Name and availability for this bot."
                    />
                    <div className="mt-5 grid gap-5 sm:grid-cols-2">
                        <FormField
                            id="name"
                            label="Bot name"
                            value={data.name}
                            onChange={(value) => setData('name', value)}
                            error={errors.name}
                            placeholder="Support assistant"
                        />
                        <div>
                            <InputLabel htmlFor="status" value="Status" />
                            <select
                                id="status"
                                value={data.status}
                                onChange={(e) => setData('status', e.target.value)}
                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:focus:border-indigo-600 dark:focus:ring-indigo-600"
                            >
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                            <InputError message={errors.status} className="mt-1" />
                        </div>
                    </div>
                </section>

                <section className="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                    <SectionHeading
                        title="Messages"
                        description="What visitors see when the chat opens and when the bot cannot answer."
                    />
                    <div className="mt-5 space-y-5">
                        <FormField
                            id="welcome_message"
                            label="Welcome message"
                            value={data.welcome_message}
                            onChange={(value) => setData('welcome_message', value)}
                            error={errors.welcome_message}
                            placeholder="Hi! How can I help you today?"
                        />
                        <FormField
                            id="fallback_message"
                            label="Fallback message"
                            value={data.fallback_message}
                            onChange={(value) => setData('fallback_message', value)}
                            error={errors.fallback_message}
                            hint="Shown when the bot cannot answer from knowledge."
                        />
                        <TextAreaField
                            id="system_prompt"
                            label="System prompt"
                            value={data.system_prompt}
                            onChange={(value) => setData('system_prompt', value)}
                            error={errors.system_prompt}
                            rows={8}
                            hint="Instructions that shape tone, scope, and safety for every reply."
                        />
                    </div>
                </section>

                <section className="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                    <SectionHeading
                        title="Retrieval"
                        description="Tune how aggressively the bot pulls context from your knowledge base."
                    />
                    <div className="mt-5 space-y-6">
                        <RangeField
                            id="temperature"
                            label="Temperature"
                            value={Number(data.temperature)}
                            min={0}
                            max={1}
                            step={0.05}
                            onChange={(value) => setData('temperature', value.toFixed(2))}
                            error={errors.temperature}
                            hint="Higher values are more creative; lower values stay closer to retrieved context."
                        />
                        <RangeField
                            id="similarity_threshold"
                            label="Similarity threshold"
                            value={Number(data.similarity_threshold)}
                            min={0}
                            max={1}
                            step={0.05}
                            onChange={(value) => setData('similarity_threshold', value.toFixed(3))}
                            error={errors.similarity_threshold}
                            hint="Minimum relevance score for a knowledge chunk to be included."
                        />
                        <div>
                            <InputLabel htmlFor="max_context_chunks" value="Max context chunks" />
                            <TextInput
                                id="max_context_chunks"
                                type="number"
                                min={1}
                                max={12}
                                value={data.max_context_chunks}
                                onChange={(e) => setData('max_context_chunks', e.target.value)}
                                className="mt-1 block w-full sm:max-w-xs"
                            />
                            <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Number of knowledge chunks sent to the model (1–12).
                            </p>
                            <InputError message={errors.max_context_chunks} className="mt-1" />
                        </div>
                    </div>
                </section>

                <section className="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                    <SectionHeading
                        title="Security"
                        description="Restrict the widget to specific hostnames. Leave empty to allow any domain."
                    />
                    <TextAreaField
                        id="allowed_domains"
                        label="Allowed domains"
                        value={data.allowed_domains}
                        onChange={(value) => setData('allowed_domains', value)}
                        error={errors.allowed_domains}
                        rows={4}
                        placeholder={'app.example.com\ncrm.example.com'}
                        hint="One domain per line. Subdomains must be listed explicitly."
                        mono
                    />
                </section>

                <section className="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                    <SectionHeading
                        title="Lead capture"
                        description="Ask visitors for contact details when the bot cannot answer."
                    />
                    <div className="mt-5 space-y-4">
                        <label className="flex items-start gap-3">
                            <Checkbox
                                checked={data.collect_visitor_email}
                                onChange={(e) => setData('collect_visitor_email', e.target.checked)}
                            />
                            <span>
                                <span className="block text-sm font-medium text-gray-900 dark:text-white">Collect email</span>
                                <span className="block text-xs text-gray-500 dark:text-gray-400">
                                    Show an email field in the widget contact form.
                                </span>
                            </span>
                        </label>
                        <label className="flex items-start gap-3">
                            <Checkbox
                                checked={data.collect_visitor_phone}
                                onChange={(e) => setData('collect_visitor_phone', e.target.checked)}
                            />
                            <span>
                                <span className="block text-sm font-medium text-gray-900 dark:text-white">Collect phone</span>
                                <span className="block text-xs text-gray-500 dark:text-gray-400">
                                    Show a phone field in the widget contact form.
                                </span>
                            </span>
                        </label>
                    </div>
                </section>

                <div className="flex flex-wrap items-center gap-3">
                    <PrimaryButton disabled={processing}>
                        {processing ? 'Saving…' : isEditing ? 'Save changes' : 'Create bot'}
                    </PrimaryButton>
                    <Link
                        href={route('bots.index')}
                        className="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold normal-case tracking-normal text-gray-700 shadow-sm transition hover:bg-gray-50 dark:border-gray-500 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                    >
                        Cancel
                    </Link>
                    {recentlySuccessful && (
                        <span className="text-sm text-emerald-600 dark:text-emerald-400">Saved.</span>
                    )}
                </div>
            </form>
        </AuthenticatedLayout>
    );
}

function EmbedSnippetCard({ snippet, publicKey }) {
    const [copied, setCopied] = useState(false);

    const copySnippet = async () => {
        try {
            await navigator.clipboard.writeText(snippet);
            setCopied(true);
            window.setTimeout(() => setCopied(false), 2000);
        } catch {
            window.prompt('Copy this embed code:', snippet);
        }
    };

    return (
        <section className="rounded-lg border border-indigo-100 bg-indigo-50 p-6 dark:border-indigo-900/50 dark:bg-indigo-950/30">
            <div className="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h3 className="text-base font-semibold text-gray-900 dark:text-white">Embed widget</h3>
                    <p className="mt-1 text-sm text-gray-600 dark:text-gray-300">
                        Add this script before <code className="text-xs">&lt;/body&gt;</code> on sites listed in allowed domains.
                    </p>
                    <p className="mt-2 font-mono text-xs text-gray-500 dark:text-gray-400">Public key: {publicKey}</p>
                </div>
                <SecondaryButton type="button" onClick={copySnippet} className="!normal-case !tracking-normal">
                    {copied ? 'Copied' : 'Copy snippet'}
                </SecondaryButton>
            </div>
            <pre className="mt-4 overflow-x-auto rounded-lg bg-gray-950 p-4 text-sm text-gray-100">{snippet}</pre>
        </section>
    );
}

function SectionHeading({ title, description }) {
    return (
        <div>
            <h3 className="text-base font-semibold text-gray-900 dark:text-white">{title}</h3>
            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">{description}</p>
        </div>
    );
}

function FormField({ id, label, value, onChange, error, placeholder, hint }) {
    return (
        <div>
            <InputLabel htmlFor={id} value={label} />
            <TextInput
                id={id}
                value={value}
                onChange={(e) => onChange(e.target.value)}
                className="mt-1 block w-full"
                placeholder={placeholder}
            />
            {hint && <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">{hint}</p>}
            <InputError message={error} className="mt-1" />
        </div>
    );
}

function TextAreaField({ id, label, value, onChange, error, rows = 4, placeholder, hint, mono = false }) {
    return (
        <div>
            <InputLabel htmlFor={id} value={label} />
            <textarea
                id={id}
                rows={rows}
                value={value}
                onChange={(e) => onChange(e.target.value)}
                placeholder={placeholder}
                className={
                    'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:focus:border-indigo-600 dark:focus:ring-indigo-600 ' +
                    (mono ? 'font-mono text-sm' : '')
                }
            />
            {hint && <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">{hint}</p>}
            <InputError message={error} className="mt-1" />
        </div>
    );
}

function RangeField({ id, label, value, min, max, step, onChange, error, hint }) {
    return (
        <div>
            <div className="flex items-center justify-between gap-4">
                <InputLabel htmlFor={id} value={label} />
                <span className="rounded bg-gray-100 px-2 py-0.5 font-mono text-sm text-gray-800 dark:bg-gray-900 dark:text-gray-200">
                    {value}
                </span>
            </div>
            <input
                id={id}
                type="range"
                min={min}
                max={max}
                step={step}
                value={value}
                onChange={(e) => onChange(Number(e.target.value))}
                className="mt-3 w-full accent-indigo-600"
            />
            {hint && <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">{hint}</p>}
            <InputError message={error} className="mt-1" />
        </div>
    );
}
