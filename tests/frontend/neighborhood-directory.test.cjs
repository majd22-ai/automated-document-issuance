const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

test('selecting a neighborhood fills the directory name and phone and clears them on district change', async () => {
    class Option {
        constructor(text, value) { this.text = text; this.value = String(value); this.dataset = {}; }
    }
    const select = (options = [], value = '') => ({
        options, value, dataset: {}, disabled: false, listeners: {},
        get selectedOptions() { return this.options.filter(o => o.value === String(this.value)); },
        replaceChildren(...items) { this.options = items; this.value = items[0]?.value || ''; },
        add(item) { this.options.push(item); },
        addEventListener(name, listener) { this.listeners[name] = listener; },
    });
    const govOption = new Option('Governorate', 1); govOption.dataset.locationId = '1';
    const governorate = select([govOption], '1');
    const district = select(); district.dataset.selected = '10';
    const official = select(); official.dataset.selected = '77';
    const name = {value: ''}, phone = {value: ''}, neighborhood = {value: ''};
    const submit = {hidden: true};
    const fields = {
        '[data-location-data]': {textContent: JSON.stringify([{id: 1, districts: [{id: 10, name: 'District'}]}])},
        '[data-location-governorate]': governorate, '[data-location-district]': district,
        '[data-location-official]': official, '[data-location-neighborhood]': neighborhood,
        '[data-attestation-request]': submit, '[data-location-status]': {},
        '[data-official-name]': name, '[data-official-phone]': phone,
    };
    const root = {dataset: {officialsUrl: '/residence/districts/__DISTRICT__/officials'}, querySelector: key => fields[key]};
    let calls = 0;
    vm.runInNewContext(fs.readFileSync('public/js/residence-selects.js', 'utf8'), {
        document: {querySelectorAll: () => [root]}, Option,
        fetch: async () => { calls++; return {ok: true, json: async () => [{id: 77, neighborhood: 'Neighborhood', full_name: 'Official', phone: '+967777123456'}]}; },
    });
    await new Promise(setImmediate);
    assert.equal(calls, 1);
    assert.equal(name.value, 'Official'); assert.equal(phone.value, '+967777123456');
    assert.equal(neighborhood.value, 'Neighborhood'); assert.equal(submit.hidden, false);
    assert.equal(official.options[1].text, 'Neighborhood');
    district.value = ''; district.listeners.change();
    assert.equal(name.value, ''); assert.equal(phone.value, ''); assert.equal(submit.hidden, true);
});
