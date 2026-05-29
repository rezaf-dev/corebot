(function () {
    const script = document.currentScript;
    const botKey = script && script.dataset.botKey;
    if (!botKey) return;

    const apiBase = resolveApiBase(script);
    const storageKey = 'crm_ai_bot_' + botKey;
    const state = JSON.parse(localStorage.getItem(storageKey) || '{}');
    state.visitor_id = state.visitor_id || crypto.randomUUID();
    localStorage.setItem(storageKey, JSON.stringify(state));

    const DEFAULT_CONFIG = {
        title: 'Support',
        subtitle: 'We typically reply instantly',
        primary_color: '#111827',
        accent_color: '#2563eb',
        background_color: '#f3f4f6',
        surface_color: '#ffffff',
        text_color: '#111827',
        position: 'bottom-right',
        offset_x: 16,
        offset_y: 16,
        border_radius: 16,
        panel_width: 400,
        launcher_size: 56,
        send_button_label: 'Send',
        input_placeholder: 'Type your message…',
        launcher_icon: 'chat',
        initial_open: false,
    };

    let config = parseDatasetConfig(script.dataset);
    let contactConfig = {
        fields: ['name', 'email'],
        required: ['email'],
        has_contact: false,
        collect_on_start: false,
    };
    let isOpen = false;
    let initialOpenApplied = false;
    let isLoading = false;
    let isStarting = false;

    const LAUNCHER_ICONS = {
        chat: '<path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H5.17L4 17.17V4h16v12z"/>',
        help: '<path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 17h-2v-2h2v2zm2.07-7.75l-.9.92C13.45 12.9 13 13.5 13 15h-2v-.5c0-1.1.45-2.1 1.17-2.83l1.24-1.26c.37-.36.59-.86.59-1.41 0-1.1-.9-2-2-2s-2 .9-2 2H8c0-2.21 1.79-4 4-4s4 1.79 4 4c0 .88-.36 1.68-.93 2.25z"/>',
        support: '<path d="M12 1c-4.97 0-9 4.03-9 9v7c0 1.66 1.34 3 3 3h1v-8H5c0-3.87 3.13-7 7-7s7 3.13 7 7v1h-2c-1.1 0-2 .9-2 2v6c0 1.1.9 2 2 2h4c1.1 0 2-.9 2-2v-6c0-4.97-4.03-9-9-9z"/>',
    };

    const root = document.createElement('div');
    root.innerHTML = buildMarkup();
    document.body.appendChild(root);

    const crmRoot = root.querySelector('.crm-ai-root');
    const button = root.querySelector('.crm-ai-btn');
    const iconChat = root.querySelector('.crm-ai-icon-chat');
    const iconClose = root.querySelector('.crm-ai-icon-close');
    const panel = root.querySelector('.crm-ai-panel');
    const closeBtn = root.querySelector('.crm-ai-close');
    const headTitle = root.querySelector('.crm-ai-head-title');
    const headSub = root.querySelector('.crm-ai-head-sub');
    const messages = root.querySelector('.crm-ai-msgs');
    const form = root.querySelector('.crm-ai-form');
    const input = form.querySelector('input');
    const sendBtn = form.querySelector('button');
    const contact = root.querySelector('.crm-ai-contact');
    const isMobile = () => window.matchMedia('(max-width: 480px)').matches;

    renderContactForm({});

    applyConfig(config);

    loadRemoteConfig().then((remote) => {
        config = mergeConfig(config, remote);
        applyConfig(config);

        if (remote.welcome_message) {
            state.welcome_message = remote.welcome_message;
            config.welcome_message = remote.welcome_message;
            localStorage.setItem(storageKey, JSON.stringify(state));
        }

        applyInitialOpen();
    });

    button.addEventListener('click', () => togglePanel());
    closeBtn.addEventListener('click', () => togglePanel(false));

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (isLoading || isStarting) return;

        const text = input.value.trim();
        if (!text) return;

        if (!state.conversation_id) {
            await startConversation();
            if (!state.conversation_id) return;
        }

        input.value = '';
        add('user', text);

        const assistant = add('assistant', '', { loading: true });
        setLoading(true);

        try {
            await streamMessage(text, assistant);
        } catch {
            try {
                const res = await post('/message', { conversation_id: state.conversation_id, message: text });
                setBubbleText(assistant, res.message);
                if (shouldPromptContact(res)) showContact();
            } catch {
                setBubbleText(assistant, 'Support is unavailable right now. Please try again later.');
            }
        } finally {
            setLoading(false);
        }
    });

    contact.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (isLoading) return;

        setLoading(true);
        const submitBtn = contact.querySelector('button[type="submit"]');
        if (submitBtn) submitBtn.disabled = true;

        try {
            const payload = Object.fromEntries(new FormData(contact).entries());
            await post('/contact', { conversation_id: state.conversation_id, ...payload });
            contact.classList.remove('is-visible');
            contactConfig.has_contact = true;
            localStorage.setItem(storageKey, JSON.stringify(state));
            add('assistant', 'Thanks! Your contact details were saved.');
        } catch {
            add('assistant', 'Could not save your details. Please try again.');
        } finally {
            setLoading(false);
            if (submitBtn) submitBtn.disabled = false;
        }
    });

    function applyInitialOpen() {
        if (initialOpenApplied || !config.initial_open) return;
        initialOpenApplied = true;
        togglePanel(true);
    }

    async function togglePanel(forceOpen) {
        const next = typeof forceOpen === 'boolean' ? forceOpen : !isOpen;
        if (next === isOpen) return;

        isOpen = next;
        panel.classList.toggle('is-open', isOpen);
        button.classList.toggle('is-open', isOpen);
        button.classList.toggle('is-hidden', isOpen && isMobile());
        button.setAttribute('aria-expanded', String(isOpen));
        iconChat.style.display = isOpen ? 'none' : 'block';
        iconClose.style.display = isOpen ? 'block' : 'none';

        if (isOpen) {
            ensureInitialContent();

            if (!state.conversation_id) {
                await startConversation();
            }

            requestAnimationFrame(() => input.focus());
        }
    }

    function hasMessages() {
        return messages.querySelector('.crm-ai-msg') !== null;
    }

    function welcomeText() {
        return (
            state.welcome_message ||
            config.welcome_message ||
            'Hi! How can I help you today?'
        );
    }

    function ensureInitialContent() {
        if (hasMessages()) return;

        showWelcomeMessage();
    }

    function showWelcomeMessage(text) {
        messages.innerHTML = '';
        add('assistant', text || welcomeText());
    }

    function showConnectingState() {
        messages.innerHTML = `
            <div class="crm-ai-starting">
                <span class="crm-ai-typing" aria-hidden="true"><span></span><span></span><span></span></span>
                Connecting…
            </div>`;
    }

    async function startConversation() {
        if (isStarting) return;
        isStarting = true;
        setLoading(true);

        if (!hasMessages()) {
            showWelcomeMessage();
        }

        try {
            const res = await post('/start', startPayload());
            state.conversation_id = res.conversation_id;
            state.welcome_message = res.welcome_message || welcomeText();
            applyContactConfig(res);
            localStorage.setItem(storageKey, JSON.stringify(state));

            if (res.widget) {
                config = mergeConfig(config, res.widget);
                applyConfig(config);
            }

            showWelcomeMessage(state.welcome_message);

            if (res.collect_contact_on_start && shouldPromptContact(res)) {
                showContact();
            }
        } catch {
            showWelcomeMessage('Unable to start chat. Please refresh and try again.');
            state.conversation_id = null;
            delete state.welcome_message;
            localStorage.setItem(storageKey, JSON.stringify(state));
        } finally {
            isStarting = false;
            setLoading(false);
        }
    }

    async function loadRemoteConfig() {
        try {
            const url = apiBase + '/widget-config?bot_public_key=' + encodeURIComponent(botKey);
            const response = await fetch(url, { headers: { Accept: 'application/json' } });
            if (!response.ok) return {};
            const data = await response.json();
            return data.widget || {};
        } catch {
            return {};
        }
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
            body: JSON.stringify({
                bot_public_key: botKey,
                conversation_id: state.conversation_id,
                message: text,
            }),
        });

        if (!response.ok || !response.body) throw new Error('Stream failed');

        const reader = response.body.getReader();
        const decoder = new TextDecoder();
        let buffer = '';
        let hasContent = false;

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
                    if (!hasContent) {
                        clearLoading(node);
                        hasContent = true;
                    }
                    appendBubbleText(node, payload.delta);
                    scrollToBottom();
                }

                if (payload.type === 'meta' && shouldPromptContact(payload)) {
                    showContact();
                }
            }
        }

        if (!hasContent) {
            setBubbleText(node, 'Sorry, I could not generate a response.');
        }
    }

    function applyConfig(next) {
        const border = clamp(Number(next.border_radius), 0, 32);
        const launcher = clamp(Number(next.launcher_size), 44, 72);
        const panelWidth = clamp(Number(next.panel_width), 320, 480);
        const offsetX = clamp(Number(next.offset_x), 0, 80);
        const offsetY = clamp(Number(next.offset_y), 0, 80);
        const position = ['bottom-right', 'bottom-left', 'top-right', 'top-left'].includes(next.position)
            ? next.position
            : 'bottom-right';

        crmRoot.dataset.position = position;
        crmRoot.style.setProperty('--crm-primary', next.primary_color);
        crmRoot.style.setProperty('--crm-accent', next.accent_color);
        crmRoot.style.setProperty('--crm-bg', next.background_color);
        crmRoot.style.setProperty('--crm-surface', next.surface_color);
        crmRoot.style.setProperty('--crm-text', next.text_color);
        crmRoot.style.setProperty('--crm-border', hexToRgba(next.text_color, 0.12));
        crmRoot.style.setProperty('--crm-muted', hexToRgba(next.text_color, 0.55));
        crmRoot.style.setProperty('--crm-radius', border + 'px');
        crmRoot.style.setProperty('--crm-offset-x', offsetX + 'px');
        crmRoot.style.setProperty('--crm-offset-y', offsetY + 'px');
        crmRoot.style.setProperty('--crm-panel-width', panelWidth + 'px');
        crmRoot.style.setProperty('--crm-launcher-size', launcher + 'px');
        crmRoot.style.setProperty('--crm-launcher-gap', '16px');
        crmRoot.style.setProperty('--crm-focus-ring', hexToRgba(next.accent_color, 0.15));

        headTitle.textContent = next.title || DEFAULT_CONFIG.title;
        headSub.textContent = next.subtitle || '';
        headSub.style.display = next.subtitle ? 'block' : 'none';
        input.placeholder = next.input_placeholder || DEFAULT_CONFIG.input_placeholder;
        sendBtn.textContent = next.send_button_label || DEFAULT_CONFIG.send_button_label;

        iconChat.innerHTML = LAUNCHER_ICONS[next.launcher_icon] || LAUNCHER_ICONS.chat;
    }

    function resolveApiBase(script) {
        const path = script.dataset.apiBase || 'api/public/chat';

        return new URL(path, script.src).toString().replace(/\/$/, '');
    }

    function parseDatasetConfig(dataset) {
        return mergeConfig(DEFAULT_CONFIG, {
            title: dataset.title,
            subtitle: dataset.subtitle,
            primary_color: dataset.primaryColor,
            accent_color: dataset.accentColor,
            background_color: dataset.backgroundColor,
            surface_color: dataset.surfaceColor,
            text_color: dataset.textColor,
            position: dataset.position,
            offset_x: dataset.offsetX ? Number(dataset.offsetX) : undefined,
            offset_y: dataset.offsetY ? Number(dataset.offsetY) : undefined,
            border_radius: dataset.borderRadius ? Number(dataset.borderRadius) : undefined,
            panel_width: dataset.panelWidth ? Number(dataset.panelWidth) : undefined,
            launcher_size: dataset.launcherSize ? Number(dataset.launcherSize) : undefined,
            send_button_label: dataset.sendButtonLabel,
            input_placeholder: dataset.inputPlaceholder,
            launcher_icon: dataset.launcherIcon,
            initial_open: parseBoolean(dataset.initialOpen),
        });
    }

    function parseBoolean(value) {
        if (value === undefined || value === null || value === '') return undefined;
        return value === true || value === 'true' || value === '1';
    }

    function mergeConfig(base, patch) {
        const merged = { ...base };
        Object.keys(patch || {}).forEach((key) => {
            if (patch[key] === undefined || patch[key] === null || patch[key] === '') return;
            if (key === 'initial_open') {
                merged[key] = parseBoolean(patch[key]) ?? false;
                return;
            }
            merged[key] = patch[key];
        });
        return merged;
    }

    function setLoading(loading) {
        isLoading = loading;
        sendBtn.disabled = loading || isStarting;
        input.disabled = loading || isStarting;
    }

    function showContact() {
        if (contactConfig.has_contact || !contactConfig.fields.length) return;
        contact.classList.add('is-visible');
        scrollToBottom();
    }

    function shouldPromptContact(payload) {
        if (contactConfig.has_contact || !contactConfig.fields.length) return false;
        return Boolean(payload && (payload.fallback || payload.needs_contact));
    }

    function applyContactConfig(res) {
        contactConfig.fields = Array.isArray(res.contact_fields) ? res.contact_fields : ['name', 'email'];
        contactConfig.required = Array.isArray(res.contact_required) ? res.contact_required : ['email'];
        contactConfig.has_contact = Boolean(res.has_contact);
        contactConfig.collect_on_start = Boolean(res.collect_contact_on_start);
        renderContactForm(res.contact || {});
    }

    function renderContactForm(values) {
        const fieldDefs = {
            name: { name: 'visitor_name', type: 'text', placeholder: 'Name', autocomplete: 'name' },
            email: { name: 'visitor_email', type: 'email', placeholder: 'Email', autocomplete: 'email' },
            phone: { name: 'visitor_phone', type: 'tel', placeholder: 'Phone', autocomplete: 'tel' },
        };

        const inputs = contactConfig.fields
            .filter((field) => fieldDefs[field])
            .map((field) => {
                const def = fieldDefs[field];
                const required = contactConfig.required.includes(field) ? ' required' : '';
                const value = values[field] ? ' value="' + escapeAttr(values[field]) + '"' : '';
                return (
                    '<input name="' +
                    def.name +
                    '" type="' +
                    def.type +
                    '" placeholder="' +
                    def.placeholder +
                    (contactConfig.required.includes(field) ? ' *' : '') +
                    '" autocomplete="' +
                    def.autocomplete +
                    '"' +
                    required +
                    value +
                    ' />'
                );
            })
            .join('');

        contact.innerHTML =
            '<p class="crm-ai-contact-title">Leave your details</p>' +
            '<p class="crm-ai-contact-hint">So our team can follow up with you.</p>' +
            inputs +
            '<button type="submit">Submit</button>';
    }

    function escapeAttr(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/</g, '&lt;');
    }

    function startPayload() {
        const utm = parseUtmParams();
        let timezone = null;
        try {
            timezone = Intl.DateTimeFormat().resolvedOptions().timeZone;
        } catch {
            timezone = null;
        }

        return {
            visitor_id: state.visitor_id,
            source_url: location.href,
            referrer_url: document.referrer || null,
            timezone,
            utm_source: utm.source,
            utm_medium: utm.medium,
            utm_campaign: utm.campaign,
        };
    }

    function parseUtmParams() {
        try {
            const params = new URL(location.href).searchParams;
            return {
                source: params.get('utm_source'),
                medium: params.get('utm_medium'),
                campaign: params.get('utm_campaign'),
            };
        } catch {
            return { source: null, medium: null, campaign: null };
        }
    }

    function scrollToBottom() {
        messages.scrollTop = messages.scrollHeight;
    }

    function add(role, text, options = {}) {
        const wrap = document.createElement('div');
        wrap.className = 'crm-ai-msg crm-ai-' + role;

        const bubble = document.createElement('div');
        bubble.className = 'crm-ai-bubble';

        if (options.loading) {
            bubble.innerHTML = '<span class="crm-ai-typing" aria-label="Assistant is typing"><span></span><span></span><span></span></span>';
            bubble.dataset.loading = 'true';
        } else {
            bubble.textContent = text;
        }

        wrap.appendChild(bubble);
        messages.appendChild(wrap);
        scrollToBottom();

        return wrap;
    }

    function getBubble(node) {
        return node.querySelector('.crm-ai-bubble');
    }

    function clearLoading(node) {
        const bubble = getBubble(node);
        if (!bubble || bubble.dataset.loading !== 'true') return;
        delete bubble.dataset.loading;
        bubble.textContent = '';
    }

    function setBubbleText(node, text) {
        const bubble = getBubble(node);
        if (!bubble) return;
        delete bubble.dataset.loading;
        bubble.textContent = text;
        scrollToBottom();
    }

    function appendBubbleText(node, delta) {
        const bubble = getBubble(node);
        if (!bubble) return;
        bubble.textContent += delta;
    }

    function clamp(value, min, max) {
        return Math.min(max, Math.max(min, Number.isFinite(value) ? value : min));
    }

    function hexToRgba(hex, alpha) {
        const normalized = String(hex || '#111827').replace('#', '');
        if (normalized.length !== 6) return 'rgba(17, 24, 39, ' + alpha + ')';
        const r = parseInt(normalized.slice(0, 2), 16);
        const g = parseInt(normalized.slice(2, 4), 16);
        const b = parseInt(normalized.slice(4, 6), 16);
        return 'rgba(' + r + ', ' + g + ', ' + b + ', ' + alpha + ')';
    }

    function buildMarkup() {
        return `
        <style>
            .crm-ai-root *, .crm-ai-root *::before, .crm-ai-root *::after { box-sizing: border-box; }
            .crm-ai-root {
                --crm-primary: #111827;
                --crm-primary-hover: color-mix(in srgb, var(--crm-primary) 88%, #000);
                --crm-accent: #2563eb;
                --crm-accent-hover: color-mix(in srgb, var(--crm-accent) 88%, #000);
                --crm-bg: #f3f4f6;
                --crm-surface: #ffffff;
                --crm-border: #e5e7eb;
                --crm-muted: #6b7280;
                --crm-text: #111827;
                --crm-radius: 16px;
                --crm-shadow: 0 20px 50px rgba(15, 23, 42, 0.18);
                --crm-offset-x: 16px;
                --crm-offset-y: 16px;
                --crm-panel-width: 400px;
                --crm-launcher-size: 56px;
                --crm-launcher-gap: 16px;
                --crm-focus-ring: rgba(37, 99, 235, 0.15);
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
                -webkit-font-smoothing: antialiased;
            }
            .crm-ai-btn {
                position: fixed;
                z-index: 2147483647;
                border: 0;
                border-radius: 999px;
                background: var(--crm-primary);
                color: #fff;
                width: var(--crm-launcher-size);
                height: var(--crm-launcher-size);
                display: flex;
                align-items: center;
                justify-content: center;
                box-shadow: 0 10px 28px rgba(17, 24, 39, 0.28);
                cursor: pointer;
                transition: transform 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
            }
            .crm-ai-root[data-position="bottom-right"] .crm-ai-btn {
                right: max(var(--crm-offset-x), env(safe-area-inset-right));
                bottom: max(var(--crm-offset-y), env(safe-area-inset-bottom));
            }
            .crm-ai-root[data-position="bottom-left"] .crm-ai-btn {
                left: max(var(--crm-offset-x), env(safe-area-inset-left));
                bottom: max(var(--crm-offset-y), env(safe-area-inset-bottom));
            }
            .crm-ai-root[data-position="top-right"] .crm-ai-btn {
                right: max(var(--crm-offset-x), env(safe-area-inset-right));
                top: max(var(--crm-offset-y), env(safe-area-inset-top));
            }
            .crm-ai-root[data-position="top-left"] .crm-ai-btn {
                left: max(var(--crm-offset-x), env(safe-area-inset-left));
                top: max(var(--crm-offset-y), env(safe-area-inset-top));
            }
            .crm-ai-btn:hover { background: var(--crm-primary-hover); transform: scale(1.04); }
            .crm-ai-btn:active { transform: scale(0.98); }
            .crm-ai-btn svg { width: calc(var(--crm-launcher-size) * 0.46); height: calc(var(--crm-launcher-size) * 0.46); fill: currentColor; }
            .crm-ai-btn.is-open { background: var(--crm-muted); }
            .crm-ai-panel {
                position: fixed;
                z-index: 2147483647;
                width: min(var(--crm-panel-width), calc(100vw - 32px));
                height: min(560px, calc(100dvh - 120px));
                display: none;
                flex-direction: column;
                background: var(--crm-surface);
                border: 1px solid var(--crm-border);
                border-radius: var(--crm-radius);
                box-shadow: var(--crm-shadow);
                color: var(--crm-text);
                overflow: hidden;
                opacity: 0;
                transform: translateY(12px) scale(0.98);
                transition: opacity 0.22s ease, transform 0.22s ease;
            }
            .crm-ai-root[data-position="bottom-right"] .crm-ai-panel {
                right: max(var(--crm-offset-x), env(safe-area-inset-right));
                bottom: calc(max(var(--crm-offset-y), env(safe-area-inset-bottom)) + var(--crm-launcher-size) + var(--crm-launcher-gap));
            }
            .crm-ai-root[data-position="bottom-left"] .crm-ai-panel {
                left: max(var(--crm-offset-x), env(safe-area-inset-left));
                bottom: calc(max(var(--crm-offset-y), env(safe-area-inset-bottom)) + var(--crm-launcher-size) + var(--crm-launcher-gap));
            }
            .crm-ai-root[data-position="top-right"] .crm-ai-panel {
                right: max(var(--crm-offset-x), env(safe-area-inset-right));
                top: calc(max(var(--crm-offset-y), env(safe-area-inset-top)) + var(--crm-launcher-size) + var(--crm-launcher-gap));
            }
            .crm-ai-root[data-position="top-left"] .crm-ai-panel {
                left: max(var(--crm-offset-x), env(safe-area-inset-left));
                top: calc(max(var(--crm-offset-y), env(safe-area-inset-top)) + var(--crm-launcher-size) + var(--crm-launcher-gap));
            }
            .crm-ai-panel.is-open {
                display: flex;
                opacity: 1;
                transform: translateY(0) scale(1);
            }
            .crm-ai-head {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 12px;
                padding: 14px 16px;
                background: var(--crm-primary);
                color: #fff;
                flex-shrink: 0;
            }
            .crm-ai-head-info { min-width: 0; }
            .crm-ai-head-title { font-size: 15px; font-weight: 600; line-height: 1.3; }
            .crm-ai-head-sub { font-size: 12px; opacity: 0.75; margin-top: 2px; }
            .crm-ai-close {
                flex-shrink: 0;
                border: 0;
                background: rgba(255,255,255,0.12);
                color: #fff;
                width: 32px;
                height: 32px;
                border-radius: 8px;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                transition: background 0.15s ease;
            }
            .crm-ai-close:hover { background: rgba(255,255,255,0.2); }
            .crm-ai-close svg { width: 18px; height: 18px; stroke: currentColor; fill: none; stroke-width: 2; }
            .crm-ai-msgs {
                flex: 1;
                min-height: 0;
                padding: 16px;
                overflow-y: auto;
                overflow-x: hidden;
                background: var(--crm-bg);
                scroll-behavior: smooth;
                -webkit-overflow-scrolling: touch;
            }
            .crm-ai-msg {
                margin: 0 0 12px;
                display: flex;
                flex-direction: column;
                max-width: 88%;
            }
            .crm-ai-msg:last-child { margin-bottom: 0; }
            .crm-ai-user { margin-left: auto; align-items: flex-end; }
            .crm-ai-assistant { align-items: flex-start; }
            .crm-ai-bubble {
                padding: 10px 14px;
                border-radius: 14px;
                line-height: 1.45;
                font-size: 14px;
                word-wrap: break-word;
                overflow-wrap: anywhere;
            }
            .crm-ai-user .crm-ai-bubble {
                background: var(--crm-accent);
                color: #fff;
                border-bottom-right-radius: 4px;
            }
            .crm-ai-assistant .crm-ai-bubble {
                background: var(--crm-surface);
                border: 1px solid var(--crm-border);
                color: var(--crm-text);
                border-bottom-left-radius: 4px;
            }
            .crm-ai-typing {
                display: inline-flex;
                align-items: center;
                gap: 5px;
                padding: 12px 14px;
                min-height: 40px;
            }
            .crm-ai-typing span {
                width: 7px;
                height: 7px;
                border-radius: 50%;
                background: var(--crm-muted);
                animation: crm-ai-bounce 1.2s infinite ease-in-out;
            }
            .crm-ai-typing span:nth-child(2) { animation-delay: 0.15s; }
            .crm-ai-typing span:nth-child(3) { animation-delay: 0.3s; }
            @keyframes crm-ai-bounce {
                0%, 60%, 100% { transform: translateY(0); opacity: 0.45; }
                30% { transform: translateY(-5px); opacity: 1; }
            }
            .crm-ai-form, .crm-ai-contact {
                display: flex;
                flex-direction: column;
                gap: 8px;
                padding: 12px;
                border-top: 1px solid var(--crm-border);
                background: var(--crm-surface);
                flex-shrink: 0;
            }
            .crm-ai-form-row { display: flex; gap: 8px; align-items: flex-end; }
            .crm-ai-form input, .crm-ai-contact input {
                width: 100%;
                border: 1px solid var(--crm-border);
                border-radius: 10px;
                padding: 11px 12px;
                font: inherit;
                font-size: 16px;
                color: var(--crm-text);
                background: var(--crm-surface);
                outline: none;
                transition: border-color 0.15s ease, box-shadow 0.15s ease;
            }
            .crm-ai-form input:focus, .crm-ai-contact input:focus {
                border-color: var(--crm-accent);
                box-shadow: 0 0 0 3px var(--crm-focus-ring);
            }
            .crm-ai-form input { flex: 1; min-width: 0; }
            .crm-ai-form button, .crm-ai-contact button {
                border: 0;
                border-radius: 10px;
                background: var(--crm-primary);
                color: #fff;
                padding: 11px 16px;
                font: inherit;
                font-size: 14px;
                font-weight: 600;
                cursor: pointer;
                flex-shrink: 0;
                transition: background 0.15s ease, opacity 0.15s ease;
            }
            .crm-ai-form button:hover:not(:disabled), .crm-ai-contact button:hover:not(:disabled) {
                background: var(--crm-primary-hover);
            }
            .crm-ai-form button:disabled, .crm-ai-contact button:disabled {
                opacity: 0.55;
                cursor: not-allowed;
            }
            .crm-ai-contact { display: none; }
            .crm-ai-contact.is-visible { display: flex; }
            .crm-ai-contact-title {
                font-size: 13px;
                font-weight: 600;
                color: var(--crm-text);
                margin: 0 0 2px;
            }
            .crm-ai-contact-hint {
                font-size: 12px;
                color: var(--crm-muted);
                margin: 0 0 4px;
            }
            .crm-ai-starting {
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
                padding: 24px;
                color: var(--crm-muted);
                font-size: 13px;
            }
            .crm-ai-starting .crm-ai-typing span { width: 6px; height: 6px; }
            .crm-ai-empty {
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                gap: 8px;
                min-height: 180px;
                padding: 24px 16px;
                text-align: center;
            }
            .crm-ai-empty-icon {
                display: flex;
                align-items: center;
                justify-content: center;
                width: 48px;
                height: 48px;
                border-radius: 999px;
                background: var(--crm-surface);
                border: 1px solid var(--crm-border);
                color: var(--crm-primary);
            }
            .crm-ai-empty-icon svg { width: 24px; height: 24px; fill: currentColor; }
            .crm-ai-empty-title {
                margin: 0;
                font-size: 15px;
                font-weight: 600;
                color: var(--crm-text);
            }
            .crm-ai-empty-text {
                margin: 0;
                max-width: 260px;
                font-size: 13px;
                line-height: 1.5;
                color: var(--crm-muted);
            }
            @media (max-width: 480px) {
                .crm-ai-panel {
                    left: 0 !important;
                    right: 0 !important;
                    top: auto !important;
                    bottom: 0 !important;
                    width: 100%;
                    height: 100dvh;
                    max-height: 100dvh;
                    border-radius: 0;
                    border: 0;
                    transform: translateY(100%);
                }
                .crm-ai-panel.is-open {
                    transform: translateY(0);
                }
                .crm-ai-btn.is-hidden { opacity: 0; pointer-events: none; transform: scale(0.8); }
                .crm-ai-head { padding-top: max(14px, env(safe-area-inset-top)); }
                .crm-ai-form, .crm-ai-contact {
                    padding-bottom: max(12px, env(safe-area-inset-bottom));
                }
            }
        </style>
        <div class="crm-ai-root" data-position="bottom-right">
            <button class="crm-ai-btn" type="button" aria-label="Open support chat" aria-expanded="false">
                <svg class="crm-ai-icon-chat" viewBox="0 0 24 24" aria-hidden="true"></svg>
                <svg class="crm-ai-icon-close" viewBox="0 0 24 24" aria-hidden="true" style="display:none"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
            </button>
            <section class="crm-ai-panel" role="dialog" aria-label="Support chat" aria-modal="true">
                <header class="crm-ai-head">
                    <div class="crm-ai-head-info">
                        <div class="crm-ai-head-title">Support</div>
                        <div class="crm-ai-head-sub">We typically reply instantly</div>
                    </div>
                    <button type="button" class="crm-ai-close" aria-label="Close chat">
                        <svg viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>
                    </button>
                </header>
                <div class="crm-ai-msgs" role="log" aria-live="polite" aria-relevant="additions"></div>
                <form class="crm-ai-form">
                    <div class="crm-ai-form-row">
                        <input type="text" maxlength="2000" placeholder="Type your message…" autocomplete="off" />
                        <button type="submit">Send</button>
                    </div>
                </form>
                <form class="crm-ai-contact"></form>
            </section>
        </div>`;
    }
})();
