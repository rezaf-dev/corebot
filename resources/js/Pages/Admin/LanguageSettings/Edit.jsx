import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Checkbox from '@/Components/Checkbox';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import { t } from '@/localization';
import { Head, useForm } from '@inertiajs/react';

export default function Edit({ languageSettings, availableLanguages }) {
    const { data, setData, put, processing, errors, recentlySuccessful } = useForm(languageSettings);

    function toggleLanguage(code) {
        const enabled = data.enabled_locales.includes(code)
            ? data.enabled_locales.filter((locale) => locale !== code)
            : [...data.enabled_locales, code];

        setData({
            ...data,
            enabled_locales: enabled,
            default_locale: enabled.includes(data.default_locale) ? data.default_locale : (enabled[0] || ''),
        });
    }

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-gray-800 dark:text-gray-200">{t('Language settings')}</h2>}>
            <Head title={t('Language settings')} />
            <div className="mx-auto max-w-3xl p-6">
                <form onSubmit={(event) => { event.preventDefault(); put(route('language-settings.update')); }} className="space-y-6 rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                    <div>
                        <h3 className="text-lg font-medium">{t('Available languages')}</h3>
                        <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">{t('Choose the languages visitors and admins can use throughout Corebot.')}</p>
                        <div className="mt-4 grid gap-3 sm:grid-cols-2">
                            {availableLanguages.map((language) => (
                                <label key={language.code} className="flex items-center gap-3 rounded-md border border-gray-200 p-3 dark:border-gray-700">
                                    <Checkbox checked={data.enabled_locales.includes(language.code)} onChange={() => toggleLanguage(language.code)} />
                                    <span lang={language.code}>{language.native_name}</span>
                                    <span className="ms-auto text-xs text-gray-500 dark:text-gray-400">{language.direction === 'rtl' ? 'RTL' : 'LTR'}</span>
                                </label>
                            ))}
                        </div>
                        <InputError message={errors.enabled_locales || Object.entries(errors).find(([key]) => key.startsWith('enabled_locales.'))?.[1]} className="mt-2" />
                    </div>
                    <div>
                        <InputLabel htmlFor="default_locale" value={t('Default language')} />
                        <select id="default_locale" value={data.default_locale} onChange={(event) => setData('default_locale', event.target.value)} className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100">
                            {!data.enabled_locales.length && <option value="">{t('Select at least one language')}</option>}
                            {availableLanguages.filter((language) => data.enabled_locales.includes(language.code)).map((language) => <option key={language.code} value={language.code}>{language.native_name}</option>)}
                        </select>
                        <p className="mt-2 text-sm text-gray-600 dark:text-gray-400">{t('Used when someone has not selected a language. Persian and Arabic automatically use right-to-left layouts.')}</p>
                        <InputError message={errors.default_locale} className="mt-2" />
                    </div>
                    <div className="flex items-center gap-4">
                        <PrimaryButton disabled={processing || !data.enabled_locales.length}>{t('Save settings')}</PrimaryButton>
                        {recentlySuccessful && <span role="status" className="text-sm text-gray-600 dark:text-gray-400">{t('Saved.')}</span>}
                    </div>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
