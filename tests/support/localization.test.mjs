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
