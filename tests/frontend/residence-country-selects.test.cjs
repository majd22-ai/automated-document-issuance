const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
class Option {
    constructor(text, value) { this.text = text; this.value = value; this.dataset = {}; }
}
class Control {
    constructor(id, value = '') { this.id = id; this.value = value; this.options = []; this.dataset = {}; this.listeners = {}; this.disabled = false; }
    replaceChildren(...options) { this.options = options; this.value = ''; }
    add(option) { this.options.push(option); }
    addEventListener(event, listener) { this.listeners[event] = listener; }
    get selectedOptions() { return this.options.filter(row => row.value === this.value); }
}
const divisions = [
    {id: 'one', name: 'المحافظة الأولى', districts: [{name: 'مديرية أ'}, {name: 'مديرية ب'}]},
    {id: 'two', name: 'المحافظة الثانية', districts: [{name: 'مديرية ج'}]},
];
const settle = () => new Promise(resolve => setImmediate(resolve));
function run({code = '', governorate = '', district = '', fetcher} = {}) {
    const country = new Control('country', code);
    country.options = ['', 'YE', 'SA'].map(code => { const option = new Option(code, code); option.dataset.countryCode = code; return option; });
    const controls = {'country': country, 'status': {textContent: ''}};
    for (const kind of ['governorate', 'district']) {
        controls[kind] = new Control(kind);
        controls[kind].dataset.selected = kind === 'governorate' ? governorate : district;
        controls[kind + '-manual'] = new Control(kind + '-manual', kind === 'governorate' ? governorate : district);
    }
    const requests = [];
    const root = {
        dataset: {residenceUrl: '/locations/residence/ZZ'},
        querySelector(selector) {
            if (selector.startsWith('label')) return {setAttribute() {}};
            return controls[selector.replace('[data-residence-', '').replace(']', '')];
        },
    };
    vm.runInNewContext(fs.readFileSync('public/js/residence-country-selects.js', 'utf8'), {
        document: {querySelectorAll: () => [root]}, Option,
        fetch: url => { requests.push(url); return fetcher ? fetcher(url) : Promise.resolve({ok: true, json: async () => ({governorates: divisions})}); },
    });
    return {...controls, requests};
}
test('empty country leaves dependent lists disabled and sends no request', () => {
    const ui = run();
    assert.equal(ui.governorate.disabled, true);
    assert.equal(ui.district.disabled, true);
    assert.equal(ui.requests.length, 0);
});
test('country loads its governorates and governorate change clears the previous district', async () => {
    const ui = run({code: 'YE'});
    await settle();
    assert.deepEqual(ui.requests, ['/locations/residence/YE']);
    ui.governorate.value = 'المحافظة الأولى';
    ui.governorate.listeners.change();
    assert.equal(ui.district.options[1].value, 'مديرية أ');
    ui.district.value = 'مديرية ب';
    ui.governorate.value = 'المحافظة الثانية';
    ui.governorate.listeners.change();
    assert.equal(ui.district.value, '');
    assert.equal(ui.district.options[1].value, 'مديرية ج');
});
test('saved draft restores country governorate and district without clearing them', async () => {
    const ui = run({code: 'YE', governorate: 'المحافظة الأولى', district: 'مديرية ب'});
    await settle();
    assert.equal(ui.governorate.value, 'المحافظة الأولى');
    assert.equal(ui.district.value, 'مديرية ب');
    assert.equal(ui['governorate-manual'].disabled, true);
});
test('legacy governorate missing from directory retains the saved district for draft editing', async () => {
    const ui = run({code: 'SA', governorate: 'منطقة قديمة', district: 'مديرية قديمة'});
    await settle();
    assert.equal(ui.governorate.value, 'منطقة قديمة');
    assert.equal(ui['district-manual'].value, 'مديرية قديمة');
    assert.equal(ui['district-manual'].disabled, false);
});
test('late response from previous country cannot overwrite current country', async () => {
    const pending = {};
    const ui = run({code: 'YE', fetcher: url => new Promise(resolve => { pending[url] = resolve; })});
    ui.country.value = 'SA';
    ui.country.listeners.change();
    pending['/locations/residence/SA']({ok: true, json: async () => ({governorates: [{name: 'منطقة سعودية', districts: []}]})});
    await settle();
    pending['/locations/residence/YE']({ok: true, json: async () => ({governorates: divisions})});
    await settle();
    assert.equal(ui.governorate.options[1].value, 'منطقة سعودية');
    assert.equal(ui.governorate.options.some(row => row.value === 'المحافظة الأولى'), false);
});
test('unavailable administrative level allows explicit manual entry without inventing districts', async () => {
    const ui = run({code: 'SA', fetcher: async () => ({ok: true, json: async () => ({governorates: [{name: 'منطقة', districts: []}]})})});
    await settle();
    ui.governorate.value = 'منطقة';
    ui.governorate.listeners.change();
    assert.equal(ui.district.disabled, true);
    assert.equal(ui['district-manual'].disabled, false);
    assert.match(ui.status.textContent, /لا تتوفر/);
});
test('network failure preserves saved values for manual recovery', async () => {
    const ui = run({code: 'YE', governorate: 'محافظة محفوظة', district: 'مديرية محفوظة', fetcher: async () => { throw new Error('network'); }});
    await settle();
    assert.equal(ui['governorate-manual'].value, 'محافظة محفوظة');
    assert.equal(ui['district-manual'].value, 'مديرية محفوظة');
    assert.match(ui.status.textContent, /تعذر تحميل/);
    ui.country.value = '';
    ui.country.listeners.change();
    assert.equal(ui['governorate-manual'].disabled, true);
    assert.equal(ui['district-manual'].value, '');
});
