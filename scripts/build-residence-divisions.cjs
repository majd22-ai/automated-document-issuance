// Usage: node scripts/build-residence-divisions.cjs <GeoNames download directory>
const fs = require('node:fs');
const path = require('node:path');
const crypto = require('node:crypto');
const input = process.argv[2];
if (!input) throw new Error('GeoNames source directory required');
const read = name => fs.readFileSync(path.join(input, name), 'utf8').split(/\r?\n/).filter(line => line && !line.startsWith('#')).map(line => line.split('\t'));
const countryRows = read('countryInfo.txt');
const admin1Rows = read('admin1CodesASCII.txt');
const admin2Rows = read('admin2Codes.txt');
if (countryRows.length < 200 || admin1Rows.length < 3000 || admin2Rows.length < 10000) throw new Error('Incomplete source files');
for (const row of [...admin1Rows, ...admin2Rows]) {
    if (row.length !== 4 || !row[0] || !row[1] || !/^\d+$/.test(row[3])) throw new Error('Malformed or truncated administrative source');
}
const names = new Intl.DisplayNames(['ar'], {type: 'region'});
const countries = countryRows.map(row => ({code: row[0], name: row[0] === 'YE' ? 'اليمن' : names.of(row[0]), english_name: row[4]}))
    .filter(row => /^[A-Z]{2}$/.test(row.code) && !['AN', 'CS'].includes(row.code)).sort((a, b) => a.name.localeCompare(b.name, 'ar'));
const subdivisions = new Map();
for (const [code, name] of admin1Rows) {
    if (!subdivisions.has(code)) subdivisions.set(code, {id: code, name, districts: []});
}
for (const [code, name] of admin2Rows) {
    const parent = code.split('.').slice(0, 2).join('.');
    const governorate = subdivisions.get(parent);
    if (governorate && !governorate.districts.some(row => row.id === code)) governorate.districts.push({id: code, name});
}
const output = path.resolve(__dirname, '../database/data/residence-divisions');
fs.mkdirSync(output, {recursive: true});
for (const country of countries) {
    if (country.code === 'YE') continue; // Yemen uses the local Arabic official-directory tables.
    const governorates = [...subdivisions.values()].filter(row => row.id.startsWith(country.code + '.'))
        .sort((a, b) => a.name.localeCompare(b.name, 'ar'));
    for (const governorate of governorates) governorate.districts.sort((a, b) => a.name.localeCompare(b.name, 'ar'));
    fs.writeFileSync(path.join(output, country.code + '.json'), JSON.stringify(governorates) + '\n');
}
fs.writeFileSync(path.resolve(output, '../residence-countries.json'), JSON.stringify(countries) + '\n');
const metadata = {
    source: 'GeoNames', license: 'CC BY 4.0', license_url: 'https://creativecommons.org/licenses/by/4.0/',
    source_url: 'https://download.geonames.org/export/dump/', retrieved_on: '2026-11-14',
    countries: countries.length, source_admin1_rows: admin1Rows.length, source_admin2_rows: admin2Rows.length,
    files: Object.fromEntries(['countryInfo.txt', 'admin1CodesASCII.txt', 'admin2Codes.txt'].map(name => [name, {
        sha256: crypto.createHash('sha256').update(fs.readFileSync(path.join(input, name))).digest('hex'),
    }])),
    notes: 'ADM1 and ADM2 only; countries without a supplied administrative level use explicit manual entry. Yemen uses the existing Arabic directory. Withdrawn AN/CS country codes excluded. Names retain the source spelling.',
};
fs.writeFileSync(path.resolve(output, '../residence-locations-source.json'), JSON.stringify(metadata, null, 2) + '\n');
console.log(JSON.stringify({countries: countries.length, governorates: admin1Rows.length, districts: admin2Rows.length}));
