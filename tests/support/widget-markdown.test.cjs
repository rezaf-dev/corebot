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
