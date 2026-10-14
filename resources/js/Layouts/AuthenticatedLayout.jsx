import { t } from '@/localization';
import WorkspaceOnboarding from '@/Components/WorkspaceOnboarding';
import LanguageSelector from '@/Components/LanguageSelector';
import ApplicationLogo from '@/Components/ApplicationLogo';
import Dropdown from '@/Components/Dropdown';
import { Link, usePage } from '@inertiajs/react';
import { useState } from 'react';

export default function AuthenticatedLayout({
    header,
    children,
    workspace = false,
}) {
    const { auth, flash = {}, onboarding } = usePage().props;
    const [menuOpen, setMenuOpen] = useState(false);
    const links = [['conversations.index', 'Conversations', '◫']];
    if (auth.capabilities?.configure)
        links.push(['settings.index', 'Settings', '⚙']);
    if (auth.capabilities?.platform) links.push(['admin.index', 'Admin', '◇']);
    const active = (name) =>
        name === 'conversations.index'
            ? route().current('conversations.*')
            : name === 'settings.index'
              ? [
                    'settings.*',
                    'bots.*',
                    'knowledge-sources.*',
                    'ai-settings.*',
                    'widget.*',
                    'dashboard',
                    ...(!auth.capabilities?.platform ? ['staff.*'] : []),
                ].some((pattern) => route().current(pattern))
              : [
                    'admin.*',
                    'tenants.*',
                    'language-settings.*',
                    ...(auth.capabilities?.platform ? ['staff.*'] : []),
                ].some((pattern) => route().current(pattern));
    return (
        <div
            className={`bg-gray-50 text-gray-900 dark:bg-gray-950 dark:text-gray-100 ${workspace ? 'flex h-dvh flex-col overflow-hidden' : 'min-h-screen'}`}
        >
            <header className="z-30 flex h-16 shrink-0 items-center justify-between gap-3 border-b border-gray-200 bg-white px-4 dark:border-gray-800 dark:bg-gray-900">
                <div className="flex items-center gap-3">
                    <button
                        type="button"
                        aria-label={t('Toggle navigation')}
                        aria-expanded={menuOpen}
                        onClick={() => setMenuOpen(!menuOpen)}
                        className="rounded-lg p-2 focus-visible:ring-2 focus-visible:ring-indigo-500 md:hidden"
                    >
                        ☰
                    </button>
                    <Link
                        href={route('conversations.index')}
                        aria-label={t('Conversations')}
                    >
                        <ApplicationLogo
                            showWordmark
                            markClassName="h-8 w-8"
                            wordmarkClassName="text-lg"
                        />
                    </Link>
                    <span className="hidden border-s border-gray-200 ps-4 text-sm text-gray-500 dark:border-gray-700 sm:block">
                        {auth.user.tenant?.name || t('Admin')}
                    </span>
                </div>
                <div className="flex items-center gap-2">
                    <LanguageSelector />
                    <Dropdown>
                        <Dropdown.Trigger>
                            <button className="flex items-center gap-2 rounded-lg px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-indigo-500">
                                <span className="flex h-7 w-7 items-center justify-center rounded-full bg-indigo-100 font-semibold text-indigo-700 dark:bg-indigo-900 dark:text-indigo-200">
                                    {auth.user.name.slice(0, 1)}
                                </span>
                                <span className="hidden sm:block">
                                    {auth.user.name}
                                </span>
                                <span aria-hidden="true">⌄</span>
                            </button>
                        </Dropdown.Trigger>
                        <Dropdown.Content>
                            <Dropdown.Link href={route('profile.edit')}>
                                {t('Profile')}
                            </Dropdown.Link>
                            <Dropdown.Link
                                href={route('logout')}
                                method="post"
                                as="button"
                            >
                                {t('Log Out')}
                            </Dropdown.Link>
                        </Dropdown.Content>
                    </Dropdown>
                </div>
            </header>
            <div className={`flex ${workspace ? 'min-h-0 flex-1' : ''}`}>
                {menuOpen && (
                    <button
                        aria-label={t('Close navigation')}
                        className="fixed inset-0 z-30 bg-black/30 md:hidden"
                        onClick={() => setMenuOpen(false)}
                    />
                )}
                <aside
                    className={`${menuOpen ? 'flex' : 'hidden'} fixed bottom-0 start-0 top-16 z-40 w-40 flex-col border-e border-gray-200 bg-white p-3 dark:border-gray-800 dark:bg-gray-900 md:sticky md:top-0 md:flex ${workspace ? 'md:h-full' : 'md:min-h-[calc(100dvh-4rem)]'}`}
                >
                    <nav
                        aria-label={t('Main navigation')}
                        className="flex flex-col gap-1"
                    >
                        {links.map(([name, label, icon]) => (
                            <Link
                                key={name}
                                href={route(name)}
                                aria-current={active(name) ? 'page' : undefined}
                                onClick={() => setMenuOpen(false)}
                                className={`flex items-center gap-2 rounded-lg px-3 py-3 text-sm font-medium focus-visible:ring-2 focus-visible:ring-indigo-500 ${active(name) ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300' : 'text-gray-600 hover:bg-gray-50 dark:text-gray-400 dark:hover:bg-gray-800'}`}
                            >
                                <span aria-hidden="true">{icon}</span>
                                {t(label)}
                            </Link>
                        ))}
                    </nav>
                    <div className="mt-auto border-t border-gray-100 px-3 pt-4 text-xs text-gray-500 dark:border-gray-800">
                        {t(auth.user.role === 'agent' ? 'Agent' : 'Admin')}
                    </div>
                </aside>
                <main
                    className={`min-w-0 flex-1 ${workspace ? 'flex min-h-0 flex-col' : ''}`}
                >
                    {header && (
                        <div className="border-b border-gray-200 bg-white px-6 py-5 dark:border-gray-800 dark:bg-gray-900">
                            {header}
                        </div>
                    )}
                    {(flash.success || flash.error) && (
                        <div
                            role={flash.error ? 'alert' : 'status'}
                            className={`shrink-0 border-b px-5 py-3 text-sm ${flash.error ? 'border-red-200 bg-red-50 text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200' : 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200'}`}
                        >
                            {t(flash.error || flash.success)}
                        </div>
                    )}
                    <WorkspaceOnboarding onboarding={onboarding} />
                    {children}
                </main>
            </div>
        </div>
    );
}
