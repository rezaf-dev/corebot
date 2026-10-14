import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';
import React from 'react';
import { renderToString } from 'react-dom/server';
import { transformWithOxc } from 'vite';
import { setLocalization, t } from '../../resources/js/localization.js';

// Render the real JSX and invoke its controls with a small state harness.
// This needs no additional browser or DOM dependency.
async function pageHarness(path, name, overrides = {}) {
    const source = fs.readFileSync(path, 'utf8').replace(/^import[\s\S]*?;\s*/gm, '').replace('export default function', 'function');
    const { code } = await transformWithOxc(source, path, { jsx: { runtime: 'classic' } });
    const states = [];
    let cursor = 0;
    let data;
    const controls = [];
    const effectDeps = [];
    let effectCursor = 0;
    let pendingEffects = [];
    const context = vm.createContext({
        React: { ...React, createElement: (type, props, ...children) => {
            if (typeof type === 'string') controls.push({ tag: type, type, ...props, children });
            return React.createElement(type, props, ...children);
        } },
        t,
        useState: (initial) => {
            const index = cursor++;
            if (!(index in states)) states[index] = initial;
            return [states[index], (value) => { states[index] = typeof value === 'function' ? value(states[index]) : value; }];
        },
        useEffect: (callback, deps) => {
            const index = effectCursor++;
            if (!effectDeps[index] || deps.some((value, i) => !Object.is(value, effectDeps[index][i]))) pendingEffects.push(callback);
            effectDeps[index] = deps;
        },
        useLayoutEffect: () => {},
        useRef: (initial) => ({ current: initial }),
        usePoll: () => {},
        currentLocale: () => 'en',
        useMemo: (callback) => callback(),
        useForm: (initial) => {
            data ??= initial;
            return { data, setData: (key, value) => { data = typeof key === 'string' ? { ...data, [key]: value } : key; }, errors: {}, setDefaults: () => {} };
        },
        AuthenticatedLayout: ({ children }) => React.createElement('main', null, children),
        Head: () => null,
        Link: ({ children, href, preserveState, preserveScroll, ...props }) => React.createElement('a', { href, ...props }, children),
        InputLabel: ({ htmlFor, value }) => React.createElement('label', { htmlFor }, value),
        InputError: () => null,
        TextInput: (props) => React.createElement('input', props),
        Checkbox: (props) => React.createElement('input', { ...props, type: 'checkbox' }),
        PrimaryButton: ({ children, ...props }) => React.createElement('button', props, children),
        route: (name) => `/routes/${name}`,
        ...overrides,
    });
    vm.runInContext(`${code}\nthis.Page = ${name};`, context);
    return {
        render(props) {
            cursor = 0;
            effectCursor = 0;
            controls.length = 0;
            return renderToString(React.createElement(context.Page, props));
        },
        runEffects() { const effects = pendingEffects; pendingEffects = []; effects.forEach((effect) => effect()); },
        controls: () => controls,
        control(id) { return controls.find((control) => control.id === id); },
        button(text) { return controls.find((control) => control.type === 'button' && control.children.includes(text)); },
    };
}

