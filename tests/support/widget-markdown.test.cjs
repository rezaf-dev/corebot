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

const { formatMarkdown, escapeHtml } = context;

function assertIncludes(actual, expected, label) {
    if (!actual.includes(expected)) {
        throw new Error(`${label}: expected ${JSON.stringify(actual)} to include ${JSON.stringify(expected)}`);
    }
}

assertIncludes(formatMarkdown('**Hello** world'), '<strong>Hello</strong>', 'bold');
assertIncludes(formatMarkdown('Visit [Docs](https://example.com/docs)'), 'href="https://example.com/docs"', 'safe link');
assertIncludes(formatMarkdown('Visit [X](javascript:alert(1))'), 'Visit X', 'blocked javascript link');
assertIncludes(formatMarkdown('`code`'), '<code>code</code>', 'inline code');
assertIncludes(formatMarkdown('- one\n- two'), '<ul><li>one</li><li>two</li></ul>', 'unordered list');
assertIncludes(formatMarkdown('```\nline\n```'), '<pre><code>line</code></pre>', 'code block');
assertIncludes(escapeHtml('<script>'), '&lt;script&gt;', 'escape html');

console.log('widget-markdown tests passed');
