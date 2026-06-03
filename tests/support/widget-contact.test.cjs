const fs = require('fs');
const path = require('path');
const vm = require('vm');

const widgetPath = path.resolve(__dirname, '../../public/widget.js');
const widgetSource = fs.readFileSync(widgetPath, 'utf8');
const match = widgetSource.match(/\/\* CONTACT_HELPERS_START \*\/([\s\S]*?)\/\* CONTACT_HELPERS_END \*/);

if (!match) {
    throw new Error('Contact helpers block not found in public/widget.js');
}

const context = { console };
vm.createContext(context);
vm.runInContext(match[1], context);

const { contactCopy, validateContactPayload, mapContactFieldErrors } = context;

function assertEqual(actual, expected, label) {
    const a = JSON.stringify(actual);
    const e = JSON.stringify(expected);
    if (a !== e) {
        throw new Error(`${label}: expected ${e}, got ${a}`);
    }
}

assertEqual(contactCopy('start').submit, 'Continue', 'start submit label');
assertEqual(contactCopy('fallback').title, 'Stay in touch', 'fallback title');

assertEqual(
    validateContactPayload(['email'], ['email'], { visitor_email: '' }),
    { visitor_email: 'Email is required.' },
    'required email'
);

assertEqual(
    validateContactPayload(['email'], ['email'], { visitor_email: 'not-an-email' }),
    { visitor_email: 'Enter a valid email address.' },
    'invalid email'
);

assertEqual(
    validateContactPayload(['name', 'email'], [], { visitor_name: '', visitor_email: '' }),
    { _form: 'Please fill in at least one field.' },
    'requires at least one value'
);

assertEqual(
    mapContactFieldErrors({ visitor_email: ['The visitor email field is required.'] }),
    { visitor_email: 'The visitor email field is required.' },
    'maps api errors'
);

console.log('widget-contact tests passed');
