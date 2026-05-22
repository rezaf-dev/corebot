import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';

export default function Index({ tenants }) {
    const { data, setData, post, processing, errors, reset } = useForm({ name: '', admin_name: '', admin_email: '', admin_password: '' });
    const submit = (e) => { e.preventDefault(); post(route('tenants.store'), { onSuccess: () => reset() }); };

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-gray-800 dark:text-gray-200">Tenants</h2>}>
            <Head title="Tenants" />
            <div className="mx-auto grid max-w-7xl gap-6 p-6 lg:grid-cols-[360px_1fr]">
                <form onSubmit={submit} className="rounded-lg bg-white p-5 shadow dark:bg-gray-800">
                    <h3 className="font-semibold text-gray-900 dark:text-white">Create tenant</h3>
                    {['name', 'admin_name', 'admin_email', 'admin_password'].map((field) => (
                        <label key={field} className="mt-4 block text-sm text-gray-700 dark:text-gray-300">
                            {field.replace('_', ' ')}
                            <input type={field.includes('password') ? 'password' : 'text'} value={data[field]} onChange={(e) => setData(field, e.target.value)} className="mt-1 w-full rounded border-gray-300 dark:border-gray-700 dark:bg-gray-900" />
                            {errors[field] && <span className="text-xs text-red-600">{errors[field]}</span>}
                        </label>
                    ))}
                    <button disabled={processing} className="mt-5 rounded bg-gray-900 px-4 py-2 text-sm text-white">Create</button>
                </form>
                <div className="rounded-lg bg-white p-5 shadow dark:bg-gray-800">
                    <table className="w-full text-left text-sm">
                        <thead className="text-gray-500"><tr><th>Name</th><th>Status</th><th>Admins</th><th>Bots</th></tr></thead>
                        <tbody className="divide-y dark:divide-gray-700">{tenants.map((tenant) => <tr key={tenant.id}><td className="py-2">{tenant.name}</td><td>{tenant.status}</td><td>{tenant.users_count}</td><td>{tenant.bots_count}</td></tr>)}</tbody>
                    </table>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
