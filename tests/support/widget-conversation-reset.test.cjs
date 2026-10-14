const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const source = fs.readFileSync(require('node:path').resolve(__dirname, '../../public/widget.js'), 'utf8');
const section = (start, end) => source.slice(source.indexOf(start), source.indexOf(end));
const resetAndRequests = section('    function resetConversation(', '    function applySupportMode(');
const submitCode = section("    form.addEventListener('submit',", "    contact.addEventListener('submit',");
const streamCode = section('    async function streamMessage(', '    function applyConfig(');

function harness(fetch) {
    const stored = new Map();
    const cleared = [];
    const bubbles = [];
    let submit;
    const context = vm.createContext({
        state: { conversation_id: 7, conversation_token: 'old-token', visitor_id: 'visitor', locale: 'tr', welcome_message: 'Old welcome', has_contact: true },
        contactConfig: { has_contact: true, countryCode: 'TR' },
        manualMessagePolling: 42, latestManualMessageId: 100, manualMessageRequestRunning: false,
        window: { clearInterval: (id) => cleared.push(id), setInterval: () => 43 },
        localStorage: { setItem: (key, value) => stored.set(key, JSON.parse(value)) }, storageKey: 'chat',
        hideContact: () => {}, renderContactForm: () => {}, applySupportMode: () => {}, clearUnreadNotification: () => {},
        showWelcomeMessage: () => { bubbles.length = 0; },
        fetch, apiBase: '/api', botKey: 'bot', widgetLocalization: { locale: () => 'tr' },
        form: { addEventListener: (_, handler) => { submit = handler; } },
        isLoading: false, isStarting: false, isOpen: true, input: { value: 'New question' },
        messages: { querySelectorAll: () => [] }, hideSuggestedPrompts: () => {}, scrollToBottom: () => {},
        add: (role, text) => { const bubble = { role, text }; bubbles.push(bubble); return bubble; },
        setLoading: (value) => { context.isLoading = value; },
        pageContext: () => ({}), shouldPromptContact: () => false,
        showHumanSupportAcknowledgement: (node) => { node.text = 'Delivered'; },
        setBubbleText: (node, text) => { node.text = text; }, t: (text) => text, TextDecoder,
        startConversation: async () => {
            context.state.conversation_id = 8;
            context.state.conversation_token = 'new-token';
            context.showWelcomeMessage();
            context.localStorage.setItem('chat', JSON.stringify(context.state));
        },
    });
    vm.runInContext(resetAndRequests + streamCode + submitCode, context);
    return { context, stored, cleared, bubbles, submit: () => submit({ preventDefault() {} }) };
}
const failed = (status) => ({ ok: false, status, json: async () => ({ message: 'Failure' }) });
function delivered() {
    let read = false;
    return { ok: true, body: { getReader: () => ({ read: async () => {
        if (read) return { done: true };
        read = true;
        return { done: false, value: new TextEncoder().encode('data: {"type":"meta","human_support":true}\n\ndata: {"type":"done"}\n\n') };
    } }) } };
}

(async () => {
    const polling = harness(async () => failed(404));
    await polling.context.pollManualMessages();
    assert.equal(polling.context.state.conversation_id, undefined);
    assert.equal(polling.context.state.conversation_token, undefined);
    assert.equal(polling.context.state.welcome_message, undefined);
    assert.equal(polling.context.state.has_contact, undefined);
    assert.equal(polling.context.contactConfig.has_contact, false);
    assert.equal(polling.context.latestManualMessageId, 0);
    assert.equal(polling.context.manualMessagePolling, null);
    assert.deepEqual(polling.cleared, [42]);
    assert.deepEqual(polling.stored.get('chat'), { visitor_id: 'visitor', locale: 'tr' });

    for (const fallback of [false, true]) {
        const requests = [];
        const chat = harness(async (url, options) => {
            const body = JSON.parse(options.body);
            requests.push({ url, body });
            if (body.conversation_id === 7) return failed(fallback && url.endsWith('/stream') ? 500 : 404);
            return delivered();
        });
        await chat.submit();
        assert.equal(requests.length, fallback ? 3 : 2);
        assert.equal(requests.at(-1).body.conversation_id, 8);
        assert.equal(requests.at(-1).body.conversation_token, 'new-token');
        assert.equal(requests.at(-1).body.message, 'New question');
        assert.deepEqual(chat.bubbles.map((bubble) => bubble.text), ['New question', 'Delivered']);
        assert.equal(chat.context.input.value, '');
        assert.equal(chat.context.isLoading, false);
    }

    const serverFailure = harness(async () => failed(503));
    await serverFailure.submit();
    assert.equal(serverFailure.context.state.conversation_id, 7);
    assert.equal(serverFailure.cleared.length, 0);
    assert.match(serverFailure.bubbles.at(-1).text, /Support is unavailable/);

    const deletedAgain = harness(async () => failed(404));
    await deletedAgain.submit();
    assert.equal(deletedAgain.context.state.conversation_id, undefined);
    assert.equal(deletedAgain.context.input.value, 'New question', 'preserve draft if the replacement is also deleted');
    assert.equal(deletedAgain.context.isLoading, false);

    let resolvePoll;
    const stalePoll = harness(() => new Promise((resolve) => { resolvePoll = resolve; }));
    const pending = stalePoll.context.pollManualMessages();
    stalePoll.context.resetConversation(7);
    await stalePoll.context.startConversation();
    resolvePoll({ ok: true, json: async () => ({ support_mode: 'human', messages: [{ id: 101, content: 'Old reply' }] }) });
    await pending;
    assert.equal(stalePoll.bubbles.length, 0, 'old replies do not enter the new chat');
    assert.equal(stalePoll.context.resetConversation(7), false, 'old errors do not clear a replacement chat');
    assert.equal(stalePoll.context.state.conversation_id, 8);

    console.log('Widget deleted-conversation recovery passed');
})().catch((error) => { console.error(error); process.exitCode = 1; });
