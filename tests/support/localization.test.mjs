import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';
import { parseAst } from 'rolldown/parseAst';
import { setLocalization, t, currentLocale, formatNumber, formatDate } from '../../resources/js/localization.js';

const locales = ['en', 'fa', 'ar', 'it', 'es', 'tr'];
const english = JSON.parse(fs.readFileSync('lang/en.json', 'utf8'));
for (const locale of locales) {
    const translations = JSON.parse(fs.readFileSync(`lang/${locale}.json`, 'utf8'));
    assert.deepEqual(Object.keys(translations).sort(), Object.keys(english).sort(), `${locale}: complete catalog`);
    setLocalization({ locale, translations });
    assert.equal(currentLocale(), locale);
    assert.equal(t('Dashboard'), translations.Dashboard);
    assert.equal(t('Delete ":name"? This cannot be undone.', { name: '<script>example</script>' }), translations['Delete ":name"? This cannot be undone.'].replace(':name', '<script>example</script>'));
    assert.equal(t('Unknown :name', { name: 'test' }), 'Unknown test');
    assert.equal(formatNumber(12345), new Intl.NumberFormat(locale).format(12345));
    assert.equal(formatDate('2026-10-12T12:00:00Z', { dateStyle: 'long' }), new Intl.DateTimeFormat(locale, { dateStyle: 'long' }).format(new Date('2026-10-12T12:00:00Z')));
}
setLocalization({ translations: { 'Danger :name': ':name :other' } });
assert.equal(t('Danger :name', { name: ':other' }), ':other :other', 'replacement is single pass');
assert.equal(t('toString'), 'toString', 'prototype properties are not translations');
setLocalization();
assert.equal(t('Dashboard'), 'Dashboard');

// Check every static translation reference, including nested headers and form helper copy.
const files = fs.readdirSync('resources/js', { recursive: true }).filter((file) => /\.(jsx|js)$/.test(file));
for (const file of files) {
    const ast = parseAst(fs.readFileSync(`resources/js/${file}`, 'utf8'), { lang: file.endsWith('.jsx') ? 'jsx' : 'js' });
    function walk(node) {
        if (!node || typeof node !== 'object') return;
        if (node.type === 'CallExpression' && node.callee?.name === 't' && node.arguments[0]?.type === 'Literal') {
            assert.ok(Object.hasOwn(english, node.arguments[0].value), `${file}: catalog missing ${node.arguments[0].value}`);
        }
        if (node.type === 'JSXText') {
            assert.ok(!/[A-Za-z]/.test(node.value), `${file}: untranslated JSX text ${node.value.trim()}`);
        }
        for (const value of Object.values(node)) {
            if (Array.isArray(value)) value.forEach(walk);
            else if (value && typeof value === 'object') walk(value);
        }
    }
    walk(ast);
}

const widget = fs.readFileSync('public/widget.js', 'utf8');
const helpers = widget.match(/\/\* LOCALIZATION_HELPERS_START \*\/([\s\S]*?)\/\* LOCALIZATION_HELPERS_END \*/)[1];
const context = vm.createContext({});
vm.runInContext(helpers, context);
const translator = context.createWidgetLocalization();
assert.equal(translator.translate('Send'), 'Send');
assert.equal(translator.direction(), 'ltr');
for (const locale of locales) {
    translator.set({ locale, direction: ['fa', 'ar'].includes(locale) ? 'rtl' : 'ltr', translations: JSON.parse(fs.readFileSync(`lang/${locale}.json`, 'utf8')) });
    assert.equal(translator.locale(), locale);
    assert.equal(translator.direction(), ['fa', 'ar'].includes(locale) ? 'rtl' : 'ltr');
    assert.equal(translator.translate('Send'), JSON.parse(fs.readFileSync(`lang/${locale}.json`, 'utf8')).Send);
    assert.equal(translator.translate('custom visitor content'), 'custom visitor content');
}
console.log('Frontend localization and widget translation checks passed.');

