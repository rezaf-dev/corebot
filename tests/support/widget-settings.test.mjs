import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';
import React from 'react';
import { renderToString } from 'react-dom/server';
import { useForm } from '@inertiajs/react';
import { parseAst } from 'rolldown/parseAst';
import { resolveWidgetText, defaultWidgetMessages, defaultWidgetPrompts } from '../../resources/js/widget-settings.js';

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
    languages: [{ code: 'en' }, { code: 'fa' }],
    setPreviewOpen: (value) => { calls.previewOpen = value; },
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
    editingLocale: 'fa',
});
starterContext.setData = (key, value) => { starterContext.data[key] = value; };
vm.runInContext(source.slice(localizedFunction.start, localizedFunction.end), starterContext);
vm.runInContext(source.slice(starterFunction.start, starterFunction.end), starterContext);
starterContext.updateStarterPrompts(['چطور شروع کنم؟']);
assert.equal(starterContext.data.localized_content.fa.suggested_prompts[0], 'چطور شروع کنم؟');
assert.equal(starterContext.data.localized_content.fa.title, 'پشتیبانی');
assert.equal(starterContext.data.localized_content.tr.suggested_prompts[0], 'Eski soru');
assert.equal(starterContext.data.suggested_prompts[0], 'Base question');
starterContext.editingLocale = 'tr';
starterContext.updateStarterPrompts(['Nasıl başlayabilirim?']);
assert.equal(starterContext.data.localized_content.tr.suggested_prompts[0], 'Nasıl başlayabilirim?');
starterContext.editingLocale = 'en';
starterContext.updateStarterPrompts(['New base']);
assert.equal(starterContext.data.localized_content.en.suggested_prompts[0], 'New base');
assert.equal(starterContext.data.suggested_prompts[0], 'Base question');
assert.equal(starterContext.data.localized_content.fa.suggested_prompts[0], 'چطور شروع کنم؟');

// The language section follows all text, appearance, and layout controls.
function elements(node, predicate) {
    if (!node || typeof node !== 'object') return [];
    const found = node.type === 'JSXElement' && predicate(node) ? [node] : [];
    for (const value of Object.values(node)) {
        for (const child of Array.isArray(value) ? value : [value]) found.push(...elements(child, predicate));
    }
    return found;
}
const byId = (id) => elements(page, (node) => node.openingElement.attributes.some((attribute) => attribute.name?.name === 'id' && attribute.value?.value === id))[0];
const texts = byId('widget-text-settings');
const languageConfig = byId('widget-language-config');
assert.ok(texts && languageConfig);
assert.ok(texts.start < languageConfig.start);
assert.ok(!byId('advanced-widget-settings'), 'language configuration must always be visible');
const textSection = source.slice(texts.start, texts.end);
assert.ok(textSection.includes('effectiveLanguages.map'), 'text selector only offers active languages');
for (const field of ['title', 'subtitle', 'launcher_label', 'input_placeholder', 'send_button_label', 'welcome_message', 'fallback_message']) {
    assert.ok(textSection.includes(`['${field}',`), `${field} belongs in the unified language editor`);
}
assert.ok(textSection.includes('Conversation starters'));
const formElement = elements(page, (node) => node.openingElement.name.name === 'form')[0];
const sections = formElement.children.filter((node) => node.type === 'JSXElement' && node.openingElement.name.name === 'section');
assert.equal(sections.at(-1), languageConfig, 'language configuration is the last settings section');
const aside = elements(page, (node) => node.openingElement.name.name === 'aside')[0];
assert.ok(aside.start < formElement.start, 'preview precedes the form on small screens');
assert.equal(aside.children.find((node) => node.type === 'JSXElement').openingElement.name.name, 'WidgetPreview');
assert.equal(calls.previewOpen, false, 'switching bots resets the preview to its launcher');

// Resolve the same defaults shown in the editor and used by the preview.
const defaults = { title: 'Support', subtitle: 'We typically reply instantly', launcher_label: "We're here to help", input_placeholder: 'Type your message…', send_button_label: 'Send' };
for (const filename of fs.readdirSync('lang').filter((name) => name.endsWith('.json'))) {
    const locale = filename.replace('.json', '');
    const translations = JSON.parse(fs.readFileSync(`lang/${filename}`, 'utf8'));
    const resolved = resolveWidgetText({ ...defaults, suggested_prompts: [] }, {}, defaults, translations, locale);
    assert.equal(resolved.title, translations.Support || 'Support');
    assert.equal(resolved.subtitle, translations[defaults.subtitle] || defaults.subtitle);
    assert.equal(resolved.welcome_message, translations[defaultWidgetMessages.welcome_message] || defaultWidgetMessages.welcome_message);
    assert.deepEqual(resolved.suggested_prompts, defaultWidgetPrompts.map((prompt) => translations[prompt] || prompt));
    const config = { ...defaults, title: 'Existing base title', localized_content: { [locale]: { title: 'Custom title', subtitle: 'Custom subtitle', suggested_prompts: ['Custom question'] } } };
    const custom = resolveWidgetText(config, {}, defaults, translations, locale);
    assert.equal(custom.title, 'Custom title');
    assert.equal(custom.subtitle, 'Custom subtitle');
    assert.deepEqual(custom.suggested_prompts, ['Custom question']);
    config.localized_content[locale] = { title: '', subtitle: '   ', suggested_prompts: [''] };
    const cleared = resolveWidgetText(config, {}, defaults, translations, locale);
    assert.equal(cleared.title, 'Existing base title', 'cleared overrides restore existing fallback text');
    assert.equal(cleared.subtitle, translations[defaults.subtitle] || defaults.subtitle);
}
console.log('Unified language editor, defaults, preview order, and language configuration checks passed.');
