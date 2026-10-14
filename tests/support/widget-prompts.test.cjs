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

// Exercise the actual button renderer and submit handler for every bundled language.
const renderStart = widgetSource.indexOf('    function renderSuggestedPrompts()');
const clickStart = widgetSource.indexOf('    async function handlePromptClick(', renderStart);
const clickEnd = widgetSource.indexOf('    function handleReplyActionClick(', clickStart);
const promptCode = widgetSource.slice(renderStart, clickEnd);
const locales = ['en', ...fs.readdirSync(path.resolve(__dirname, '../../lang')).filter((name) => name.endsWith('.json')).map((name) => name.replace('.json', ''))];
for (const locale of locales) {
    const translationPath = path.resolve(__dirname, `../../lang/${locale}.json`);
    const translations = fs.existsSync(translationPath) ? JSON.parse(fs.readFileSync(translationPath, 'utf8')) : {};
    const buttons = [];
    const sent = [];
    const input = { value: '' };
    const promptContext = vm.createContext({
        config: { suggested_prompts: [] },
        resolvedSuggestedPrompts,
        DEFAULT_SUGGESTED_PROMPTS: resolvedSuggestedPrompts([]),
        t: (text) => translations[text] || text,
        hideSuggestedPrompts() {}, hasUserMessages: () => false, scrollToBottom() {},
        document: {
            createElement: () => ({
                appendChild(button) { buttons.push(button); },
                addEventListener(event, callback) { this.click = callback; },
            }),
        },
        messages: { appendChild() {} },
        input, form: { requestSubmit() { sent.push(input.value); } },
        isLoading: false, isStarting: false, isChangingLanguage: false, chatBlockedByContact: () => false,
    });
    vm.runInContext(promptCode, promptContext);
    promptContext.renderSuggestedPrompts();
    for (const button of buttons) {
        button.click();
        assertEqual(sent.at(-1), button.textContent, `${locale}: submitted question matches the displayed translation`);
    }
    // Custom localized questions must also be submitted without translation or substitution.
    buttons.length = 0;
    promptContext.config.suggested_prompts = ['سوال دلخواه؟', 'Özel soru?', '¿Pregunta personalizada?'];
    promptContext.renderSuggestedPrompts();
    for (const button of buttons) {
        button.click();
        assertEqual(sent.at(-1), button.textContent, `${locale}: custom question preserves entered text`);
    }
}
console.log('Translated starter click checks passed for all languages.');
