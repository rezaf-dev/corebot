(function () {
    const script = document.currentScript;
    const botKey = script && script.dataset.botKey;
    if (!botKey) return;

    const apiBase = new URL('/api/public/chat', script.src).toString().replace(/\/$/, '');
    const storageKey = 'crm_ai_bot_' + botKey;
    const state = JSON.parse(localStorage.getItem(storageKey) || '{}');
    state.visitor_id = state.visitor_id || crypto.randomUUID();
    localStorage.setItem(storageKey, JSON.stringify(state));

    const root = document.createElement('div');
    root.innerHTML = `
        <style>
            .crm-ai-btn{position:fixed;right:20px;bottom:20px;z-index:2147483647;border:0;border-radius:999px;background:#111827;color:white;width:56px;height:56px;font:24px system-ui;box-shadow:0 12px 30px rgba(0,0,0,.25);cursor:pointer}
            .crm-ai-panel{position:fixed;right:20px;bottom:88px;z-index:2147483647;width:min(380px,calc(100vw - 40px));height:520px;display:none;grid-template-rows:auto 1fr auto;background:white;border:1px solid #d1d5db;border-radius:8px;box-shadow:0 18px 60px rgba(0,0,0,.25);font:14px system-ui;color:#111827;overflow:hidden}
            .crm-ai-head{padding:14px 16px;background:#111827;color:white;font-weight:700}
            .crm-ai-msgs{padding:14px;overflow:auto;background:#f9fafb}
            .crm-ai-msg{margin:0 0 10px;padding:10px 12px;border-radius:8px;max-width:85%;line-height:1.4}
            .crm-ai-user{margin-left:auto;background:#2563eb;color:white}
            .crm-ai-assistant{background:white;border:1px solid #e5e7eb}
            .crm-ai-form{display:flex;gap:8px;padding:12px;border-top:1px solid #e5e7eb}
            .crm-ai-form input{flex:1;border:1px solid #d1d5db;border-radius:6px;padding:10px}
            .crm-ai-form button,.crm-ai-contact button{border:0;border-radius:6px;background:#111827;color:white;padding:10px 12px;cursor:pointer}
            .crm-ai-contact{display:none;gap:8px;padding:12px;border-top:1px solid #e5e7eb}
            .crm-ai-contact input{width:100%;box-sizing:border-box;border:1px solid #d1d5db;border-radius:6px;padding:9px;margin-bottom:8px}
        </style>
        <button class="crm-ai-btn" aria-label="Open support chat">?</button>
        <section class="crm-ai-panel" aria-live="polite">
            <div class="crm-ai-head">Support</div>
            <div class="crm-ai-msgs"></div>
            <form class="crm-ai-form"><input maxlength="2000" placeholder="Ask a question" /><button>Send</button></form>
            <form class="crm-ai-contact"><input name="visitor_name" placeholder="Name" /><input name="visitor_email" placeholder="Email" /><input name="visitor_phone" placeholder="Phone" /><button>Send contact</button></form>
        </section>`;
    document.body.appendChild(root);

    const button = root.querySelector('.crm-ai-btn');
    const panel = root.querySelector('.crm-ai-panel');
    const messages = root.querySelector('.crm-ai-msgs');
    const form = root.querySelector('.crm-ai-form');
    const input = form.querySelector('input');
    const contact = root.querySelector('.crm-ai-contact');

    button.addEventListener('click', async () => {
        panel.style.display = panel.style.display === 'grid' ? 'none' : 'grid';
        if (!state.conversation_id) await start();
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const text = input.value.trim();
        if (!text) return;
        input.value = '';
        add('user', text);
        const assistant = add('assistant', '');
        try {
            await streamMessage(text, assistant);
        } catch {
            try {
                const res = await post('/message', { conversation_id: state.conversation_id, message: text });
                assistant.textContent = res.message;
                if (res.fallback) contact.style.display = 'block';
            } catch {
                assistant.textContent = 'Support is unavailable right now.';
            }
        }
    });

    contact.addEventListener('submit', async (event) => {
        event.preventDefault();
        const payload = Object.fromEntries(new FormData(contact).entries());
        await post('/contact', { conversation_id: state.conversation_id, ...payload });
        contact.style.display = 'none';
        add('assistant', 'Thanks. Your contact details were saved.');
    });

    async function start() {
        const res = await post('/start', { visitor_id: state.visitor_id, source_url: location.href });
        state.conversation_id = res.conversation_id;
        localStorage.setItem(storageKey, JSON.stringify(state));
        add('assistant', res.welcome_message || 'Hi, how can I help?');
    }

    async function post(path, body) {
        const response = await fetch(apiBase + path, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            body: JSON.stringify({ bot_public_key: botKey, ...body }),
        });
        if (!response.ok) throw new Error('Request failed');
        return response.json();
    }

    async function streamMessage(text, node) {
        const response = await fetch(apiBase + '/message/stream', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'text/event-stream' },
            body: JSON.stringify({ bot_public_key: botKey, conversation_id: state.conversation_id, message: text }),
        });

        if (!response.ok || !response.body) throw new Error('Stream failed');

        const reader = response.body.getReader();
        const decoder = new TextDecoder();
        let buffer = '';

        while (true) {
            const { value, done } = await reader.read();
            if (done) break;

            buffer += decoder.decode(value, { stream: true });
            const events = buffer.split('\n\n');
            buffer = events.pop() || '';

            for (const event of events) {
                const line = event.split('\n').find((item) => item.startsWith('data: '));
                if (!line) continue;

                const payload = JSON.parse(line.slice(6));
                if (payload.type === 'text_delta') {
                    node.textContent += payload.delta;
                    messages.scrollTop = messages.scrollHeight;
                }

                if (payload.type === 'meta' && payload.fallback) {
                    contact.style.display = 'block';
                }
            }
        }
    }

    function add(role, text) {
        const node = document.createElement('div');
        node.className = 'crm-ai-msg crm-ai-' + role;
        node.textContent = text;
        messages.appendChild(node);
        messages.scrollTop = messages.scrollHeight;
        return node;
    }
})();