// Exercise the actual widget locale update against minimal DOM controls.
const widgetAst = parseAst(widget, { lang: 'js' });
function widgetFunction(name) {
    let match;
    function visit(node) {
        if (!node || typeof node !== 'object') return;
        if (node.type === 'FunctionDeclaration' && node.id?.name === name) match = widget.slice(node.start, node.end);
        for (const value of Object.values(node)) {
            if (Array.isArray(value)) value.forEach(visit);
            else if (value && typeof value === 'object') visit(value);
        }
    }
    visit(widgetAst);
    assert.ok(match, `widget function ${name} exists`);
    return match;
}
let storedState;
const controls = {
    state: { locale: 'en' },
    localStorage: { setItem(_key, value) { storedState = JSON.parse(value); } },
    storageKey: 'test',
    isOpen: false,
    widgetLocalization: context.createWidgetLocalization(),
    languageSelect: { children: [], replaceChildren() { this.children = []; }, appendChild(option) { this.children.push(option); } },
    crmRoot: { querySelectorAll() { return []; } },
    document: { createElement() { return {}; } },
    button: { setAttribute(name, value) { this[name] = value; } },
};
controls.t = (key) => controls.widgetLocalization.translate(key);
const widgetContext = vm.createContext(controls);
vm.runInContext(widgetFunction('applyLocalization'), widgetContext);
const languageOptions = [{ code: 'en', native_name: 'English' }, { code: 'fa', native_name: 'فارسی' }, { code: 'ar', native_name: 'العربية' }];
widgetContext.applyLocalization({ locale: 'fa', direction: 'rtl', enabled_locales: ['en', 'fa'], available_languages: languageOptions, translations: { 'Open support chat': 'باز کردن گفتگو' } });
assert.equal(controls.crmRoot.dir, 'rtl');
assert.equal(controls.crmRoot.lang, 'fa');
assert.equal(storedState.locale, 'fa');
assert.equal(controls.languageSelect.value, 'fa');
assert.equal(controls.languageSelect.hidden, false);
assert.deepEqual(controls.languageSelect.children.map((option) => option.value), ['en', 'fa']);
assert.equal(controls.languageSelect.children[1].textContent, 'فارسی');
assert.equal(controls.button['aria-label'], 'باز کردن گفتگو');
widgetContext.applyLocalization({ locale: 'en', direction: 'ltr', enabled_locales: ['en'], available_languages: languageOptions, translations: {} });
assert.equal(controls.crmRoot.dir, 'ltr');
assert.equal(controls.languageSelect.hidden, true, 'single language widgets hide the switcher');
assert.equal(storedState.locale, 'en', 'resolved default replaces disabled saved preferences');

// A slower initial config request must not overwrite a visitor's newer choice.
const pending = [];
const raceContext = vm.createContext({
    botKey: 'test', apiBase: 'https://example.test/api', configRequestSequence: 0,
    fetch(url) { return new Promise((resolve) => pending.push({ url, resolve })); },
});
vm.runInContext(widgetFunction('loadRemoteConfig'), raceContext);
const older = raceContext.loadRemoteConfig('en');
const newer = raceContext.loadRemoteConfig('fa');
assert.ok(pending[1].url.endsWith('&locale=fa'));
pending[1].resolve({ ok: true, async json() { return { locale: 'fa' }; } });
assert.equal((await newer).locale, 'fa');
pending[0].resolve({ ok: true, async json() { return { locale: 'en' }; } });
assert.equal(await older, null);
console.log('Widget language switching, persistence and config race checks passed.');

controls.state = {};
widgetContext.state = controls.state;
widgetContext.applyLocalization({ locale: 'fa', direction: 'rtl', available_languages: languageOptions, translations: {} });
assert.equal(storedState.locale, undefined, 'automatic defaults are not saved as explicit visitor choices');
