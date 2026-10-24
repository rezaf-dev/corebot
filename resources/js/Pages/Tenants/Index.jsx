import { t } from '@/localization';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import DangerButton from '@/Components/DangerButton';
import InputError from '@/Components/InputError';
import Modal from '@/Components/Modal';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import { DialogTitle } from '@headlessui/react';
import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';

function TenantActions({ tenant }) {
    const [action, setAction] = useState(null);
    const form = useForm({ name: tenant.name, status: tenant.status });
    const open = (nextAction) => {
        form.clearErrors();
        form.setData({ name: tenant.name, status: nextAction === 'toggle' ? (tenant.status === 'active' ? 'inactive' : 'active') : tenant.status });
        setAction(nextAction);
    };
    const close = () => { if (!form.processing) setAction(null); };
    const submit = (event) => {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: () => setAction(null) };
        if (action === 'delete') {
            form.delete(route('tenants.destroy', tenant.id), options);
        } else {
            form.put(route('tenants.update', tenant.id), options);
        }
    };
    const toggleLabel = tenant.status === 'active' ? t('Disable tenant') : t('Enable tenant');

    return (
        <>
            <div className="flex flex-wrap gap-3">
                <button type="button" onClick={() => open('edit')} className="text-indigo-600 focus-visible:ring-2 dark:text-indigo-400" aria-label={t('Edit tenant :name', { name: tenant.name })}>{t('Edit')}</button>
                <button type="button" onClick={() => open('toggle')} className="text-indigo-600 focus-visible:ring-2 dark:text-indigo-400" aria-label={t(':action: :name', { action: toggleLabel, name: tenant.name })}>{toggleLabel}</button>
                <button type="button" onClick={() => open('delete')} className="text-red-600 focus-visible:ring-2 dark:text-red-400" aria-label={t('Delete tenant :name', { name: tenant.name })}>{t('Delete')}</button>
            </div>
            <Modal show={action !== null} onClose={close} closeable={!form.processing} maxWidth="md">
                <form onSubmit={submit} className="flex flex-col gap-5 overflow-y-auto p-6">
                    <DialogTitle className="text-lg font-semibold text-gray-900 dark:text-white">{action === 'delete' ? t('Delete tenant') : action === 'toggle' ? toggleLabel : t('Edit tenant')}</DialogTitle>
                    {action === 'edit' ? (
                        <>
                            <label className="flex flex-col gap-1 text-sm text-gray-700 dark:text-gray-300">
                                {t('Name')}
                                <input required maxLength={255} value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} className="rounded border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100" />
                                <InputError message={form.errors.name} />
                            </label>
                            <label className="flex flex-col gap-1 text-sm text-gray-700 dark:text-gray-300">
                                {t('Status')}
                                <select value={form.data.status} onChange={(event) => form.setData('status', event.target.value)} className="rounded border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100">
                                    <option value="active">{t('Active')}</option>
                                    <option value="inactive">{t('Inactive')}</option>
                                </select>
                                <InputError message={form.errors.status} />
                            </label>
                            <p className="text-sm text-gray-600 dark:text-gray-400">{t('Disabling a tenant blocks staff access and public chat. Its data is preserved.')}</p>
                        </>
                    ) : (
                        <>
                            <p className="break-words font-medium text-gray-900 dark:text-white">{tenant.name}</p>
                            <p className="text-sm text-gray-600 dark:text-gray-400">{action === 'delete' ? t('This permanently deletes the tenant, its bots, conversations, and knowledge. Staff accounts are deactivated. This cannot be undone.') : tenant.status === 'active' ? t('Disabling a tenant blocks staff access and public chat. Its data is preserved.') : t('Enable this tenant to restore staff access and public chat.')}</p>
                            {Object.entries(form.errors).map(([key, message]) => <InputError key={key} message={message} />)}
                        </>
                    )}
                    <div className="flex flex-wrap justify-end gap-3">
                        <SecondaryButton onClick={close} disabled={form.processing}>{t('Cancel')}</SecondaryButton>
                        {action === 'delete' ? <DangerButton disabled={form.processing}>{t('Delete tenant')}</DangerButton> : <PrimaryButton disabled={form.processing}>{action === 'toggle' ? toggleLabel : t('Save changes')}</PrimaryButton>}
                    </div>
                </form>
            </Modal>
        </>
    );
}

export default function Index({ tenants }) {
    const { data, setData, post, processing, errors, reset } = useForm({ name: '', admin_name: '', admin_email: '', admin_password: '' });
    const submit = (e) => { e.preventDefault(); post(route('tenants.store'), { onSuccess: () => reset() }); };

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-gray-800 dark:text-gray-200">{t("Tenants")}</h2>}>
            <Head title={t("Tenants")} />
            <div className="mx-auto grid max-w-7xl gap-6 p-6 lg:grid-cols-[360px_1fr]">
                <form onSubmit={submit} className="rounded-lg bg-white p-5 shadow dark:bg-gray-800">
                    <h3 className="font-semibold text-gray-900 dark:text-white">{t("Create tenant")}</h3>
                    {['name', 'admin_name', 'admin_email', 'admin_password'].map((field) => (
                        <label key={field} className="mt-4 block text-sm text-gray-700 dark:text-gray-300">
                            {field.replace('_', ' ')}
                            <input type={field.includes('password') ? 'password' : 'text'} value={data[field]} onChange={(e) => setData(field, e.target.value)} className="mt-1 w-full rounded border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100" />
                            {errors[field] && <span className="text-xs text-red-600 dark:text-red-400">{errors[field]}</span>}
                        </label>
                    ))}
                    <button disabled={processing} className="mt-5 rounded bg-gray-900 px-4 py-2 text-sm text-white">{t("Create")}</button>
                </form>
                <div className="min-w-0 overflow-x-auto rounded-lg bg-white p-5 shadow dark:bg-gray-800">
                    <table className="w-full text-start text-sm">
                        <thead className="text-gray-500 dark:text-gray-400"><tr><th>{t("Name")}</th><th>{t("Status")}</th><th>{t("Users")}</th><th>{t("Bots")}</th><th>{t('Actions')}</th></tr></thead>
                        <tbody className="divide-y dark:divide-gray-700">{tenants.map((tenant) => <tr key={tenant.id}><td className="max-w-xs break-words py-4 pe-4">{tenant.name}</td><td className="pe-4">{t(tenant.status === 'active' ? 'Active' : 'Inactive')}</td><td className="pe-4">{tenant.users_count}</td><td className="pe-4">{tenant.bots_count}</td><td><TenantActions tenant={tenant} /></td></tr>)}</tbody>
                    </table>
                    {!tenants.length && <p className="py-6 text-center text-sm text-gray-500 dark:text-gray-400">{t('No tenants yet.')}</p>}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
