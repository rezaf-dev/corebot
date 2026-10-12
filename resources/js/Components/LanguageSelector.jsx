import { t } from '@/localization';
import { router, usePage } from '@inertiajs/react';
import { useId, useState } from 'react';

export default function LanguageSelector() {
    const { localization } = usePage().props;
    const id = useId();
    const [processing, setProcessing] = useState(false);

    if (!localization || localization.enabled_locales.length < 2) {
        return null;
    }

    return (
        <label htmlFor={id} className="inline-flex items-center gap-2 text-sm">
            <span className="sr-only">{t('Language')}</span>
            <select
                id={id}
                value={localization.locale}
                disabled={processing}
                onChange={(event) => router.post(route('locale.update'), { locale: event.target.value }, {
                    preserveScroll: true,
                    preserveState: true,
                    onStart: () => setProcessing(true),
                    onFinish: () => setProcessing(false),
                })}
                className="max-w-32 rounded-md border-gray-300 bg-white py-1.5 text-sm text-gray-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
            >
                {localization.available_languages.filter((language) => localization.enabled_locales.includes(language.code)).map((language) => (
                    <option key={language.code} value={language.code} lang={language.code}>
                        {language.native_name}
                    </option>
                ))}
            </select>
        </label>
    );
}
