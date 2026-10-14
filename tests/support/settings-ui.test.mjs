import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';
import React from 'react';
import { renderToString } from 'react-dom/server';
import { transformWithOxc } from 'vite';
import { setLocalization, t } from '../../resources/js/localization.js';

// Render the real JSX and invoke its controls with a small state harness.
// This needs no additional browser or DOM dependency.
async function pageHarness(path, name) {
    const source = fs.readFileSync(path, 'utf8').replace(/^import .*;\n/gm, '').replace('export default function', 'function');
    const { code } = await transformWithOxc(source, path, { jsx: { runtime: 'classic' } });
    const states = [];
    let cursor = 0;
    let data;
    const controls = [];
    const context = vm.createContext({
        React: { ...React, createElement: (type, props, ...children) => {
            if (typeof type === 'string') controls.push({ type, ...props, children });
            return React.createElement(type, props, ...children);
        } },
        t,
        useState: (initial) => {
            const index = cursor++;
            if (!(index in states)) states[index] = initial;
            return [states[index], (value) => { states[index] = value; }];
        },
        useEffect: () => {},
        useMemo: (callback) => callback(),
        useForm: (initial) => {
            data ??= initial;
            return { data, setData: (key, value) => { data = typeof key === 'string' ? { ...data, [key]: value } : key; }, errors: {}, setDefaults: () => {} };
        },
        AuthenticatedLayout: ({ children }) => React.createElement('main', null, children),
        Head: () => null,
        Link: ({ children, href, ...props }) => React.createElement('a', { href, ...props }, children),
        InputLabel: ({ htmlFor, value }) => React.createElement('label', { htmlFor }, value),
        InputError: () => null,
        TextInput: (props) => React.createElement('input', props),
        Checkbox: (props) => React.createElement('input', { ...props, type: 'checkbox' }),
        PrimaryButton: ({ children, ...props }) => React.createElement('button', props, children),
        route: (name) => `/routes/${name}`,
    });
    vm.runInContext(`${code}\nthis.Page = ${name};`, context);
    return {
        render(props) {
            cursor = 0;
            controls.length = 0;
            return renderToString(React.createElement(context.Page, props));
        },
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
