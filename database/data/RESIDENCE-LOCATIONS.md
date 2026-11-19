# Residence address directory

The step 3 identity wizard uses country → ADM1 (governorate/region/state) → ADM2 (district or corresponding administrative division). Cities are not substituted for districts.

Foreign country data: [GeoNames](https://download.geonames.org/export/dump/), retrieved 2026-11-14, licensed [CC BY 4.0](https://creativecommons.org/licenses/by/4.0/). GeoNames [format and licensing](https://download.geonames.org/export/dump/readme.txt). Country names are displayed in Arabic using Node/ICU `Intl.DisplayNames`; subdivision names preserve the source spelling and are often English/local names. Yemen uses the existing Arabic `residence_governorates` / `residence_districts` tables, originally populated from `yemen-divisions.json`, preserving neighborhood-official directory identifiers.

`residence-countries.json` contains 250 country/territory options. Withdrawn AN/CS codes are excluded. `residence-divisions/XX.json` contains only the ADM1 and ADM2 records actually supplied by GeoNames for that country. Coverage varies: some countries have no ADM1 or ADM2, and not every administrative system uses those levels. Missing levels are explicitly indicated in the UI and allow manual entry. No universal completeness claim is made. Source hashes and input row counts are recorded in `residence-locations-source.json`.

To rebuild, download `countryInfo.txt`, `admin1CodesASCII.txt` and `admin2Codes.txt` from the GeoNames directory into a temporary directory, then run:

```powershell
node scripts/build-residence-divisions.cjs <download-directory>
```

The builder rejects malformed/truncated rows and unexpectedly short files. Review changes before replacing the snapshot. These files do not require a database migration, seeder or API credentials. Runtime requests are to the application's own authenticated endpoint; no citizen address is sent to GeoNames.
