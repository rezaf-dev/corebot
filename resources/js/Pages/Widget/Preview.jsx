import { t } from '@/localization';
import { loadPreviewWidget, openPreviewChat } from '@/widget-preview';
import { Head, Link } from '@inertiajs/react';
import { useEffect, useState } from 'react';

export default function Preview({ bot, widget_url, widget_config, widget_localization, contact_config, embedded, preview_host }) {
    const [device, setDevice] = useState('desktop');
    const [reload, setReload] = useState(0);
    const domainBlocked = bot.allowed_domains?.length > 0 && !bot.allowed_domains.includes(preview_host.toLowerCase());

    if (embedded) {
        return <DemoPage bot={bot} widgetUrl={widget_url} />;
    }

    return (
        <div className="min-h-screen bg-gray-100 text-gray-900 dark:bg-gray-950 dark:text-gray-100">
            <Head title={`${bot.name} — ${t('Widget demo')}`} />
            <header className="border-b border-gray-200 bg-white px-6 py-4 dark:border-gray-800 dark:bg-gray-900">
                <div className="mx-auto flex max-w-screen-2xl flex-wrap items-center justify-between gap-4">
                    <div>
                        <Link href={route('widget.install')} className="text-sm font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">← {t('Widget settings')}</Link>
                        <h1 className="mt-1 text-xl font-semibold">{bot.name} · {t('Live demo')}</h1>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        {['desktop', 'mobile'].map((option) => (
                            <button key={option} type="button" aria-pressed={device === option} onClick={() => setDevice(option)} className={`rounded-md px-4 py-2 text-sm font-medium ${device === option ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-200'}`}>
                                {t(option === 'desktop' ? 'Desktop' : 'Mobile')}
                            </button>
                        ))}
                        <button type="button" onClick={() => setReload((value) => value + 1)} className="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800">{t('Reload widget')}</button>
                    </div>
                </div>
            </header>

            <main className="mx-auto grid max-w-screen-2xl items-start gap-6 p-4 sm:p-6 xl:grid-cols-[minmax(0,1fr)_320px]">
                <section className="min-w-0 space-y-4">
                    <p className="text-sm text-gray-600 dark:text-gray-400">{t('This demo uses your saved settings. Conversations and contact details are recorded in your workspace.')}</p>
                    {domainBlocked && (
                        <div role="alert" className="rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-200">
                            {t('To chat on this demo, add :host to this bot’s allowed domains.', { host: preview_host })}{' '}
                            <Link href={route('bots.edit', bot.id)} className="font-semibold underline">{t('Edit bot')}</Link>
                        </div>
                    )}
                    <div className="overflow-auto rounded-xl border border-gray-200 bg-gray-200 p-2 sm:p-4 dark:border-gray-800 dark:bg-gray-900">
                        <iframe
                            key={reload}
                            title={t('Live widget demo')}
                            src={route('widget.preview', { bot: bot.id, embedded: 1 })}
                            className={`mx-auto block h-[760px] max-w-full rounded-lg border-0 bg-white shadow-sm ${device === 'mobile' ? 'w-[390px]' : 'w-full'}`}
                        />
                    </div>
                </section>

                <aside className="space-y-4">
                    <div className="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
                        <h2 className="font-semibold">{t('Saved configuration')}</h2>
                        <p className="mt-2 text-sm text-gray-500 dark:text-gray-400">{t('Change settings in the widget editor, save, then reopen this demo.')}</p>
                        <Configuration values={widget_config} />
                    </div>
                    <div className="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
                        <h2 className="font-semibold">{t('Languages')}</h2>
                        <Configuration values={{ default_locale: widget_localization.locale, enabled_locales: widget_localization.enabled_locales }} />
                        <h2 className="mt-5 font-semibold">{t('Contact collection')}</h2>
                        <Configuration values={contact_config} />
                    </div>
                </aside>
            </main>
        </div>
    );
}

function Configuration({ values }) {
    return (
        <dl className="mt-4 space-y-3 text-sm">
            {Object.entries(values).map(([key, value]) => (
                <div key={key} className="border-t border-gray-100 pt-3 dark:border-gray-800">
                    <dt className="font-medium text-gray-500 dark:text-gray-400">{t(key.replaceAll('_', ' ').replace(/^./, (letter) => letter.toUpperCase()))}</dt>
                    <dd className="mt-1 whitespace-pre-wrap break-words">
                        {typeof value === 'boolean' ? t(value ? 'Yes' : 'No') : Array.isArray(value) && !value.length ? t('None') : value === '' || value == null ? t('Default') : typeof value === 'object' ? JSON.stringify(value, null, 2) : String(value)}
                    </dd>
                </div>
            ))}
        </dl>
    );
}

function DemoPage({ bot, widgetUrl }) {
    const [status, setStatus] = useState('loading');

    useEffect(() => loadPreviewWidget(widgetUrl, setStatus), [widgetUrl]);

    return (
        <div className="min-h-screen bg-white text-slate-900">
            <Head title={`${bot.name} — ${t('Demo website')}`} />
            <nav className="flex items-center justify-between gap-4 border-b border-slate-100 px-6 py-5">
                <span className="font-semibold">{bot.name}</span>
                <span className="rounded-full bg-indigo-50 px-3 py-1 text-xs font-medium text-indigo-700">{t('Demo website')}</span>
            </nav>
            <main className="mx-auto max-w-4xl px-6 py-14 sm:px-10">
                <p className="text-sm font-semibold text-indigo-600">{t('Here to help')}</p>
                <h1 className="mt-4 max-w-xl text-4xl font-semibold leading-tight tracking-tight sm:text-5xl">{t('Help is one conversation away.')}</h1>
                <p className="mt-6 max-w-xl text-base leading-7 text-slate-500">{t('Explore this page just like a visitor would. Open the chat widget, ask a question, and see your assistant in action.')}</p>
                <button type="button" onClick={() => openPreviewChat()} disabled={status !== 'ready'} className="mt-8 rounded-lg bg-indigo-600 px-5 py-3 text-sm font-semibold text-white hover:bg-indigo-500 disabled:opacity-50">{t('Open chat')}</button>
                <p role={status === 'error' ? 'alert' : 'status'} className="mt-3 text-sm text-slate-500">
                    {t(status === 'loading' ? 'Loading widget…' : status === 'error' ? 'The widget could not load. Reload the demo to try again.' : 'Widget loaded. Try a conversation.')}
                </p>
                <div className="mt-14 grid gap-4 sm:grid-cols-3">
                    {[
                        ['Ask a question', 'Chat with your bot using its knowledge and configured responses.'],
                        ['Try your prompts', 'Use the suggested questions and language options inside the widget.'],
                        ['Share your details', 'Test contact collection just as your visitors will experience it.'],
                    ].map(([title, description]) => (
                        <section key={title} className="rounded-xl border border-slate-200 bg-slate-50 p-5">
                            <h2 className="text-sm font-semibold">{t(title)}</h2>
                            <p className="mt-3 text-sm leading-6 text-slate-500">{t(description)}</p>
                        </section>
                    ))}
                </div>
            </main>
        </div>
    );
}
