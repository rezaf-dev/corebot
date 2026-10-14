import { t } from '@/localization';
import { loadPreviewWidget, openPreviewChat } from '@/widget-preview';
import { Head } from '@inertiajs/react';
import { useEffect, useState } from 'react';

export default function Preview({ bot, widget_url: widgetUrl }) {
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
