let current = { locale: 'en', direction: 'ltr', translations: {} };

export function setLocalization(localization = {}) {
    current = { locale: 'en', direction: 'ltr', translations: {}, ...localization };
}

export function t(key, replacements = {}) {
    const translated = Object.hasOwn(current.translations, key) ? current.translations[key] : key;

    return String(translated).replace(/:([a-zA-Z_]+)/g, (match, name) =>
        Object.hasOwn(replacements, name) ? String(replacements[name]) : match,
    );
}

export function formatNumber(value, options = {}) {
    return new Intl.NumberFormat(current.locale, options).format(value);
}

export function formatDate(value, options = {}) {
    return new Intl.DateTimeFormat(current.locale, options).format(new Date(value));
}

export function currentLocale() {
    return current.locale;
}
