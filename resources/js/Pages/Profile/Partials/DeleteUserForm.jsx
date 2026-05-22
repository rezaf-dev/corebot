import DangerButton from '@/Components/DangerButton';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import Modal from '@/Components/Modal';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import { useForm } from '@inertiajs/react';
import { useRef, useState } from 'react';

export default function DeleteUserForm() {
    const [confirmingUserDeletion, setConfirmingUserDeletion] = useState(false);
    const passwordInput = useRef();

    const { data, setData, delete: destroy, processing, reset, errors, clearErrors } = useForm({
        password: '',
    });

    const confirmUserDeletion = () => {
        setConfirmingUserDeletion(true);
    };

    const deleteUser = (e) => {
        e.preventDefault();

        destroy(route('profile.destroy'), {
            preserveScroll: true,
            onSuccess: () => closeModal(),
            onError: () => passwordInput.current?.focus(),
            onFinish: () => reset(),
        });
    };

    const closeModal = () => {
        setConfirmingUserDeletion(false);
        clearErrors();
        reset();
    };

    return (
        <div className="space-y-4">
            <p className="text-sm text-red-800/90 dark:text-red-300/90">
                This action cannot be undone. All conversations, bots, and knowledge tied to your account may become
                inaccessible depending on your role.
            </p>

            <DangerButton type="button" onClick={confirmUserDeletion} className="!normal-case !tracking-normal">
                Delete my account
            </DangerButton>

            <Modal show={confirmingUserDeletion} onClose={closeModal} maxWidth="md">
                <form onSubmit={deleteUser} className="p-6">
                    <div className="flex h-10 w-10 items-center justify-center rounded-full bg-red-100 dark:bg-red-950/60">
                        <svg className="h-5 w-5 text-red-600 dark:text-red-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path
                                fillRule="evenodd"
                                d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 6a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 6zm0 8a1 1 0 100-2 1 1 0 000 2z"
                                clipRule="evenodd"
                            />
                        </svg>
                    </div>

                    <h2 className="mt-4 text-lg font-semibold text-gray-900 dark:text-white">Delete your account?</h2>

                    <p className="mt-2 text-sm text-gray-600 dark:text-gray-400">
                        Enter your password to confirm. You will be signed out immediately and your account will be
                        permanently removed.
                    </p>

                    <div className="mt-5">
                        <InputLabel htmlFor="delete_password" value="Password" />
                        <TextInput
                            id="delete_password"
                            type="password"
                            name="password"
                            ref={passwordInput}
                            value={data.password}
                            onChange={(e) => setData('password', e.target.value)}
                            className="mt-1 block w-full"
                            isFocused
                            autoComplete="current-password"
                            placeholder="Your current password"
                        />
                        <InputError message={errors.password} className="mt-1" />
                    </div>

                    <div className="mt-6 flex flex-wrap justify-end gap-3">
                        <SecondaryButton type="button" onClick={closeModal} className="!normal-case !tracking-normal">
                            Cancel
                        </SecondaryButton>
                        <DangerButton disabled={processing} className="!normal-case !tracking-normal">
                            {processing ? 'Deleting…' : 'Yes, delete account'}
                        </DangerButton>
                    </div>
                </form>
            </Modal>
        </div>
    );
}
