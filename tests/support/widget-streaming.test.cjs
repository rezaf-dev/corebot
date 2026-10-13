const fs = require('fs');
const path = require('path');

const widgetPath = path.resolve(__dirname, '../../public/widget.js');
const widgetSource = fs.readFileSync(widgetPath, 'utf8');

function assertIncludes(expected, label) {
    if (!widgetSource.includes(expected)) {
        throw new Error(`${label}: expected widget source to include ${JSON.stringify(expected)}`);
    }
}

assertIncludes('renderFrame = requestAnimationFrame(flushPendingDelta)', 'frame-batched rendering');
assertIncludes("bubble.textContent = raw", 'plain text while streaming');
assertIncludes("bubble.dir = messageDirection(raw)", 'direction updated while streaming');
assertIncludes("setBubbleText(node, getBubble(node)?.dataset.rawText || '')", 'final markdown rendering');
assertIncludes('page: pageContext()', 'page context sent with streamed messages');
assertIncludes('showUnreadNotification()', 'closed widget reply notification');
assertIncludes('playNotificationSound()', 'reply notification sound');
assertIncludes('crm-ai-launcher-badge', 'unread reply badge');

console.log('widget-streaming tests passed');

const vm = require('node:vm');
const assert = require('node:assert/strict');
const streamFunction = widgetSource.slice(widgetSource.indexOf('    async function streamMessage('), widgetSource.indexOf('    function applyConfig('));
(async () => {
    const events = 'data: {"type":"meta","human_support":true}\n\ndata: {"type":"done"}\n\n';
    let delivered = false;
    let acknowledged = false;
    let mode;
    const context = vm.createContext({
        fetch: async () => ({ ok: true, body: { getReader: () => ({ read: async () => { if (delivered) return { done: true }; delivered = true; return { value: new TextEncoder().encode(events), done: false }; } }) } }),
        TextDecoder, apiBase: '/api', botKey: 'bot', state: { conversation_id: 1, conversation_token: 'token' }, widgetLocalization: { locale: () => 'en' }, pageContext: () => ({}),
        applySupportMode: (value) => { mode = value; }, shouldPromptContact: () => false,
        showHumanSupportAcknowledgement: () => { acknowledged = true; },
    });
    vm.runInContext(streamFunction, context);
    await context.streamMessage('Help', { remove: () => { throw new Error('Human handoff must keep a visible acknowledgement'); } });
    assert.equal(acknowledged, true);
    assert.equal(mode, 'human');
    console.log('Human handoff streaming rendering passed');
})().catch((error) => { console.error(error); process.exitCode = 1; });

(async () => {
    const pollFunction = widgetSource.slice(widgetSource.indexOf('    async function pollManualMessages('), widgetSource.indexOf('    function applySupportMode('));
    let requests = 0;
    let added = 0;
    const context = vm.createContext({
        state: { conversation_id: 1, conversation_token: 'token' }, manualMessageRequestRunning: false, latestManualMessageId: 0,
        post: async () => { requests++; return { support_mode: 'human', messages: [{ id: 1, content: 'Hello' }] }; },
        applySupportMode: () => {}, add: () => { added++; }, isOpen: true,
    });
    vm.runInContext(pollFunction, context);
    await Promise.all([context.pollManualMessages(), context.pollManualMessages()]);
    await context.pollManualMessages();
    assert.equal(requests, 2, 'overlapping polls are skipped');
    assert.equal(added, 1, 'staff replies are not duplicated');
    console.log('Human support polling deduplication passed');
})().catch((error) => { console.error(error); process.exitCode = 1; });

const acknowledgementFunction = widgetSource.slice(widgetSource.indexOf('    function showHumanSupportAcknowledgement('), widgetSource.indexOf('    async function streamMessage('));
let acknowledgement;
const acknowledgementContext = vm.createContext({
    t: (key) => 'translated: ' + key,
    setBubbleText: (node, text) => { acknowledgement = text; },
});
vm.runInContext(acknowledgementFunction, acknowledgementContext);
acknowledgementContext.showHumanSupportAcknowledgement({});
assert.equal(acknowledgement, 'translated: Your message has been sent to our support team. Replies will appear here.');

for (const locale of ['en', 'fa', 'ar', 'tr', 'it', 'es']) {
    const translations = JSON.parse(fs.readFileSync(path.resolve(__dirname, `../../lang/${locale}.json`), 'utf8'));
    assert.ok(translations['Your message has been sent to our support team. Replies will appear here.'], `Handoff acknowledgement translated in ${locale}`);
}

(async () => {
    const submitFunction = widgetSource.slice(widgetSource.indexOf("    form.addEventListener('submit',"), widgetSource.indexOf("    contact.addEventListener('submit',"));
    let submit;
    let acknowledged = false;
    let mode;
    const context = vm.createContext({
        form: { addEventListener: (_, handler) => { submit = handler; } },
        isLoading: false, isStarting: false,
        input: { value: 'Help' }, state: { conversation_id: 1, conversation_token: 'token' },
        messages: { querySelectorAll: () => [] }, hideSuggestedPrompts: () => {},
        add: () => ({ remove: () => { throw new Error('Fallback handoff must remain visible'); } }),
        scrollToBottom: () => {}, setLoading: () => {},
        streamMessage: async () => { throw new Error('Stream unavailable'); },
        post: async () => ({ human_support: true }), pageContext: () => ({}),
        applySupportMode: (value) => { mode = value; },
        showHumanSupportAcknowledgement: () => { acknowledged = true; },
        shouldPromptContact: () => false,
        setBubbleText: () => { throw new Error('Do not show an error for a successful handoff'); },
    });
    vm.runInContext(submitFunction, context);
    await submit({ preventDefault() {} });
    assert.equal(acknowledged, true, 'regular response fallback shows handoff acknowledgement');
    assert.equal(mode, 'human');
})().catch((error) => { console.error(error); process.exitCode = 1; });

(async () => {
    const events = 'data: {"type":"text_delta","delta":"Existing reply"}\n\ndata: {"type":"meta","human_support":true}\n\ndata: {"type":"done"}\n\n';
    let delivered = false;
    let reply;
    const bubble = { dataset: { rawText: '' } };
    const context = vm.createContext({
        fetch: async () => ({ ok: true, body: { getReader: () => ({ read: async () => {
            if (delivered) return { done: true };
            delivered = true;
            return { value: new TextEncoder().encode(events), done: false };
        } }) } }),
        TextDecoder, apiBase: '/api', botKey: 'bot', state: {}, widgetLocalization: { locale: () => 'en' }, pageContext: () => ({}),
        applySupportMode: () => {}, shouldPromptContact: () => false, clearLoading: () => {},
        requestAnimationFrame: () => 1, cancelAnimationFrame: () => {}, scrollToBottom: () => {},
        appendStreamingBubbleText: (_, delta) => { bubble.dataset.rawText += delta; }, getBubble: () => bubble,
        setBubbleText: (_, text) => { reply = text; },
        showHumanSupportAcknowledgement: () => { throw new Error('Do not replace a delivered reply with a handoff acknowledgement'); },
    });
    vm.runInContext(streamFunction, context);
    await context.streamMessage('Help', {});
    assert.equal(reply, 'Existing reply');
})().catch((error) => { console.error(error); process.exitCode = 1; });
