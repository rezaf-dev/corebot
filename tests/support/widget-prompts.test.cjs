const fs = require('fs');
const path = require('path');
const vm = require('vm');

const widgetPath = path.resolve(__dirname, '../../public/widget.js');
const widgetSource = fs.readFileSync(widgetPath, 'utf8');
const match = widgetSource.match(/\/\* PROMPT_HELPERS_START \*\/([\s\S]*?)\/\* PROMPT_HELPERS_END \*/);

if (!match) {
    throw new Error('Prompt helpers block not found in public/widget.js');
}

const context = { console };
vm.createContext(context);
vm.runInContext(match[1], context);

const {
    parseSuggestedPrompts,
    normalizeSuggestedPrompts,
    resolvedSuggestedPrompts,
    titleInitial,
} = context;

function assertEqual(actual, expected, label) {
    const a = JSON.stringify(actual);
    const e = JSON.stringify(expected);
    if (a !== e) {
        throw new Error(`${label}: expected ${e}, got ${a}`);
    }
}

assertEqual(parseSuggestedPrompts('["Pricing?", "  ", "Support"]'), ['Pricing?', 'Support'], 'parses json dataset');
assertEqual(parseSuggestedPrompts(['One', 'Two', 'Three', 'Four', 'Five']), ['One', 'Two', 'Three', 'Four'], 'caps at four prompts');
assertEqual(resolvedSuggestedPrompts([]), [
    'How do I get started?',
    'What can you help with?',
    'Contact support',
], 'uses defaults when empty');
assertEqual(resolvedSuggestedPrompts(['Custom prompt']), ['Custom prompt'], 'uses custom prompts');
assertEqual(titleInitial('Support'), 'S', 'title initial');
assertEqual(titleInitial(''), 'S', 'empty title initial');
assertEqual(normalizeSuggestedPrompts(null), [], 'null prompts');

console.log('widget-prompts tests passed');
