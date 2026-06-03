/* MARKDOWN_FORMATTER_START */
function escapeHtml(text) {
    return String(text)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function sanitizeUrl(url) {
    const trimmed = String(url || '').trim();
    if (!trimmed) return null;
    try {
        const parsed = new URL(trimmed, 'https://example.invalid');
        if (parsed.protocol === 'http:' || parsed.protocol === 'https:') {
            return parsed.href;
        }
    } catch {
        return null;
    }
    return null;
}

function applyInlineMarkdown(text) {
    let html = text;

    html = html.replace(/`([^`\n]+)`/g, '<code>$1</code>');
    html = html.replace(/\*\*([^*\n]+)\*\*/g, '<strong>$1</strong>');
    html = html.replace(/__([^_\n]+)__/g, '<strong>$1</strong>');
    html = html.replace(/(?<!\*)\*([^*\n]+)\*(?!\*)/g, '<em>$1</em>');
    html = html.replace(/(?<!_)_([^_\n]+)_(?!_)/g, '<em>$1</em>');
    html = html.replace(/\[([^\]]+)\]\(([^)]+)\)/g, (_, label, url) => {
        const safeUrl = sanitizeUrl(url);
        if (!safeUrl) {
            return label;
        }
        return (
            '<a href="' +
            safeUrl.replace(/"/g, '&quot;') +
            '" target="_blank" rel="noopener noreferrer">' +
            label +
            '</a>'
        );
    });

    return html;
}

function formatMarkdown(text) {
    const source = String(text || '');
    if (!source.trim()) {
        return '';
    }

    const lines = source.replace(/\r\n/g, '\n').split('\n');
    const blocks = [];
    let paragraph = [];
    let listType = null;
    let listItems = [];
    let inCodeBlock = false;
    let codeLines = [];

    function flushParagraph() {
        if (!paragraph.length) {
            return;
        }
        blocks.push('<p>' + applyInlineMarkdown(escapeHtml(paragraph.join('\n'))).replace(/\n/g, '<br>') + '</p>');
        paragraph = [];
    }

    function flushList() {
        if (!listType || !listItems.length) {
            listType = null;
            listItems = [];
            return;
        }
        const tag = listType === 'ol' ? 'ol' : 'ul';
        blocks.push(
            '<' +
                tag +
                '>' +
                listItems.map((item) => '<li>' + applyInlineMarkdown(escapeHtml(item)) + '</li>').join('') +
                '</' +
                tag +
                '>'
        );
        listType = null;
        listItems = [];
    }

    function flushCodeBlock() {
        if (!codeLines.length) {
            return;
        }
        blocks.push('<pre><code>' + escapeHtml(codeLines.join('\n')) + '</code></pre>');
        codeLines = [];
    }

    for (const line of lines) {
        const fence = line.match(/^```(\w*)?$/);
        if (fence) {
            flushParagraph();
            flushList();
            if (inCodeBlock) {
                flushCodeBlock();
                inCodeBlock = false;
            } else {
                inCodeBlock = true;
            }
            continue;
        }

        if (inCodeBlock) {
            codeLines.push(line);
            continue;
        }

        const trimmed = line.trim();
        if (!trimmed) {
            flushParagraph();
            flushList();
            continue;
        }

        const heading = trimmed.match(/^(#{1,3})\s+(.+)$/);
        if (heading) {
            flushParagraph();
            flushList();
            const level = heading[1].length + 2;
            const tag = 'h' + Math.min(level, 6);
            blocks.push(
                '<' + tag + '>' + applyInlineMarkdown(escapeHtml(heading[2])) + '</' + tag + '>'
            );
            continue;
        }

        const ordered = trimmed.match(/^\d+\.\s+(.+)$/);
        if (ordered) {
            flushParagraph();
            if (listType && listType !== 'ol') {
                flushList();
            }
            listType = 'ol';
            listItems.push(ordered[1]);
            continue;
        }

        const unordered = trimmed.match(/^[-*+]\s+(.+)$/);
        if (unordered) {
            flushParagraph();
            if (listType && listType !== 'ul') {
                flushList();
            }
            listType = 'ul';
            listItems.push(unordered[1]);
            continue;
        }

        const quote = trimmed.match(/^>\s?(.+)$/);
        if (quote) {
            flushParagraph();
            flushList();
            blocks.push('<blockquote>' + applyInlineMarkdown(escapeHtml(quote[1])) + '</blockquote>');
            continue;
        }

        flushList();
        paragraph.push(line);
    }

    flushParagraph();
    flushList();
    if (inCodeBlock) {
        flushCodeBlock();
    }

    return blocks.join('');
}
/* MARKDOWN_FORMATTER_END */

