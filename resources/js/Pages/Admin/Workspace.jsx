import { t } from '@/localization';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useState } from 'react';
import { Head, Link, usePage } from '@inertiajs/react';

export default function Workspace({ section, tenants = [] }) {
    const { auth } = usePage().props;
    const [tenantId, setTenantId] = useState('');
    const admin = section === 'admin';
    const entries = admin
        ? [
              [
                  'tenants.index',
                  'Tenants',
                  'Manage client workspaces and their admins.',
              ],
              [
                  'staff.index',
                  'Team',
                  'Manage support staff and account access.',
              ],
              [
                  'language-settings.edit',
                  'Language settings',
                  'Choose the languages available in the app.',
              ],
          ]
        : [
              [
                  'bots.index',
                  'Bots',
                  'Configure your assistants and integrations.',
              ],
              [
                  'knowledge-sources.index',
                  'Knowledge',
                  'Manage the information your assistants use.',
              ],
              ...(!auth.capabilities.platform
                  ? [
                        [
                            'ai-settings.edit',
                            'AI Settings',
                            'Manage AI credentials and models.',
                        ],
                    ]
                  : []),
              [
                  'widget.install',
                  'Widget',
                  'Install and customize your chat widget.',
              ],
              [
                  'dashboard',
                  'Analytics',
                  'Review conversation activity and AI usage.',
              ],
              ...(!auth.capabilities.platform
                  ? [
                        [
                            'staff.index',
                            'Team',
                            'Manage support staff and account access.',
                        ],
                    ]
                  : []),
          ];
    return (
        <AuthenticatedLayout
            header={
                <h1 className="text-xl font-semibold">
                    {t(admin ? 'Admin' : 'Settings')}
                </h1>
            }
        >
            <Head title={t(admin ? 'Admin' : 'Settings')} />
            <div className="mx-auto grid max-w-5xl gap-4 p-6 sm:grid-cols-2 lg:grid-cols-3">
                {entries.map(([name, title, description]) => (
                    <Link
                        key={name}
                        href={route(name)}
                        className="rounded-xl border border-gray-200 bg-white p-6 transition hover:border-indigo-400 focus-visible:ring-2 focus-visible:ring-indigo-500 dark:border-gray-800 dark:bg-gray-900"
                    >
                        <h2 className="font-semibold">{t(title)}</h2>
                        <p className="mt-2 text-sm leading-6 text-gray-500 dark:text-gray-400">
                            {t(description)}
                        </p>
                    </Link>
                ))}
                {!admin && auth.capabilities.platform && (
                    <div className="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900">
                        <h2 className="font-semibold">{t('AI Settings')}</h2>
                        <label className="mt-3 block text-sm">
                            {t('Choose a workspace')}
                            <select
                                value={tenantId}
                                onChange={(e) => setTenantId(e.target.value)}
                                className="mt-2 w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900"
                            >
                                <option value="">
                                    {t('Choose a workspace')}
                                </option>
                                {tenants.map((tenant) => (
                                    <option key={tenant.id} value={tenant.id}>
                                        {tenant.name}
                                    </option>
                                ))}
                            </select>
                        </label>
                        {tenantId && (
                            <Link
                                href={route('ai-settings.edit', {
                                    tenant_id: tenantId,
                                })}
                                className="mt-3 inline-block text-sm text-indigo-600 dark:text-indigo-400"
                            >
                                {t('AI Settings')}
                                {' →'}
                            </Link>
                        )}
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
