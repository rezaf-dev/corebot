import { t } from '@/localization';
import { Link } from '@inertiajs/react';
import { useState } from 'react';

export default function WorkspaceOnboarding({ onboarding }) {
    const [expanded, setExpanded] = useState(false);
    if (!onboarding) return null;
    const nextStep = onboarding.steps.find((step) => !step.complete && step.available);

    return (
        <section aria-label={t('Workspace setup')} className="shrink-0 border-b border-indigo-200 bg-indigo-50 px-5 py-4 dark:border-indigo-900 dark:bg-indigo-950/40">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h3 className="text-sm font-semibold text-indigo-950 dark:text-indigo-100">{t('Get your assistant ready')}</h3>
                    <p className="mt-1 text-xs text-indigo-700 dark:text-indigo-300">{t(':completed of :total setup steps complete', { completed: onboarding.completed, total: onboarding.total })}</p>
                </div>
                <div className="flex items-center gap-4">
                    {nextStep && <Link href={nextStep.url} className="rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-500">{t(nextStep.title)}</Link>}
                    <button type="button" aria-expanded={expanded} aria-controls="workspace-setup-steps" onClick={() => setExpanded(!expanded)} className="text-sm font-medium text-indigo-700 dark:text-indigo-300">{t(expanded ? 'Hide guide' : 'View setup guide')}</button>
                </div>
            </div>
            <ol id="workspace-setup-steps" hidden={!expanded} className="mt-4 grid max-h-64 gap-3 overflow-y-auto sm:grid-cols-2 xl:grid-cols-4">
                {onboarding.steps.map((step, index) => (
                    <li key={step.key} className="rounded-lg border border-indigo-100 bg-white p-3 dark:border-indigo-900 dark:bg-gray-900">
                        <div className="text-sm font-semibold">{step.complete ? '✓' : `${index + 1}.`} {t(step.title)}</div>
                        <p className="mt-1 text-xs text-gray-600 dark:text-gray-400">{t(step.description)}</p>
                        {step.complete ? <span className="mt-2 inline-block text-xs text-emerald-700 dark:text-emerald-400">{t('Complete')}</span> : step.available ? <Link href={step.url} className="mt-2 inline-block text-xs font-semibold text-indigo-600 dark:text-indigo-400">{t('Continue')} →</Link> : <span className="mt-2 inline-block text-xs text-gray-500">{t('Connect AI and create a bot first')}</span>}
                    </li>
                ))}
            </ol>
        </section>
    );
}
