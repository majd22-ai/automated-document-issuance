const test = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');
const source = fs.readFileSync('public/js/identity-phones.js', 'utf8');
function setup(value = '') {
    const listeners = {};
    const country = {selectedOptions: [{dataset: {countryCode: 'YE'}}], addEventListener: (event, fn) => listeners.country = fn};
    const input = {value, insertAdjacentElement() {}, addEventListener: (event, fn) => listeners[event] = fn, setCustomValidity(message) {this.error = message;}};
    const root = {dataset: {phoneCodes: JSON.stringify({YE: '967', SA: '966'}), phoneCountry: 'YE'}, querySelector: () => country, querySelectorAll: () => [input]};
    vm.runInNewContext(source, {document: {querySelector: () => root, createElement: () => ({})}});
    return {input, listeners, country};
}
test('Yemen phone displays prefix, preserves nine digits and rejects pasted tenth digit', () => {
    const {input, listeners} = setup('777123456');
    assert.equal(input.value, '+967777123456');
    assert.equal(input.maxLength, 13);
    assert.equal(input.error, '');
    input.value = '009677771234567';
    listeners.input();
    assert.equal(input.value, '+9677771234567');
    assert.ok(input.error);
});
test('changing country updates prefix and clears Yemen limit; empty optional phones remain empty', () => {
    const {input, listeners, country} = setup('777123456');
    country.selectedOptions[0].dataset.countryCode = 'SA';
    listeners.country();
    assert.equal(input.value, '+966777123456');
    assert.equal(input.maxLength, 16);
    assert.equal(input.error, '');
    assert.equal(setup().input.value, '');
});
