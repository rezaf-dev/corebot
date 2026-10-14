import { t } from '@/localization';
import { Link } from '@inertiajs/react';

export default function WorkspaceNavigation({ capabilities = {}, onNavigate }) {
    const groups = [
        { label: null, links: [['conversations.index', 'Conversations', '◫', 'conversations.*']] },
    ];
    if (capabilities.configure) {
        groups.push({ label: 'Settings', links: [
            ['settings.index', 'Overview', '⚙', 'settings.*'],
            ['bots.index', 'Bots', '◇', 'bots.*'],
            ['knowledge-sources.index', 'Knowledge', '▤', 'knowledge-sources.*'],
            ['widget.install', 'Widget', '▣', 'widget.*'],
            ...(!capabilities.platform ? [['ai-settings.edit', 'AI Settings', '✦', 'ai-settings.*']] : []),
            ['dashboard', 'Analytics', '▥', 'dashboard'],
            ...(!capabilities.platform ? [['staff.index', 'Team', '♙', 'staff.*']] : []),
        ] });
    }
    if (capabilities.platform) {
        groups.push({ label: 'Admin', links: [
            ['admin.index', 'Overview', '⚙', 'admin.*'],
            ['tenants.index', 'Tenants', '▦', 'tenants.*'],
            ['staff.index', 'Team', '♙', 'staff.*'],
            ['language-settings.edit', 'Language settings', '◎', 'language-settings.*'],
        ] });
    }

    return (
        <nav aria-label={t('Main navigation')} className="min-h-0 flex-1 space-y-5 overflow-y-auto">
            {groups.map((group, index) => (
                <div key={group.label || index} className="space-y-1">
                    {group.label && <p className="px-3 text-xs font-semibold uppercase tracking-wide text-gray-400">{t(group.label)}</p>}
                    {group.links.map(([name, label, icon, pattern]) => {
                        const active = route().current(pattern) || (capabilities.platform && name === 'settings.index' && route().current('ai-settings.*'));
                        return (
                            <Link key={name} href={route(name)} aria-current={active ? 'page' : undefined} onClick={onNavigate}
                                className={`flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium focus-visible:ring-2 focus-visible:ring-indigo-500 ${active ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300' : 'text-gray-600 hover:bg-gray-50 dark:text-gray-400 dark:hover:bg-gray-800'}`}>
                                <span aria-hidden="true" className="w-4 shrink-0 text-center">{icon}</span>
                                {t(label)}
                            </Link>
                        );
                    })}
                </div>
            ))}
        </nav>
    );
}
