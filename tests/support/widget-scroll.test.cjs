const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const source = fs.readFileSync(require('node:path').resolve(__dirname, '../../public/widget.js'), 'utf8');
const functions = source.slice(source.indexOf('    function isNearBottom()'), source.indexOf('    function add('));
const classes = new Set();
let height = 1000;
let top = 700;
const messages = {
    get scrollHeight() { return height; },
    clientHeight: 300,
    get scrollTop() { return top; },
    set scrollTop(value) { top = Math.max(0, Math.min(value, height - this.clientHeight)); },
};
const context = vm.createContext({ messages, newMessagesButton: { classList: {
    toggle: (name, enabled) => enabled ? classes.add(name) : classes.delete(name),
    remove: (name) => classes.delete(name),
} }, followingLatestMessages: true, hasUnreadMessages: false });
vm.runInContext(functions, context);
height += 600;
context.scrollToBottom();
assert.equal(top, 1300, 'large incoming messages follow the bottom');
assert.equal(classes.has('is-visible'), false);
// A manual scroll pauses following, but does not itself mean a message is unread.
top = 100;
context.updateNewMessagesButton();
assert.equal(classes.has('is-visible'), false);
height += 600;
context.scrollToBottom();
assert.equal(top, 100, 'incoming message preserves the reading position');
assert.equal(classes.has('is-visible'), true);
for (let i = 0; i < 20; i++) {
    height += 100;
    context.scrollToBottom();
}
assert.equal(top, 100, 'streamed deltas never force a return');
context.scrollToBottom(true);
assert.equal(top, height - 300, 'jump to latest resumes following');
assert.equal(classes.has('is-visible'), false);
height += 200;
context.scrollToBottom();
assert.equal(top, height - 300);
top = 200;
context.updateNewMessagesButton();
context.scrollToBottom(false, false);
assert.equal(top, 200, 'resize preserves manual scroll');
assert.equal(classes.has('is-visible'), false, 'resize alone does not mark messages unread');
height += 200;
context.scrollToBottom();
top = height - 300;
context.updateNewMessagesButton();
assert.equal(classes.has('is-visible'), false, 'manually reaching bottom clears unread');
height += 200;
context.scrollToBottom();
assert.equal(top, height - 300, 'manually reaching bottom resumes following');

const pageFunction = source.slice(source.indexOf('    function pageContext()'), source.indexOf('    function parseUtmParams()'));
const page = vm.createContext({ location: { href: 'https://example.com/' }, document: { title: 'Home' } });
vm.runInContext(pageFunction, page);
assert.equal(page.pageContext().url, 'https://example.com/');
page.location.href = 'https://example.com/pricing';
page.document.title = 'Pricing';
assert.equal(page.pageContext().url, 'https://example.com/pricing', 'SPA navigation reads the live URL');
assert.equal(page.pageContext().title, 'Pricing');
page.location.href = 'https://example.com/products#details';
page.document.title = 'Products';
assert.equal(page.pageContext().url, 'https://example.com/products#details');
assert.equal(page.pageContext().title, 'Products');
console.log('Widget scrolling and live page context passed');
