const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

function phone() {
    class Element {
        constructor() { this.listeners = {}; this.hidden = false; this.disabled = false; this.value = 'fingerprint'; }
        addEventListener(name, fn) { this.listeners[name] = fn; }
        showModal() { this.open = true; }
        close() { this.open = false; }
        focus() { this.focused = true; }
        hasAttribute() { return false; }
    }
    const keys = ['dialog','open','close','request','verification','rejection','verification-title','approve','reject','method','code','confirm'];
    const elements = Object.fromEntries(keys.map(key => [`[data-phone-${key}]`, new Element()]));
    const input = new Element();
    const textarea = new Element();
    elements['[data-phone-code]'].querySelector = () => input;
    elements['[data-phone-rejection]'].querySelector = () => textarea;
    const root = { querySelector: key => elements[key], querySelectorAll: () => [] };
    vm.runInNewContext(fs.readFileSync('public/js/neighborhood-phone.js','utf8'), { document: { querySelectorAll: () => [root] } });
    return { elements, input, textarea };
}

test('closing and reopening phone sends no decision; approval opens a separate verification screen', () => {
    const { elements: e } = phone();
    e['[data-phone-open]'].listeners.click();
    assert.equal(e['[data-phone-dialog]'].open, true);
    e['[data-phone-close]'].listeners.click();
    assert.equal(e['[data-phone-dialog]'].open, false);
    e['[data-phone-open]'].listeners.click();
    e['[data-phone-approve]'].listeners.click();
    assert.equal(e['[data-phone-request]'].hidden, true);
    assert.equal(e['[data-phone-verification]'].hidden, false);
    assert.equal(e['[data-phone-verification-title]'].focused, true);
});

test('verification code is required only for code mode; rejection asks for a reason', () => {
    const { elements: e, input, textarea } = phone();
    assert.equal(input.disabled, true);
    e['[data-phone-method]'].value = 'code';
    e['[data-phone-method]'].listeners.change();
    assert.equal(input.disabled, false);
    assert.equal(input.required, true);
    e['[data-phone-reject]'].listeners.click();
    assert.equal(e['[data-phone-rejection]'].hidden, false);
    assert.equal(textarea.focused, true);
});
