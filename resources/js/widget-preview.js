export function loadPreviewWidget(widgetUrl, onStatus, document = window.document) {
    const script = document.createElement('script');
    const browser = document.defaultView;
    let finished = false;
    let timer;

    const finish = (status) => {
        if (finished) return;
        finished = true;
        browser.clearTimeout(timer);
        observer.disconnect();
        onStatus(status);
    };
    const checkReady = () => {
        if (document.querySelector('.crm-ai-root[data-config-ready="true"]')) finish('ready');
    };
    const observer = new browser.MutationObserver(checkReady);
    observer.observe(document.body, { childList: true, subtree: true, attributes: true, attributeFilter: ['data-config-ready'] });

    script.src = widgetUrl;
    script.async = true;
    script.onload = checkReady;
    script.onerror = () => finish('error');
    onStatus('loading');
    timer = browser.setTimeout(() => finish('error'), 15000);
    document.body.appendChild(script);

    return () => {
        finished = true;
        browser.clearTimeout(timer);
        observer.disconnect();
        script.onload = null;
        script.onerror = null;
        script.remove();
    };
}

export function openPreviewChat(document = window.document) {
    const root = document.querySelector('.crm-ai-root[data-config-ready="true"]');
    if (!root || root.querySelector('.crm-ai-panel.is-open')) return;
    root.querySelector('.crm-ai-btn')?.click();
}
