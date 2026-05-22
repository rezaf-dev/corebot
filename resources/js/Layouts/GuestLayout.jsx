import ApplicationLogo from '@/Components/ApplicationLogo';
import { Link } from '@inertiajs/react';

export default function GuestLayout({ children }) {
    return (
        <div className="flex min-h-screen flex-col items-center bg-gray-100 pt-6 text-gray-900 sm:justify-center sm:pt-0 dark:bg-gray-900 dark:text-gray-100">
            <div>
                <Link
                    href="/"
                    className="inline-flex items-center justify-center"
                    aria-label="corebot home"
                >
                    <ApplicationLogo markClassName="h-20 w-20" />
                </Link>
            </div>

            <div className="mt-6 w-full overflow-hidden bg-white px-6 py-4 text-gray-900 shadow-md sm:max-w-md sm:rounded-lg dark:bg-gray-800 dark:text-gray-100">
                {children}
            </div>
        </div>
    );
}
