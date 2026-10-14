import assert from 'node:assert/strict';
import { loadPreviewWidget, openPreviewChat } from '../../resources/js/widget-preview.js';

function demoDocument() {
    const statuses = [];
    let observerCallback;
    let timeout;
    let script;
    let ready = false;
    let open = false;
    let clicks = 0;
    let disconnected = false;
    const root = {
        querySelector: (selector) => selector === '.crm-ai-panel.is-open'
            ? open ? {} : null
            : { click() { clicks++; open = true; } },
    };
    const document = {
        createElement: () => ({ remove() { this.removed = true; } }),
        body: { appendChild(element) { script = element; } },
        querySelector: () => ready ? root : null,
        defaultView: {
            MutationObserver: class {
                constructor(callback) { observerCallback = callback; }
                observe() {}
                disconnect() { disconnected = true; }
            },
            setTimeout(callback) { timeout = callback; return 1; },
            clearTimeout() {},
        },
    };
    return {
        document, statuses,
        script: () => script,
        ready() { ready = true; observerCallback(); },
        timeout: () => timeout(),
        clicks: () => clicks,
        disconnected: () => disconnected,
    };
}

const demo = demoDocument();
const cleanup = loadPreviewWidget('https://app.test/widgets/bot_example.js', (status) => demo.statuses.push(status), demo.document);
assert.equal(demo.script().src, 'https://app.test/widgets/bot_example.js');
assert.deepEqual(demo.statuses, ['loading']);
demo.script().onload();
assert.deepEqual(demo.statuses, ['loading'], 'downloading the script alone does not mean config is ready');
openPreviewChat(demo.document);
assert.equal(demo.clicks(), 0, 'chat cannot open before configuration is applied');
demo.ready();
assert.deepEqual(demo.statuses, ['loading', 'ready']);
assert.equal(demo.disconnected(), true);
openPreviewChat(demo.document);
assert.equal(demo.clicks(), 1);
openPreviewChat(demo.document);
assert.equal(demo.clicks(), 1, 'opening chat again must not toggle it closed');
cleanup();
assert.equal(demo.script().removed, true);
assert.equal(demo.script().onload, null);

for (const failure of ['network', 'timeout']) {
    const failedDemo = demoDocument();
    loadPreviewWidget('https://app.test/widgets/bot_example.js', (status) => failedDemo.statuses.push(status), failedDemo.document);
    if (failure === 'network') failedDemo.script().onerror();
    else failedDemo.timeout();
    assert.deepEqual(failedDemo.statuses, ['loading', 'error']);
    assert.equal(failedDemo.disconnected(), true);
}

const unmounted = demoDocument();
const unmount = loadPreviewWidget('https://app.test/widgets/bot_example.js', (status) => unmounted.statuses.push(status), unmounted.document);
unmount();
unmounted.ready();
unmounted.timeout();
assert.deepEqual(unmounted.statuses, ['loading'], 'an unmounted demo must not update state');
console.log('Widget preview lifecycle and chat launch checks passed.');