/* CONTACT_HELPERS_START */
function contactCopy(reason) {
    if (reason === 'start') {
        return {
            title: 'Start the conversation',
            hint: 'Share your details so our team can follow up if needed.',
            submit: 'Continue',
            privacy: 'We only use this to respond to your chat.',
        };
    }

    return {
        title: 'Stay in touch',
        hint: 'Leave your details and our team will follow up on this conversation.',
        submit: 'Send details',
        privacy: 'We only use this to respond to your chat.',
    };
}

function validateContactPayload(fields, required, payload) {
    const errors = {};
    const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    for (const field of fields) {
        const key = field === 'name' ? 'visitor_name' : field === 'email' ? 'visitor_email' : 'visitor_phone';
        const raw = payload[key];
        const value = typeof raw === 'string' ? raw.trim() : '';

        if (required.includes(field) && !value) {
            const label = field === 'name' ? 'Name' : field === 'email' ? 'Email' : 'Phone';
            errors[key] = label + ' is required.';
            continue;
        }

        if (field === 'email' && value && !emailPattern.test(value)) {
            errors[key] = 'Enter a valid email address.';
        }
    }

    if (Object.keys(errors).length === 0) {
        const hasValue = fields.some((field) => {
            const key = field === 'name' ? 'visitor_name' : field === 'email' ? 'visitor_email' : 'visitor_phone';
            const value = payload[key];
            return typeof value === 'string' && value.trim() !== '';
        });

        if (!hasValue && fields.length) {
            errors._form = 'Please fill in at least one field.';
        }
    }

    return errors;
}

