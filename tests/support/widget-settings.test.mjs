import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';
import React from 'react';
import { renderToString } from 'react-dom/server';
import { useForm } from '@inertiajs/react';
import { parseAst } from 'rolldown/parseAst';

// Get the real installed React form API without adding a DOM test dependency.
let form;
function FormProbe() {
    form = useForm({ title: 'Support' });
    return null;
}
renderToString(React.createElement(FormProbe));

// Execute the page's actual bot-selection effect against that API.
const source = fs.readFileSync('resources/js/Pages/Widget/Install.jsx', 'utf8');
const ast = parseAst(source, { lang: 'jsx' });
const page = ast.body.find((node) => node.type === 'ExportDefaultDeclaration').declaration;
const statements = page.body.body;
const formDeclaration = statements.flatMap((node) => node.declarations || [])
    .find((node) => node.init?.callee?.name === 'useForm');
const effect = statements.find((node) => node.expression?.callee?.name === 'useEffect').expression.arguments[0];
const calls = {};
const context = vm.createContext({
    defaults: { title: 'Support', primary_color: '#111827', default_locale: '' },
    language_settings: { default_locale: 'en' },
    selectedBot: { widget_config: { title: 'First bot', default_locale: 'fa' } },
    setEditingLocale: (locale) => { calls.locale = locale; },
    setStarterLocale: (locale) => { calls.starterLocale = locale; },
});
for (const property of formDeclaration.id.properties) {
    const method = form[property.key.name];
    context[property.value.name] = typeof method === 'function'
        ? (...args) => { calls[property.key.name] = args; return method(...args); }
        : method;
}
const syncBot = vm.runInContext(`(${source.slice(effect.start, effect.end)})`, context);
syncBot();
assert.equal(calls.setDefaults[0].title, 'First bot');
assert.equal(calls.setData[0].title, 'First bot');
assert.equal(calls.setData[0].primary_color, '#111827');
assert.equal(calls.locale, 'fa');

context.selectedBot = { widget_config: { primary_color: '#ff0000' } };
syncBot();
assert.equal(calls.setDefaults[0].title, 'Support', 'bot changes must reset previous overrides');
assert.equal(calls.setData[0].primary_color, '#ff0000');
assert.equal(calls.locale, 'en', 'bot changes must restore the global locale');

context.selectedBot = undefined;
assert.doesNotThrow(syncBot, 'an empty bot list must not attempt to update the form');
console.log('Widget settings mount and bot-selection checks passed.');

// Exercise submission rather than only checking source text: multipart writes
// must use POST with Laravel method spoofing, including empty JSON collections.
const submitDeclaration = statements.flatMap((node) => node.declarations || []).find((node) => node.id?.name === 'submit');
let transformed;
let submitted;
const submitContext = vm.createContext({
    selectedBot: { id: 42 },
    transform: (callback) => { transformed = callback; },
    post: (url, options) => { submitted = { url, options }; },
    route: (name, id) => `${name}/${id}`,
});
vm.runInContext(`(${source.slice(submitDeclaration.init.start, submitDeclaration.init.end)})`, submitContext)({ preventDefault() {} });
const payload = transformed({ primary_color: '#abcdef', launcher_icon: 'assistant' });
assert.equal(payload._method, 'put');
assert.equal(payload.enabled_locales, '[]');
assert.equal(payload.localized_content, '{}');
assert.equal(payload.primary_color, '#abcdef');
assert.equal(submitted.url, 'widget.install.update/42');
assert.equal(submitted.options.forceFormData, true);
console.log('Widget settings submission checks passed.');

// Editing one language must preserve base questions and all other languages.
const localizedFunction = statements.find((node) => node.type === 'FunctionDeclaration' && node.id.name === 'updateLocalizedCopy');
const starterFunction = statements.find((node) => node.type === 'FunctionDeclaration' && node.id.name === 'updateStarterPrompts');
const starterContext = vm.createContext({
    data: {
        suggested_prompts: ['Base question'],
        localized_content: { fa: { title: 'پشتیبانی', suggested_prompts: ['قدیمی'] }, tr: { suggested_prompts: ['Eski soru'] } },
    },
    starterLocale: 'fa',
    editingLocale: 'en',
});
starterContext.setData = (key, value) => { starterContext.data[key] = value; };
vm.runInContext(source.slice(localizedFunction.start, localizedFunction.end), starterContext);
vm.runInContext(source.slice(starterFunction.start, starterFunction.end), starterContext);
starterContext.updateStarterPrompts(['چطور شروع کنم؟']);
assert.equal(starterContext.data.localized_content.fa.suggested_prompts[0], 'چطور شروع کنم؟');
assert.equal(starterContext.data.localized_content.fa.title, 'پشتیبانی');
assert.equal(starterContext.data.localized_content.tr.suggested_prompts[0], 'Eski soru');
assert.equal(starterContext.data.suggested_prompts[0], 'Base question');
starterContext.starterLocale = 'tr';
starterContext.updateStarterPrompts(['Nasıl başlayabilirim?']);
assert.equal(starterContext.data.localized_content.tr.suggested_prompts[0], 'Nasıl başlayabilirim?');
starterContext.starterLocale = '';
starterContext.updateStarterPrompts(['New base']);
assert.equal(starterContext.data.suggested_prompts[0], 'New base');
assert.equal(starterContext.data.localized_content.fa.suggested_prompts[0], 'چطور شروع کنم؟');

// The collapsed language section must not hide everyday customization controls.
function findAdvanced(node) {
    if (!node || typeof node !== 'object') return null;
    if (node.type === 'JSXElement' && node.openingElement.attributes.some((attribute) => attribute.name?.name === 'id' && attribute.value?.value === 'advanced-widget-settings')) return node;
    for (const value of Object.values(node)) {
        for (const child of Array.isArray(value) ? value : [value]) {
            const found = findAdvanced(child);
            if (found) return found;
        }
    }
    return null;
}
const advanced = findAdvanced(page);
assert.ok(advanced);
const advancedSource = source.slice(advanced.start, advanced.end);
assert.ok(advancedSource.includes('Widget languages'));
for (const section of ['Appearance', 'Layout', 'Conversation starters', 'Labels']) {
    assert.ok(!advancedSource.includes(`t("${section}")`), `${section} must remain visible when advanced settings are collapsed`);
}
console.log('Localized starter editing and settings visibility checks passed.');
