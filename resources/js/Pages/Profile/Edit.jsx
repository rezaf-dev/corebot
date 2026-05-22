import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, usePage } from '@inertiajs/react';
import DeleteUserForm from './Partials/DeleteUserForm';
import UpdatePasswordForm from './Partials/UpdatePasswordForm';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm';

export default function Edit({ mustVerifyEmail, status }) {
    const user = usePage().props.auth.user;

    return (
        <AuthenticatedLayout
            header={
                <div>
                    <h2 className="text-xl font-semibold text-gray-800 dark:text-gray-200">Profile</h2>
                    <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Manage your account details, password, and security settings.
                    </p>
                </div>
            }
        >
            <Head title="Profile" />

            <div className="mx-auto max-w-3xl space-y-6 p-6">
                <AccountOverview user={user} />

                <ProfileSection
                    title="Profile information"
                    description="Update your name and email address used to sign in."
                >
                    <UpdateProfileInformationForm mustVerifyEmail={mustVerifyEmail} status={status} />
                </ProfileSection>

                <ProfileSection
                    title="Password"
                    description="Use a strong, unique password to protect your account."
                >
                    <UpdatePasswordForm />
                </ProfileSection>

                <ProfileSection
                    title="Delete account"
                    description="Permanently remove your account and sign out of all devices."
                    variant="danger"
                >
                    <DeleteUserForm />
                </ProfileSection>
            </div>
        </AuthenticatedLayout>
    );
}

function AccountOverview({ user }) {
    const roleLabels = {
        super_admin: 'Super admin',
        tenant_admin: 'Tenant admin',
        agent: 'Agent',
    };

    return (
        <section className="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
            <div className="flex flex-wrap items-start gap-4">
                <div
                    className="flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-lg font-semibold text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300"
                    aria-hidden="true"
                >
                    {initials(user.name)}
                </div>
                <div className="min-w-0 flex-1">
                    <h3 className="text-lg font-semibold text-gray-900 dark:text-white">{user.name}</h3>
                    <p className="mt-0.5 text-sm text-gray-600 dark:text-gray-300">{user.email}</p>
                    <div className="mt-3 flex flex-wrap gap-2">
                        <Badge label={roleLabels[user.role] || user.role} />
                        {user.tenant?.name && <Badge label={user.tenant.name} muted />}
                        {user.email_verified_at ? (
                            <Badge label="Email verified" success />
                        ) : (
                            <Badge label="Email not verified" warning />
                        )}
                    </div>
                </div>
            </div>
            {user.created_at && (
                <p className="mt-4 border-t border-gray-100 pt-4 text-xs text-gray-500 dark:border-gray-700 dark:text-gray-400">
                    Member since {formatDate(user.created_at)}
                </p>
            )}
        </section>
    );
}

function ProfileSection({ title, description, children, variant = 'default' }) {
    const isDanger = variant === 'danger';

    return (
        <section
            className={`rounded-lg p-6 shadow dark:bg-gray-800 ${
                isDanger
                    ? 'border border-red-200 bg-red-50/50 dark:border-red-900/50 dark:bg-red-950/20'
                    : 'bg-white'
            }`}
        >
            <header className={isDanger ? 'border-b border-red-200 pb-4 dark:border-red-900/50' : 'border-b border-gray-100 pb-4 dark:border-gray-700'}>
                <h3 className={`text-base font-semibold ${isDanger ? 'text-red-900 dark:text-red-200' : 'text-gray-900 dark:text-white'}`}>
                    {title}
                </h3>
                <p className={`mt-1 text-sm ${isDanger ? 'text-red-700/80 dark:text-red-300/80' : 'text-gray-500 dark:text-gray-400'}`}>
                    {description}
                </p>
            </header>
            <div className="pt-6">{children}</div>
        </section>
    );
}

function Badge({ label, muted = false, success = false, warning = false }) {
    let classes = 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300';

    if (success) {
        classes = 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300';
    } else if (warning) {
        classes = 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300';
    } else if (muted) {
        classes = 'bg-indigo-50 text-indigo-800 dark:bg-indigo-950/40 dark:text-indigo-300';
    }

    return (
        <span className={`inline-flex rounded-full px-2.5 py-1 text-xs font-medium ${classes}`}>
            {label}
        </span>
    );
}

function initials(name) {
    return (name || '?')
        .split(' ')
        .map((part) => part[0])
        .join('')
        .slice(0, 2)
        .toUpperCase();
}

function formatDate(value) {
    try {
        return new Intl.DateTimeFormat(undefined, { dateStyle: 'long' }).format(new Date(value));
    } catch {
        return value;
    }
}
