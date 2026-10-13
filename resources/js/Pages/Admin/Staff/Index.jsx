import { t } from '@/localization';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Pagination from '@/Components/Pagination';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';

const field =
    'w-full rounded-lg border-gray-300 bg-white text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100';
export default function Staff({ staff, tenants }) {
    const { auth } = usePage().props;
    const [editing, setEditing] = useState(null);
    const [resending, setResending] = useState(null);
    const form = useForm({
        name: '',
        email: '',
        role: 'agent',
        tenant_id: auth.user.tenant_id || '',
        is_active: true,
    });
    const edit = (person) => {
        setEditing(person?.id || null);
        form.clearErrors();
        form.setData(
            person
                ? {
                      name: person.name,
                      email: person.email,
                      role: person.role,
                      tenant_id: person.tenant_id || '',
                      is_active: person.is_active,
                  }
                : {
                      name: '',
                      email: '',
                      role: 'agent',
                      tenant_id: auth.user.tenant_id || '',
                      is_active: true,
                  },
        );
    };
    const submit = (e) => {
        e.preventDefault();
        const options = { preserveScroll: true, onSuccess: () => edit(null) };
        if (editing) form.put(route('staff.update', editing), options);
        else form.post(route('staff.store'), options);
    };
    return (
        <AuthenticatedLayout
            header={<h1 className="text-xl font-semibold">{t('Team')}</h1>}
        >
            <Head title={t('Team')} />
            <div className="mx-auto grid max-w-6xl gap-6 p-6 xl:grid-cols-[1fr_340px]">
                <section className="min-w-0">
                    <div className="mb-4">
                        <h2 className="font-semibold">{t('Support staff')}</h2>
                        <p className="mt-1 text-sm text-gray-500">
                            {t('Manage support staff and account access.')}
                        </p>
                    </div>
                    <div className="flex flex-col gap-3">
                        {staff.data.map((person) => (
                            <article
                                key={person.id}
                                className="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900"
                            >
                                <div className="min-w-0">
                                    <h3 className="font-medium">
                                        {person.name}
                                    </h3>
                                    <p className="break-all text-sm text-gray-500">
                                        {person.email}
                                    </p>
                                    <p className="mt-1 text-xs text-gray-500">
                                        {t(
                                            person.role === 'agent'
                                                ? 'Agent'
                                                : 'Admin',
                                        )}
                                        {person.tenant &&
                                            ` · ${person.tenant.name}`}
                                        {' · '}
                                        {t(
                                            person.is_active
                                                ? 'Active'
                                                : 'Inactive',
                                        )}
                                    </p>
                                </div>
                                <div className="flex gap-3">
                                    <button
                                        className="text-sm text-indigo-600 focus-visible:ring-2 dark:text-indigo-400"
                                        onClick={() => edit(person)}
                                    >
                                        {t('Edit')}
                                    </button>
                                    {person.is_active && (
                                        <button
                                            disabled={resending !== null}
                                            className="text-sm text-indigo-600 focus-visible:ring-2 disabled:opacity-50 dark:text-indigo-400"
                                            onClick={() =>
                                                router.post(
                                                    route(
                                                        'staff.setup',
                                                        person.id,
                                                    ),
                                                    {},
                                                    {
                                                        preserveScroll: true,
                                                        onStart: () =>
                                                            setResending(
                                                                person.id,
                                                            ),
                                                        onFinish: () =>
                                                            setResending(null),
                                                    },
                                                )
                                            }
                                        >
                                            {t('Send setup email')}
                                        </button>
                                    )}
                                </div>
                            </article>
                        ))}
                        {!staff.data.length && (
                            <p className="rounded-xl border border-dashed p-8 text-center text-sm text-gray-500">
                                {t('No support staff yet')}
                            </p>
                        )}
                    </div>
                    <div className="mt-4">
                        <Pagination links={staff.links} meta={staff} />
                    </div>
                </section>
                <form
                    onSubmit={submit}
                    className="flex h-fit flex-col gap-4 rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900"
                >
                    <h2 className="font-semibold">
                        {t(
                            editing
                                ? 'Edit staff account'
                                : 'Add staff account',
                        )}
                    </h2>
                    {[
                        ['name', 'Name'],
                        ['email', 'Email'],
                    ].map(([key, label]) => (
                        <label
                            key={key}
                            className="flex flex-col gap-1 text-sm"
                        >
                            {t(label)}
                            <input
                                required
                                type={key === 'email' ? 'email' : 'text'}
                                value={form.data[key]}
                                onChange={(e) =>
                                    form.setData(key, e.target.value)
                                }
                                className={field}
                            />
                            {form.errors[key] && (
                                <span
                                    role="alert"
                                    className="text-xs text-red-600"
                                >
                                    {t(form.errors[key])}
                                </span>
                            )}
                        </label>
                    ))}
                    {auth.capabilities.platform && (
                        <>
                            <label className="flex flex-col gap-1 text-sm">
                                {t('Role')}
                                <select
                                    className={field}
                                    value={form.data.role}
                                    onChange={(e) =>
                                        form.setData('role', e.target.value)
                                    }
                                >
                                    <option value="agent">{t('Agent')}</option>
                                    <option value="tenant_admin">
                                        {t('Admin')}
                                    </option>
                                    <option value="super_admin">
                                        {t('Platform admin')}
                                    </option>
                                </select>
                            </label>
                            {form.data.role !== 'super_admin' && (
                                <label className="flex flex-col gap-1 text-sm">
                                    {t('Tenant')}
                                    <select
                                        required
                                        className={field}
                                        value={form.data.tenant_id}
                                        onChange={(e) =>
                                            form.setData(
                                                'tenant_id',
                                                e.target.value
                                                    ? Number(e.target.value)
                                                    : '',
                                            )
                                        }
                                    >
                                        <option value="">
                                            {t('Choose a workspace')}
                                        </option>
                                        {tenants.map((tenant) => (
                                            <option
                                                key={tenant.id}
                                                value={tenant.id}
                                            >
                                                {tenant.name}
                                            </option>
                                        ))}
                                    </select>
                                    {form.errors.tenant_id && (
                                        <span className="text-xs text-red-600">
                                            {t(form.errors.tenant_id)}
                                        </span>
                                    )}
                                </label>
                            )}
                        </>
                    )}
                    {form.errors.role && (
                        <p role="alert" className="text-sm text-red-600">
                            {t(form.errors.role)}
                        </p>
                    )}
                    <label className="flex items-center gap-2 text-sm">
                        <input
                            type="checkbox"
                            checked={form.data.is_active}
                            onChange={(e) =>
                                form.setData('is_active', e.target.checked)
                            }
                            className="rounded border-gray-300 text-indigo-600"
                        />
                        {t('Active')}
                    </label>
                    <p className="text-xs leading-5 text-gray-500">
                        {t('New staff receive an email to set their password.')}
                    </p>
                    <button
                        disabled={form.processing}
                        className="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white focus-visible:ring-2 disabled:opacity-50"
                    >
                        {t(editing ? 'Save changes' : 'Create account')}
                    </button>
                    {editing && (
                        <button
                            type="button"
                            className="text-sm text-gray-500"
                            onClick={() => edit(null)}
                        >
                            {t('Cancel')}
                        </button>
                    )}
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
