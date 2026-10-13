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
    let removed = false;
    let mode;
    const context = vm.createContext({
        fetch: async () => ({ ok: true, body: { getReader: () => ({ read: async () => { if (delivered) return { done: true }; delivered = true; return { value: new TextEncoder().encode(events), done: false }; } }) } }),
        TextDecoder, apiBase: '/api', botKey: 'bot', state: { conversation_id: 1, conversation_token: 'token' }, widgetLocalization: { locale: () => 'en' }, pageContext: () => ({}),
        applySupportMode: (value) => { mode = value; }, shouldPromptContact: () => false,
        setBubbleText: () => { throw new Error('Human handoff must not render an empty or error bubble'); },
    });
    vm.runInContext(streamFunction, context);
    await context.streamMessage('Help', { remove: () => { removed = true; } });
    assert.equal(removed, true);
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