setLocalization({ translations: { Chat: 'گفتگو', Help: 'راهنما', Support: 'پشتیبانی', Assistant: 'دستیار' } });
const widget = await pageHarness('resources/js/Pages/Widget/Install.jsx', 'Install');
const defaults = {
    title: 'Support', subtitle: 'Online', primary_color: '#111827', accent_color: '#2563eb',
    background_color: '#f3f4f6', surface_color: '#ffffff', text_color: '#111827',
    position: 'bottom-right', border_radius: 16, panel_width: 400, launcher_size: 56,
    offset_x: 16, offset_y: 16, launcher_icon: 'chat', launcher_label: 'Need help?',
    input_placeholder: 'Type your message…', send_button_label: 'Send', initial_open: false,
    suggested_prompts: [], enabled_locales: [], localized_content: {}, default_locale: '',
};
const props = { defaults, bots: [{ id: 1, name: 'Support bot', widget_url: '/widget.js', widget_config: {} }], positions: ['bottom-right', 'top-left'], icons: ['chat', 'help', 'support', 'assistant'], languages: [{ code: 'en', native_name: 'English', direction: 'ltr' }] };
let html = widget.render(props);
assert.match(html, /id="advanced-widget-settings" hidden=""/);
assert.match(html, /lg:sticky lg:top-6/);
assert.match(html, /Type your message…/, 'chat preview should show even when initial state is closed');
assert.match(html, /value="assistant"[^>]*>دستیار/, 'translated icon labels must retain stable values');
widget.button('Advanced settings').onClick();
html = widget.render(props);
assert.doesNotMatch(html, /id="advanced-widget-settings" hidden=""/);
widget.control('primary_color_picker').onChange({ target: { value: '#ff0000' } });
widget.button('Show launcher').onClick();
html = widget.render(props);
assert.match(html, /background:#ff0000/, 'color changes must immediately affect launcher preview');
widget.control('launcher_icon').onChange({ target: { value: 'assistant' } });
html = widget.render(props);
assert.match(html, /value="assistant" selected=""/);
assert.match(widget.render({ ...props, bots: [] }), /href="\/routes\/bots.create"/);

const bot = await pageHarness('resources/js/Pages/Bots/Form.jsx', 'Form');
html = bot.render({ bot: null });
assert.match(html, /id="advanced-bot-settings" hidden=""/);
bot.button('Advanced settings').onClick();
assert.doesNotMatch(bot.render({ bot: null }), /id="advanced-bot-settings" hidden=""/);

const guide = await pageHarness('resources/js/Components/WorkspaceOnboarding.jsx', 'WorkspaceOnboarding');
const onboarding = { completed: 0, total: 4, steps: [{ key: 'ai', title: 'Connect AI', url: '/ai', complete: false, available: true }, { key: 'knowledge', title: 'Add knowledge', url: '/knowledge', complete: false, available: false }] };
html = guide.render({ onboarding });
assert.match(html, /href="\/ai"/);
assert.doesNotMatch(html, /href="\/knowledge"/, 'blocked steps must explain prerequisites rather than link to unusable forms');
assert.match(html, /id="workspace-setup-steps" hidden=""/);
guide.button('View setup guide').onClick();
assert.doesNotMatch(guide.render({ onboarding }), /id="workspace-setup-steps" hidden=""/);
assert.equal(guide.render({ onboarding: null }), '');
console.log('Settings and onboarding rendering and interaction checks passed.');

setLocalization();
let activeRoute = 'bots.edit';
const navigation = await pageHarness('resources/js/Components/WorkspaceNavigation.jsx', 'WorkspaceNavigation', {
    route: (name) => name ? `/routes/${name}` : { current: (pattern) => pattern.endsWith('.*') ? activeRoute.startsWith(pattern.slice(0, -1)) : activeRoute === pattern },
});
html = navigation.render({ capabilities: { configure: true, platform: false } });
for (const name of ['bots.index', 'knowledge-sources.index', 'widget.install', 'ai-settings.edit', 'dashboard', 'staff.index']) assert.ok(html.includes(`/routes/${name}`));
assert.equal((html.match(/aria-current="page"/g) || []).length, 1, 'only the current destination should be highlighted');
assert.match(html, /href="\/routes\/bots.index" aria-current="page"/);
html = navigation.render({ capabilities: { configure: false, platform: false } });
assert.match(html, /conversations.index/);
assert.doesNotMatch(html, /bots.index|settings.index|admin.index/, 'agents should only see destinations they can access');
html = navigation.render({ capabilities: { configure: true, platform: true } });
assert.match(html, /tenants.index/);
assert.match(html, /language-settings.edit/);
assert.doesNotMatch(html, /href="\/routes\/ai-settings.edit"/, 'platform admins must choose a workspace before AI settings');

let capabilities = { configure: true, platform: false };
let request;
let confirmed = false;
const inbox = await pageHarness('resources/js/Pages/Conversations/Index.jsx', 'Inbox', {
    usePage: () => ({ props: { auth: { capabilities } } }),
    router: { on: () => () => {}, post: (...args) => { request = args; } },
    window: { confirm: () => confirmed },
});
const inboxProps = {
    conversations: { current_page: 1, last_page: 1, data: [
        { id: 1, visitor_name: 'First', country_name: 'Türkiye', country_code: 'TR' },
        { id: 2, visitor_name: 'Second', country_code: 'DE' },
        { id: 3, visitor_name: 'Third' },
    ] },
    counts: { all: 3 }, filters: { tab: 'all' }, conversation: null, bots: [], tenants: [], staff: [],
};
html = inbox.render(inboxProps);
inbox.runEffects();
assert.match(html, /Türkiye/);
assert.match(html, />DE</);
assert.match(html, /Unknown country/);
inbox.button('Select').onClick();
html = inbox.render(inboxProps);
assert.equal(inbox.button('Resolve selected').disabled, true);
inbox.controls().find((control) => control['aria-label'] === 'Select conversation 1').onChange();
html = inbox.render(inboxProps);
assert.match(html, /1 selected/);
inbox.button('Resolve selected').onClick();
assert.equal(request[0], '/routes/conversations.bulk-action');
assert.deepEqual(Array.from(request[1].conversation_ids), [1]);
assert.equal(request[1].action, 'resolve');
request = undefined;
inbox.button('Delete selected').onClick();
assert.equal(request, undefined, 'cancelled delete must never send a request');
confirmed = true;
inbox.button('Delete selected').onClick();
assert.equal(request[1].action, 'delete');
request[2].onError();
assert.match(inbox.render(inboxProps), /Unable to update the selection/);
inbox.controls().find((control) => control.tag === 'input' && control.type === 'checkbox' && control['aria-label'] === undefined && control.onChange && control.checked === false).onChange({ target: { checked: true } });
html = inbox.render(inboxProps);
assert.match(html, /3 selected/);
inbox.button('Resolve selected').onClick();
assert.deepEqual(Array.from(request[1].conversation_ids), [1, 2, 3]);
const nextPage = { ...inboxProps, conversations: { ...inboxProps.conversations, current_page: 2, data: [{ id: 4, visitor_name: 'Fourth' }] } };
inbox.render(nextPage);
inbox.runEffects();
assert.match(inbox.render(nextPage), /0 selected/, 'page changes must clear previous selections');
capabilities = { configure: false, platform: false };
html = inbox.render(inboxProps);
assert.doesNotMatch(html, /Select conversation|Resolve selected|Delete selected/);
console.log('Navigation, country and bulk action interaction checks passed.');
