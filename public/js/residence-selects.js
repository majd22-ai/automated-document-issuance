(() => {
    document.querySelectorAll('[data-residence-selects]').forEach((root) => {
        const locations = JSON.parse(root.querySelector('[data-location-data]').textContent);
        const governorate = root.querySelector('[data-location-governorate]');
        const district = root.querySelector('[data-location-district]');
        const official = root.querySelector('[data-location-official]');
        const neighborhood = root.querySelector('[data-location-neighborhood]');
        const submit = root.querySelector('[data-attestation-request]');
        const status = root.querySelector('[data-location-status]');
        const names = root.dataset.locationValues === 'names';
        let generation = 0;
        const reset = (select, text) => { select.replaceChildren(new Option(text, '')); select.disabled = true; };
        const sync = () => {
            if (submit) submit.hidden = !official?.value;
            if (official && neighborhood) neighborhood.value = official.selectedOptions[0]?.dataset.neighborhood || '';
        };
        const loadOfficials = async (initial = false) => {
            if (!official) return;
            const current = ++generation;
            reset(official, district.value ? 'جارٍ تحميل العُقّال…' : 'اختر المديرية أولًا');
            sync();
            if (!district.value) return;
            if (status) status.textContent = '';
            try {
                const response = await fetch(root.dataset.officialsUrl.replace('__DISTRICT__', district.value), {headers: {'Accept': 'application/json'}, cache: 'no-store'});
                if (!response.ok) throw new Error();
                const rows = await response.json();
                if (current !== generation) return;
                reset(official, rows.length ? 'اختر عاقل الحارة / مسؤول الحي' : 'لا يوجد عاقل مسجل في هذه المديرية');
                rows.forEach(row => {
                    const option = new Option(`${row.full_name} — ${row.neighborhood}`, row.id);
                    option.dataset.neighborhood = row.neighborhood;
                    official.add(option);
                });
                official.disabled = !rows.length;
                if (initial) official.value = official.dataset.selected || '';
                sync();
            } catch {
                if (current !== generation) return;
                reset(official, 'تعذر تحميل العُقّال');
                if (status) status.textContent = 'تعذر تحميل القائمة. أعد اختيار المديرية للمحاولة مجددًا.';
            }
        };
        const loadDistricts = (initial = false) => {
            const selected = governorate.selectedOptions[0]?.dataset.locationId;
            const location = locations.find(row => String(row.id) === selected);
            reset(district, location ? 'اختر المديرية' : 'اختر المحافظة أولًا');
            if (location) {
                location.districts.forEach(row => district.add(new Option(row.name, names ? row.name : row.id)));
                district.disabled = false;
            }
            if (initial) district.value = district.dataset.selected || '';
            loadOfficials(initial);
        };
        governorate.addEventListener('change', () => loadDistricts());
        district.addEventListener('change', () => loadOfficials());
        official?.addEventListener('change', sync);
        loadDistricts(true);
    });
})();
