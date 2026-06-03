import Checkbox from '@/Components/Checkbox';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import { router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';

const EMPTY_INTEGRATION = {
    type: 'webhook',
    name: '',
    description: '',
    enabled: true,
    allowed_domains: '',
    config: { url: '', method: 'POST', to_email: '', subject: '' },
    credentials: { bearer_token: '' },
};

export default function BotIntegrationsSection({ bot, integrationTypes }) {
    const { errors } = usePage().props;
    const integrations = bot?.integrations ?? [];
    const [editingId, setEditingId] = useState(null);

    const form = useForm({ ...EMPTY_INTEGRATION });

    const startCreate = () => {
        setEditingId('new');
        form.reset();
        form.setData({ ...EMPTY_INTEGRATION });
    };

    const startEdit = (integration) => {
        setEditingId(integration.id);
        form.setData({
            type: integration.type,
            name: integration.name,
            description: integration.description || '',
            enabled: integration.enabled,
            allowed_domains: (integration.allowed_domains || []).join('\n'),
            config: {
                url: integration.config?.url || '',
                method: integration.config?.method || 'POST',
                to_email: integration.config?.to_email || '',
                subject: integration.config?.subject || '',
            },
            credentials: { bearer_token: '' },
        });
    };

    const cancelEdit = () => {
        setEditingId(null);
        form.reset();
    };

    const submit = (e) => {
        e.preventDefault();

        if (editingId === 'new') {
            form.post(route('bots.integrations.store', bot.id), {
                preserveScroll: true,
                onSuccess: cancelEdit,
            });
            return;
        }

        form.put(route('bots.integrations.update', [bot.id, editingId]), {
            preserveScroll: true,
            onSuccess: cancelEdit,
        });
    };

    const remove = (integration) => {
        if (!window.confirm(`Remove integration "${integration.name}"?`)) {
            return;
        }

        router.delete(route('bots.integrations.destroy', [bot.id, integration.id]), {
            preserveScroll: true,
        });
    };

    const testConnection = (integration) => {
        router.post(route('bots.integrations.test', [bot.id, integration.id]), {}, { preserveScroll: true });
    };

    const showUrl = ['webhook', 'http_get'].includes(form.data.type);
    const showEmail = form.data.type === 'send_email';
    const showMethod = form.data.type === 'webhook';

    return (
        <section className="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
            <div className="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h3 className="text-base font-semibold text-gray-900 dark:text-white">Integrations</h3>
                    <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Actions the chatbot can run during conversations (webhooks, leads, email, API queries).
                    </p>
                </div>
                {editingId === null && (
                    <SecondaryButton type="button" onClick={startCreate} className="!normal-case !tracking-normal">
                        Add integration
                    </SecondaryButton>
                )}
            </div>

            <InputError message={errors?.integration_test} className="mt-4" />

            {integrations.length > 0 && (
                <ul className="mt-5 divide-y divide-gray-100 rounded-lg border border-gray-100 dark:divide-gray-700 dark:border-gray-700">
                    {integrations.map((integration) => (
                        <li key={integration.id} className="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
                            <div>
                                <p className="text-sm font-medium text-gray-900 dark:text-white">
                                    {integration.name}
                                    {!integration.enabled && (
                                        <span className="ml-2 text-xs text-amber-600 dark:text-amber-400">Disabled</span>
                                    )}
                                </p>
                                <p className="text-xs text-gray-500 dark:text-gray-400">
                                    {integration.type} · {integration.tool_name || `integration_${integration.id}`}
                                </p>
                            </div>
                            <div className="flex flex-wrap gap-2">
                                <SecondaryButton type="button" onClick={() => testConnection(integration)} className="!normal-case !tracking-normal">
                                    Test
                                </SecondaryButton>
                                <SecondaryButton type="button" onClick={() => startEdit(integration)} className="!normal-case !tracking-normal">
                                    Edit
                                </SecondaryButton>
                                <SecondaryButton type="button" onClick={() => remove(integration)} className="!normal-case !tracking-normal">
                                    Remove
                                </SecondaryButton>
                            </div>
                        </li>
                    ))}
                </ul>
            )}

            {editingId !== null && (
                <form onSubmit={submit} className="mt-6 space-y-4 rounded-lg border border-gray-100 p-4 dark:border-gray-700">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div>
                            <InputLabel htmlFor="integration_type" value="Type" />
                            <select
                                id="integration_type"
                                value={form.data.type}
                                onChange={(e) => form.setData('type', e.target.value)}
                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                            >
                                {integrationTypes.map((option) => (
                                    <option key={option.value} value={option.value}>
                                        {option.label}
                                    </option>
                                ))}
                            </select>
                            <InputError message={form.errors.type} className="mt-1" />
                        </div>
                        <div>
                            <InputLabel htmlFor="integration_name" value="Name" />
                            <TextInput
                                id="integration_name"
                                value={form.data.name}
                                onChange={(e) => form.setData('name', e.target.value)}
                                className="mt-1 block w-full"
                            />
                            <InputError message={form.errors.name} className="mt-1" />
                        </div>
                    </div>

                    <div>
                        <InputLabel htmlFor="integration_description" value="Description for the AI" />
                        <textarea
                            id="integration_description"
                            rows={2}
                            value={form.data.description}
                            onChange={(e) => form.setData('description', e.target.value)}
                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                            placeholder="Create a sales lead when the visitor asks to be contacted."
                        />
                        <InputError message={form.errors.description} className="mt-1" />
                    </div>

                    {(showUrl || form.data.type === 'http_get') && (
                        <div>
                            <InputLabel htmlFor="integration_url" value="URL" />
                            <TextInput
                                id="integration_url"
                                value={form.data.config.url}
                                onChange={(e) => form.setData('config', { ...form.data.config, url: e.target.value })}
                                className="mt-1 block w-full font-mono text-sm"
                                placeholder={
                                    form.data.type === 'http_get'
                                        ? 'https://api.example.com/verify/{recommendation_id}'
                                        : 'https://hooks.example.com/lead'
                                }
                            />
                            {form.data.type === 'http_get' && (
                                <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    Use {'{parameter_name}'} in the path for values the visitor provides (e.g. recommendation ID).
                                </p>
                            )}
                            <InputError message={form.errors['config.url']} className="mt-1" />
                        </div>
                    )}

                    {showMethod && (
                        <div>
                            <InputLabel htmlFor="integration_method" value="HTTP method" />
                            <select
                                id="integration_method"
                                value={form.data.config.method}
                                onChange={(e) => form.setData('config', { ...form.data.config, method: e.target.value })}
                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                            >
                                <option value="POST">POST</option>
                                <option value="PUT">PUT</option>
                                <option value="PATCH">PATCH</option>
                            </select>
                        </div>
                    )}

                    {showEmail && (
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div>
                                <InputLabel htmlFor="integration_to_email" value="Recipient email" />
                                <TextInput
                                    id="integration_to_email"
                                    value={form.data.config.to_email}
                                    onChange={(e) => form.setData('config', { ...form.data.config, to_email: e.target.value })}
                                    className="mt-1 block w-full"
                                />
                                <InputError message={form.errors['config.to_email']} className="mt-1" />
                            </div>
                            <div>
                                <InputLabel htmlFor="integration_subject" value="Default subject" />
                                <TextInput
                                    id="integration_subject"
                                    value={form.data.config.subject}
                                    onChange={(e) => form.setData('config', { ...form.data.config, subject: e.target.value })}
                                    className="mt-1 block w-full"
                                />
                            </div>
                        </div>
                    )}

                    {(showUrl || form.data.type === 'http_get') && (
                        <div>
                            <InputLabel htmlFor="integration_allowed_domains" value="Allowed domains (SSRF guard)" />
                            <textarea
                                id="integration_allowed_domains"
                                rows={2}
                                value={form.data.allowed_domains}
                                onChange={(e) => form.setData('allowed_domains', e.target.value)}
                                placeholder="api.example.com"
                                className="mt-1 block w-full font-mono text-sm rounded-md border-gray-300 shadow-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                            />
                            <InputError message={form.errors.allowed_domains} className="mt-1" />
                        </div>
                    )}

                    {(showUrl || form.data.type === 'http_get') && (
                        <div>
                            <InputLabel htmlFor="integration_bearer" value="Bearer token (optional)" />
                            <TextInput
                                id="integration_bearer"
                                type="password"
                                value={form.data.credentials.bearer_token}
                                onChange={(e) => form.setData('credentials', { bearer_token: e.target.value })}
                                className="mt-1 block w-full"
                                placeholder={editingId !== 'new' ? 'Leave blank to keep existing' : ''}
                            />
                        </div>
                    )}

                    <label className="flex items-center gap-3">
                        <Checkbox
                            checked={form.data.enabled}
                            onChange={(e) => form.setData('enabled', e.target.checked)}
                        />
                        <span className="text-sm text-gray-900 dark:text-white">Enabled</span>
                    </label>

                    <div className="flex flex-wrap gap-2">
                        <PrimaryButton disabled={form.processing}>
                            {editingId === 'new' ? 'Add integration' : 'Save integration'}
                        </PrimaryButton>
                        <SecondaryButton type="button" onClick={cancelEdit} className="!normal-case !tracking-normal">
                            Cancel
                        </SecondaryButton>
                    </div>
                </form>
            )}
        </section>
    );
}
