const fs = require('fs');
const path = require('path');
const vm = require('vm');

const widgetPath = path.resolve(__dirname, '../../public/widget.js');
const widgetSource = fs.readFileSync(widgetPath, 'utf8');
const match = widgetSource.match(/\/\* MARKDOWN_FORMATTER_START \*\/([\s\S]*?)\/\* MARKDOWN_FORMATTER_END \*/);

if (!match) {
    throw new Error('Markdown formatter block not found in public/widget.js');
}

const context = { console, URL };
vm.createContext(context);
vm.runInContext(match[1], context);

const { formatMarkdown, escapeHtml, messageDirection } = context;

function assertIncludes(actual, expected, label) {
    if (!actual.includes(expected)) {
        throw new Error(`${label}: expected ${JSON.stringify(actual)} to include ${JSON.stringify(expected)}`);
    }
}

assertIncludes(formatMarkdown('**Hello** world'), '<strong>Hello</strong>', 'bold');
assertIncludes(formatMarkdown('Visit [Docs](https://example.com/docs)'), 'href="https://example.com/docs"', 'safe link');
assertIncludes(formatMarkdown('Get [the guide](https://example.com/files/guide.pdf)'), 'download data-download-link="true"', 'download link');
assertIncludes(formatMarkdown('Get [the archive](https://example.com/files/export.zip?version=2)'), 'download data-download-link="true"', 'download link with query');
assertIncludes(formatMarkdown('Visit [X](javascript:alert(1))'), 'Visit X', 'blocked javascript link');
assertIncludes(formatMarkdown('Email support@example.com for help'), 'href="mailto:support@example.com"', 'plain email link');
assertIncludes(formatMarkdown('Call us at +1 (555) 123-4567'), 'href="tel:+15551234567"', 'plain phone link');
assertIncludes(formatMarkdown('[Call support](tel:+1 555 123 4567)'), 'href="tel:+15551234567"', 'markdown phone link');
assertIncludes(formatMarkdown('Email <script>@example.com'), '&lt;script&gt;@example.com', 'escaped invalid email');
assertIncludes(formatMarkdown('`code`'), '<code>code</code>', 'inline code');
assertIncludes(formatMarkdown('- one\n- two'), '<ul><li>one</li><li>two</li></ul>', 'unordered list');
assertIncludes(formatMarkdown('```\nline\n```'), '<pre><code>line</code></pre>', 'code block');
assertIncludes(escapeHtml('<script>'), '&lt;script&gt;', 'escape html');
if (messageDirection('سلام، چطور می‌توانم کمک کنم؟') !== 'rtl') {
    throw new Error('Persian message should use RTL direction');
}
if (messageDirection('مرحبا، كيف يمكنني المساعدة؟') !== 'rtl') {
    throw new Error('Arabic message should use RTL direction');
}
if (messageDirection('Hello, how can I help?') !== 'ltr') {
    throw new Error('English message should use LTR direction');
}

console.log('widget-markdown tests passed');

const assert = require('node:assert/strict');
assertIncludes(formatMarkdown('[Email us](mailto:support@example.com)'), 'href="mailto:support@example.com"', 'email action');
assertIncludes(formatMarkdown('[Email us](mailto:support@example.com)'), 'class="crm-ai-contact-action"', 'email action style');
assertIncludes(formatMarkdown('[WhatsApp](https://wa.me/15551234567)'), 'class="crm-ai-contact-action"', 'WhatsApp action');
assertIncludes(formatMarkdown('[WhatsApp](https://api.whatsapp.com/send?phone=15551234567&text=Hello)'), 'phone=15551234567&amp;text=Hello', 'WhatsApp query escaping');
assertIncludes(formatMarkdown('[Sales](reply:Talk%20to%20sales)'), 'data-reply="Talk%20to%20sales"', 'reply action payload');
assertIncludes(formatMarkdown('[Sales](reply:Sales)'), '<button type="button"', 'reply action button');
assertIncludes(formatMarkdown('[دعم](reply:%D8%AF%D8%B9%D9%85)'), 'data-reply="%D8%AF%D8%B9%D9%85"', 'localized reply');
assert.equal(formatMarkdown('[Broken](reply:%ZZ)').includes('<button'), false);
assert.equal(formatMarkdown('[Empty](reply:)').includes('<button'), false);
assert.equal(formatMarkdown('[Long](reply:' + 'a'.repeat(501) + ')').includes('<button'), false);
assert.equal(formatMarkdown('[Bad](reply:hello%0Aworld)').includes('<button'), false);
assert.equal(formatMarkdown('[Bad](mailto:person@example.com?bcc=other@example.com)').includes('href='), false);
assert.equal(formatMarkdown('[Fake](https://wa.me.evil.test/123)').includes('crm-ai-contact-action'), false);
assert.equal(formatMarkdown('[Fake](https://example.com/wa.me/123)').includes('crm-ai-contact-action'), false);
assert.equal(formatMarkdown('```\n[Sales](reply:Sales)\n```').includes('<button'), false, 'code blocks do not execute actions');
assert.equal(formatMarkdown('[<img src=x onerror=alert>](reply:%22%3E%3Cimg%3E)').includes('<img'), false, 'labels and values are escaped');

const actionFunction = widgetSource.slice(widgetSource.indexOf('    function handleReplyActionClick('), widgetSource.indexOf('    function isNearBottom('));
const sent = [];
const action = { dataset: { reply: 'Talk%20to%20sales' }, disabled: false };
const actionContext = vm.createContext({ messages: { contains: () => true }, handlePromptClick: (reply) => sent.push(reply) });
vm.runInContext(actionFunction, actionContext);
const click = () => actionContext.handleReplyActionClick({ target: { closest: () => action } });
click();
assert.deepEqual(sent, ['Talk to sales']);
action.disabled = true;
click();
assert.equal(sent.length, 1, 'disabled actions do not send');
action.disabled = false;
action.dataset.expired = 'true';
click();
assert.equal(sent.length, 1, 'expired choices do not send');
delete action.dataset.expired;
action.dataset.reply = '%ZZ';
click();
assert.equal(sent.length, 1, 'malformed choices do not send');

const availabilityFunction = widgetSource.slice(widgetSource.indexOf('    function updateChatAvailability('), widgetSource.indexOf('    function showContact('));
const availabilityContext = vm.createContext({
    isLoading: true, isStarting: false, isChangingLanguage: false,
    chatBlockedByContact: () => false, languageSelect: {}, sendBtn: {}, input: {},
    form: { classList: { toggle() {} } }, panel: { classList: { toggle() {} } }, contact: { classList: { contains: () => false } },
    messages: { querySelectorAll: () => [action] },
});
vm.runInContext(availabilityFunction, availabilityContext);
availabilityContext.updateChatAvailability();
assert.equal(action.disabled, true, 'actions disabled while streaming');
availabilityContext.isLoading = false;
availabilityContext.updateChatAvailability();
assert.equal(action.disabled, false, 'actions enabled after streaming');
action.dataset.expired = 'true';
availabilityContext.updateChatAvailability();
assert.equal(action.disabled, true, 'expired actions stay disabled');
console.log('Widget contact and reply actions passed');
assertIncludes(formatMarkdown('WhatsApp: https://wa.me/15551234567.'), 'href="https://wa.me/15551234567"', 'plain WhatsApp action');
assert.equal(formatMarkdown('`[Sales](reply:Sales)`').includes('<button'), false, 'inline code does not execute actions');
assertIncludes(formatMarkdown('`**code**`'), '<code>**code**</code>', 'inline code stays literal');
