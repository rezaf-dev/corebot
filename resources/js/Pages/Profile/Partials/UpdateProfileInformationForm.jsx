import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { Transition } from '@headlessui/react';
import { Link, useForm, usePage } from '@inertiajs/react';

export default function UpdateProfileInformation({ mustVerifyEmail, status }) {
    const user = usePage().props.auth.user;

    const { data, setData, patch, errors, processing, recentlySuccessful } = useForm({
        name: user.name,
        email: user.email,
    });

    const submit = (e) => {
        e.preventDefault();
        patch(route('profile.update'), { preserveScroll: true });
    };

    return (
        <form onSubmit={submit} className="space-y-5">
            <div>
                <InputLabel htmlFor="name" value="Name" />
                <TextInput
                    id="name"
                    className="mt-1 block w-full"
                    value={data.name}
                    onChange={(e) => setData('name', e.target.value)}
                    required
                    isFocused
                    autoComplete="name"
                />
                <InputError className="mt-1" message={errors.name} />
            </div>

            <div>
                <InputLabel htmlFor="email" value="Email" />
                <TextInput
                    id="email"
                    type="email"
                    className="mt-1 block w-full"
                    value={data.email}
                    onChange={(e) => setData('email', e.target.value)}
                    required
                    autoComplete="username"
                />
                <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    Changing your email will require verification again.
                </p>
                <InputError className="mt-1" message={errors.email} />
            </div>

            {mustVerifyEmail && user.email_verified_at === null && (
                <div className="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 dark:border-amber-900/50 dark:bg-amber-950/30">
                    <p className="text-sm font-medium text-amber-900 dark:text-amber-200">Email not verified</p>
                    <p className="mt-1 text-sm text-amber-800/90 dark:text-amber-300/90">
                        Check your inbox for the verification link, or{' '}
                        <Link
                            href={route('verification.send')}
                            method="post"
                            as="button"
                            className="font-medium underline hover:text-amber-950 dark:hover:text-amber-100"
                        >
                            resend the verification email
                        </Link>
                        .
                    </p>
                    {status === 'verification-link-sent' && (
                        <p className="mt-2 text-sm font-medium text-emerald-700 dark:text-emerald-300">
                            A new verification link has been sent.
                        </p>
                    )}
                </div>
            )}

            <div className="flex flex-wrap items-center gap-3">
                <PrimaryButton disabled={processing} className="!normal-case !tracking-normal">
                    {processing ? 'Saving…' : 'Save changes'}
                </PrimaryButton>
                <Transition
                    show={recentlySuccessful}
                    enter="transition ease-out duration-200"
                    enterFrom="opacity-0"
                    enterTo="opacity-100"
                    leave="transition ease-in duration-150"
                    leaveFrom="opacity-100"
                    leaveTo="opacity-0"
                >
                    <span className="text-sm text-emerald-600 dark:text-emerald-400">Saved.</span>
                </Transition>
            </div>
        </form>
    );
}