function mapContactFieldErrors(apiErrors) {
    const mapped = {};
    if (!apiErrors || typeof apiErrors !== 'object') {
        return mapped;
    }

    Object.keys(apiErrors).forEach((key) => {
        const messages = apiErrors[key];
        if (Array.isArray(messages) && messages[0]) {
            mapped[key] = String(messages[0]);
        }
    });

    return mapped;
}
/* CONTACT_HELPERS_END */

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
        has_contact: Boolean(state.has_contact),
        collect_on_start: false,
        reason: null,
    };
    let contactErrors = {};
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
        config = mergeConfig(config, remote.widget || {});
        applyConfig(config);
        applyRemoteContactConfig(remote);

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
                if (shouldPromptContact(res)) showContact('fallback');
            } catch {
                setBubbleText(assistant, 'Support is unavailable right now. Please try again later.');
            }
        } finally {
            setLoading(false);
        }
    });

    contact.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (isLoading || contactConfig.has_contact) return;

        const payload = Object.fromEntries(new FormData(contact).entries());
        contactErrors = validateContactPayload(contactConfig.fields, contactConfig.required, payload);

        if (Object.keys(contactErrors).length) {
            renderContactForm(getContactFormValues());
            return;
        }

        setContactSubmitting(true);

        try {
            await post('/contact', { conversation_id: state.conversation_id, ...payload });
            completeContactSubmission();
        } catch (error) {
            const apiErrors = mapContactFieldErrors(error && error.data && error.data.errors);
            contactErrors = Object.keys(apiErrors).length
                ? apiErrors
                : { _form: 'Could not save your details. Please try again.' };
            renderContactForm(getContactFormValues());
        } finally {
            setContactSubmitting(false);
        }
    });

    contact.addEventListener('click', (event) => {
        const skip = event.target.closest('[data-contact-skip]');
        if (!skip || contactConfig.required.length) return;
        hideContact();
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
                showContact('start');
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
            return await response.json();
        } catch {
            return {};
        }
    }

    function applyRemoteContactConfig(remote) {
        if (!remote || typeof remote !== 'object') return;

        if (Array.isArray(remote.contact_fields)) {
            contactConfig.fields = remote.contact_fields;
        }
        if (Array.isArray(remote.contact_required)) {
            contactConfig.required = remote.contact_required;
        }
        if (typeof remote.collect_contact_on_start === 'boolean') {
            contactConfig.collect_on_start = remote.collect_contact_on_start;
        }

        renderContactForm({});
    }

    async function post(path, body) {
        const response = await fetch(apiBase + path, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            body: JSON.stringify({ bot_public_key: botKey, ...body }),
        });

        if (!response.ok) {
            const error = new Error('Request failed');
            try {
                error.data = await response.json();
            } catch {
                error.data = null;
            }
            throw error;
        }

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
                    showContact('fallback');
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
        updateChatAvailability();
    }

    function chatBlockedByContact() {
        return (
            contact.classList.contains('is-visible') &&
            contactConfig.collect_on_start &&
            !contactConfig.has_contact
        );
    }

    function updateChatAvailability() {
        const blocked = chatBlockedByContact();
        const disabled = isLoading || isStarting || blocked;
        sendBtn.disabled = disabled;
        input.disabled = disabled;
        form.classList.toggle('is-blocked', blocked);
        panel.classList.toggle('crm-ai-panel--contact', contact.classList.contains('is-visible'));
    }

    function showContact(reason) {
        if (contactConfig.has_contact || !contactConfig.fields.length) return;

        contactConfig.reason = reason === 'start' ? 'start' : 'fallback';
        contactErrors = {};
        contact.classList.add('is-visible');
        renderContactForm(getContactFormValues());
        updateChatAvailability();
        scrollToBottom();

        const firstInput = contact.querySelector('input:not([disabled])');
        if (firstInput) {
            requestAnimationFrame(() => firstInput.focus());
        }
    }

    function hideContact() {
        contact.classList.remove('is-visible');
        contactConfig.reason = null;
        contactErrors = {};
        updateChatAvailability();
    }

    function completeContactSubmission() {
        contactConfig.has_contact = true;
        state.has_contact = true;
        localStorage.setItem(storageKey, JSON.stringify(state));
        updateChatAvailability();

        const card = contact.querySelector('.crm-ai-contact-card');
        if (card) {
            card.classList.add('is-success');
            card.innerHTML =
                '<div class="crm-ai-contact-success">' +
                '<span class="crm-ai-contact-success-icon" aria-hidden="true">' +
                '<svg viewBox="0 0 24 24"><path d="M9 16.2 4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4z"/></svg>' +
                '</span>' +
                '<p class="crm-ai-contact-success-title">Details saved</p>' +
                '<p class="crm-ai-contact-success-text">Thanks — our team can follow up when needed.</p>' +
                '</div>';
        }

        window.setTimeout(() => {
            hideContact();
            requestAnimationFrame(() => input.focus());
        }, 1400);
    }

    function setContactSubmitting(submitting) {
        contact.classList.toggle('is-submitting', submitting);
        const submitBtn = contact.querySelector('button[type="submit"]');
        if (submitBtn) submitBtn.disabled = submitting;
        contact.querySelectorAll('input').forEach((field) => {
            field.disabled = submitting;
        });
        setLoading(submitting);
    }

    function getContactFormValues() {
        const values = {};
        contactConfig.fields.forEach((field) => {
            const key = field === 'name' ? 'visitor_name' : field === 'email' ? 'visitor_email' : 'visitor_phone';
            const input = contact.querySelector('[name="' + key + '"]');
            if (input && input.value) {
                values[field] = input.value;
            }
        });
        return values;
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

        if (res.has_contact) {
            state.has_contact = true;
            localStorage.setItem(storageKey, JSON.stringify(state));
            hideContact();
        }

        renderContactForm(res.contact || {});
        updateChatAvailability();
    }

    function renderContactForm(values) {
        if (contactConfig.has_contact) {
            contact.innerHTML = '';
            return;
        }

        const copy = contactCopy(contactConfig.reason || 'fallback');
        const fieldDefs = {
            name: {
                name: 'visitor_name',
                type: 'text',
                label: 'Name',
                placeholder: 'Jane Smith',
                autocomplete: 'name',
            },
            email: {
                name: 'visitor_email',
                type: 'email',
                label: 'Email',
                placeholder: 'you@company.com',
                autocomplete: 'email',
            },
            phone: {
                name: 'visitor_phone',
                type: 'tel',
                label: 'Phone',
                placeholder: '+1 555 0100',
                autocomplete: 'tel',
            },
        };

        const fields = contactConfig.fields
            .filter((field) => fieldDefs[field])
            .map((field) => {
                const def = fieldDefs[field];
                const isRequired = contactConfig.required.includes(field);
                const stored = values[field] || '';
                const error = contactErrors[def.name] || '';
                const invalid = error ? ' aria-invalid="true"' : '';
                const describedBy = error ? ' aria-describedby="crm-ai-error-' + def.name + '"' : '';

                return (
                    '<label class="crm-ai-field">' +
                    '<span class="crm-ai-field-label">' +
                    def.label +
                    (isRequired ? ' <span class="crm-ai-required">*</span>' : '') +
                    '</span>' +
                    '<input name="' +
                    def.name +
                    '" type="' +
                    def.type +
                    '" placeholder="' +
                    def.placeholder +
                    '" autocomplete="' +
                    def.autocomplete +
                    '"' +
                    (isRequired ? ' required' : '') +
                    (stored ? ' value="' + escapeAttr(stored) + '"' : '') +
                    invalid +
                    describedBy +
                    ' />' +
                    (error
                        ? '<span id="crm-ai-error-' +
                          def.name +
                          '" class="crm-ai-field-error" role="alert">' +
                          escapeHtml(error) +
                          '</span>'
                        : '') +
                    '</label>'
                );
            })
            .join('');

        const formError = contactErrors._form
            ? '<p class="crm-ai-contact-form-error" role="alert">' + escapeHtml(contactErrors._form) + '</p>'
            : '';
        const canSkip = contactConfig.required.length === 0;
        const skipBtn = canSkip
            ? '<button type="button" class="crm-ai-contact-skip" data-contact-skip>Not now</button>'
            : '';

        contact.innerHTML =
            '<div class="crm-ai-contact-card">' +
            '<div class="crm-ai-contact-header">' +
            '<span class="crm-ai-contact-icon" aria-hidden="true">' +
            '<svg viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>' +
            '</span>' +
            '<div class="crm-ai-contact-headings">' +
            '<p class="crm-ai-contact-title">' +
            escapeHtml(copy.title) +
            '</p>' +
            '<p class="crm-ai-contact-hint">' +
            escapeHtml(copy.hint) +
            '</p>' +
            '</div>' +
            '</div>' +
            '<div class="crm-ai-contact-fields">' +
            fields +
            '</div>' +
            formError +
            '<div class="crm-ai-contact-actions">' +
            '<button type="submit">' +
            escapeHtml(copy.submit) +
            '</button>' +
            skipBtn +
            '</div>' +
            '<p class="crm-ai-contact-privacy">' +
            escapeHtml(copy.privacy) +
            '</p>' +
            '</div>';
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
            setBubbleContent(bubble, text, role);
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
        delete bubble.dataset.rawText;
        bubble.textContent = '';
        bubble.classList.remove('crm-ai-formatted');
    }

    function messageRole(node) {
        const wrap = node.classList && node.classList.contains('crm-ai-msg') ? node : node.closest('.crm-ai-msg');
        if (!wrap) return 'assistant';
        return wrap.classList.contains('crm-ai-user') ? 'user' : 'assistant';
    }

    function setBubbleContent(bubble, text, role) {
        const raw = String(text || '');
        if (role === 'user') {
            delete bubble.dataset.rawText;
            bubble.classList.remove('crm-ai-formatted');
            bubble.textContent = raw;
            return;
        }

        bubble.dataset.rawText = raw;
        bubble.classList.add('crm-ai-formatted');
        bubble.innerHTML = formatMarkdown(raw);
    }

    function setBubbleText(node, text) {
        const bubble = getBubble(node);
        if (!bubble) return;
        delete bubble.dataset.loading;
        setBubbleContent(bubble, text, messageRole(node));
        scrollToBottom();
    }

    function appendBubbleText(node, delta) {
        const bubble = getBubble(node);
        if (!bubble) return;
        const role = messageRole(node);
        const raw = (bubble.dataset.rawText || bubble.textContent || '') + delta;
        setBubbleContent(bubble, raw, role);
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
            .crm-ai-bubble.crm-ai-formatted > :first-child { margin-top: 0; }
            .crm-ai-bubble.crm-ai-formatted > :last-child { margin-bottom: 0; }
            .crm-ai-bubble.crm-ai-formatted p,
            .crm-ai-bubble.crm-ai-formatted ul,
            .crm-ai-bubble.crm-ai-formatted ol,
            .crm-ai-bubble.crm-ai-formatted pre,
            .crm-ai-bubble.crm-ai-formatted blockquote,
            .crm-ai-bubble.crm-ai-formatted h3,
            .crm-ai-bubble.crm-ai-formatted h4,
            .crm-ai-bubble.crm-ai-formatted h5 {
                margin: 0 0 0.55em;
            }
            .crm-ai-bubble.crm-ai-formatted h3,
            .crm-ai-bubble.crm-ai-formatted h4,
            .crm-ai-bubble.crm-ai-formatted h5 {
                font-size: inherit;
                font-weight: 600;
                line-height: 1.35;
            }
            .crm-ai-bubble.crm-ai-formatted ul,
            .crm-ai-bubble.crm-ai-formatted ol {
                padding-left: 1.25em;
            }
            .crm-ai-bubble.crm-ai-formatted li + li { margin-top: 0.2em; }
            .crm-ai-bubble.crm-ai-formatted code {
                font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
                font-size: 0.92em;
                background: color-mix(in srgb, var(--crm-text) 8%, transparent);
                padding: 0.1em 0.35em;
                border-radius: 4px;
            }
            .crm-ai-bubble.crm-ai-formatted pre {
                margin: 0 0 0.55em;
                padding: 10px 12px;
                border-radius: 8px;
                background: color-mix(in srgb, var(--crm-text) 8%, transparent);
                overflow-x: auto;
            }
            .crm-ai-bubble.crm-ai-formatted pre code {
                display: block;
                padding: 0;
                background: transparent;
                white-space: pre-wrap;
                word-break: break-word;
            }
            .crm-ai-bubble.crm-ai-formatted blockquote {
                margin: 0 0 0.55em;
                padding-left: 10px;
                border-left: 3px solid var(--crm-border);
                color: var(--crm-muted);
            }
            .crm-ai-assistant .crm-ai-bubble.crm-ai-formatted a {
                color: var(--crm-accent);
                text-decoration: underline;
                text-underline-offset: 2px;
            }
            .crm-ai-user .crm-ai-bubble.crm-ai-formatted a {
                color: #fff;
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
            .crm-ai-contact {
                display: none;
                padding: 0 12px 12px;
                border-top: 0;
                background: transparent;
            }
            .crm-ai-contact.is-visible { display: block; }
            .crm-ai-panel--contact .crm-ai-msgs {
                padding-bottom: 8px;
            }
            .crm-ai-form.is-blocked {
                opacity: 0.55;
                pointer-events: none;
            }
            .crm-ai-contact-card {
                display: flex;
                flex-direction: column;
                gap: 12px;
                padding: 14px;
                border: 1px solid color-mix(in srgb, var(--crm-accent) 28%, var(--crm-border));
                border-radius: 12px;
                background: var(--crm-surface);
                box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08);
            }
            .crm-ai-contact-card.is-success {
                border-color: color-mix(in srgb, #059669 35%, var(--crm-border));
            }
            .crm-ai-contact-header {
                display: flex;
                gap: 10px;
                align-items: flex-start;
            }
            .crm-ai-contact-icon {
                flex-shrink: 0;
                display: flex;
                align-items: center;
                justify-content: center;
                width: 36px;
                height: 36px;
                border-radius: 10px;
                background: color-mix(in srgb, var(--crm-accent) 12%, var(--crm-surface));
                color: var(--crm-accent);
            }
            .crm-ai-contact-icon svg { width: 20px; height: 20px; fill: currentColor; }
            .crm-ai-contact-headings { min-width: 0; }
            .crm-ai-contact-title {
                font-size: 14px;
                font-weight: 600;
                color: var(--crm-text);
                margin: 0;
                line-height: 1.35;
            }
            .crm-ai-contact-hint {
                font-size: 12px;
                color: var(--crm-muted);
                margin: 4px 0 0;
                line-height: 1.45;
            }
            .crm-ai-contact-fields {
                display: flex;
                flex-direction: column;
                gap: 10px;
            }
            .crm-ai-field {
                display: flex;
                flex-direction: column;
                gap: 4px;
            }
            .crm-ai-field-label {
                font-size: 12px;
                font-weight: 500;
                color: var(--crm-text);
            }
            .crm-ai-required { color: var(--crm-accent); }
            .crm-ai-field input[aria-invalid="true"] {
                border-color: #dc2626;
                box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.12);
            }
            .crm-ai-field-error,
            .crm-ai-contact-form-error {
                font-size: 11px;
                line-height: 1.4;
                color: #dc2626;
                margin: 0;
            }
            .crm-ai-contact-actions {
                display: flex;
                flex-wrap: wrap;
                gap: 8px;
                align-items: center;
            }
            .crm-ai-contact-actions button[type="submit"] {
                flex: 1;
                min-width: 120px;
            }
            .crm-ai-contact-skip {
                border: 0;
                background: transparent;
                color: var(--crm-muted);
                padding: 8px 4px;
                font: inherit;
                font-size: 12px;
                font-weight: 500;
                cursor: pointer;
                text-decoration: underline;
                text-underline-offset: 2px;
            }
            .crm-ai-contact-skip:hover { color: var(--crm-text); }
            .crm-ai-contact-privacy {
                font-size: 11px;
                color: var(--crm-muted);
                margin: 0;
                line-height: 1.4;
            }
            .crm-ai-contact.is-submitting button[type="submit"] {
                opacity: 0.7;
                cursor: wait;
            }
            .crm-ai-contact-success {
                display: flex;
                flex-direction: column;
                align-items: center;
                text-align: center;
                gap: 6px;
                padding: 8px 4px;
            }
            .crm-ai-contact-success-icon {
                display: flex;
                align-items: center;
                justify-content: center;
                width: 40px;
                height: 40px;
                border-radius: 999px;
                background: color-mix(in srgb, #059669 14%, var(--crm-surface));
                color: #059669;
            }
            .crm-ai-contact-success-icon svg { width: 22px; height: 22px; fill: currentColor; }
            .crm-ai-contact-success-title {
                margin: 0;
                font-size: 14px;
                font-weight: 600;
                color: var(--crm-text);
            }
            .crm-ai-contact-success-text {
                margin: 0;
                font-size: 12px;
                color: var(--crm-muted);
                line-height: 1.45;
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
                <form class="crm-ai-contact" aria-label="Contact details"></form>
                <form class="crm-ai-form">
                    <div class="crm-ai-form-row">
                        <input type="text" maxlength="2000" placeholder="Type your message…" autocomplete="off" />
                        <button type="submit">Send</button>
                    </div>
                </form>
            </section>
        </div>`;
    }
})();
