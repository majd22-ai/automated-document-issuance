(() => {
    document.querySelectorAll('[data-residence-cascade]').forEach(root => {
        const country = root.querySelector('[data-residence-country]');
        const status = root.querySelector('[data-residence-status]');
        const field = kind => {
            const select = root.querySelector(`[data-residence-${kind}]`);
            const manual = root.querySelector(`[data-residence-${kind}-manual]`);
            return {select, manual, name: `data[residence_${kind}]`, label: root.querySelector(`label[for="${manual.id}"]`)};
        };
        const governorate = field('governorate');
        const district = field('district');
        const saved = {governorate: governorate.select.dataset.selected || '', district: district.select.dataset.selected || ''};
        const cache = new Map();
        let generation = 0;
        let locations = [];
        const label = (item, id) => item.label?.setAttribute('for', id);
        const list = (item, rows, placeholder, selected = '') => {
            item.manual.hidden = true;
            item.manual.disabled = true;
            item.manual.value = '';
            item.select.hidden = false;
            item.select.disabled = !rows.length;
            item.select.name = item.name;
            item.select.replaceChildren(new Option(placeholder, ''), ...rows.map(row => new Option(row.name, row.name)));
            if (selected && !rows.some(row => row.name === selected)) {
                item.select.add(new Option(selected + ' — قيمة محفوظة؛ راجع الاختيار', selected));
            }
            item.select.value = selected;
            label(item, item.select.id);
        };
        const manual = (item, value = '') => {
            item.select.hidden = true;
            item.select.disabled = true;
            item.manual.hidden = false;
            item.manual.disabled = false;
            item.manual.value = value;
            label(item, item.manual.id);
        };
        const loadDistricts = (selected = '') => {
            const location = locations.find(row => row.name === governorate.select.value);
            if (!location) {
                if (selected && governorate.select.value) {
                    manual(district, selected);
                    status.textContent = 'العنوان المحفوظ غير موجود في الدليل الحالي؛ راجع اختيار المحافظة مع الاحتفاظ باسم المديرية المحفوظ.';
                } else list(district, [], 'اختر المحافظة أولًا');
            } else if (location.districts.length) {
                list(district, location.districts, 'اختر المديرية / التقسيم الإداري', selected);
                status.textContent = '';
            } else {
                manual(district, selected);
                status.textContent = 'لا تتوفر قائمة التقسيمات التابعة لهذه المنطقة؛ أدخل الاسم كما في وثيقة الإقامة.';
            }
        };
        const loadCountry = async (initial = false) => {
            const current = ++generation;
            const code = country.selectedOptions[0]?.dataset.countryCode;
            locations = [];
            list(governorate, [], country.value ? 'جارٍ تحميل المحافظات…' : 'اختر الدولة أولًا');
            list(district, [], 'اختر المحافظة أولًا');
            if (!code) {
                if (country.value) {
                    manual(governorate, initial ? saved.governorate : '');
                    manual(district, initial ? saved.district : '');
                    status.textContent = 'دولة الإقامة محفوظة بصيغة قديمة؛ يمكنك اختيارها من قائمة الدول أو مراجعة العنوان المحفوظ.';
                } else status.textContent = 'اختر دولة الإقامة أولًا.';
                return;
            }
            status.textContent = 'جارٍ تحميل تقسيمات دولة الإقامة…';
            try {
                if (!cache.has(code)) {
                    cache.set(code, fetch(root.dataset.residenceUrl.replace(/ZZ$/, code), {headers: {'Accept': 'application/json'}})
                        .then(response => { if (!response.ok) throw new Error('locations'); return response.json(); })
                        .catch(error => { cache.delete(code); throw error; }));
                }
                const data = await cache.get(code);
                if (current !== generation) return;
                locations = data.governorates;
                if (!Array.isArray(locations)) throw new Error('locations');
                if (locations.length) {
                    list(governorate, locations, 'اختر المحافظة / المنطقة', initial ? saved.governorate : '');
                    status.textContent = 'اختر المحافظة ثم المديرية التابعة لها.';
                    loadDistricts(initial ? saved.district : '');
                } else {
                    manual(governorate, initial ? saved.governorate : '');
                    manual(district, initial ? saved.district : '');
                    status.textContent = 'لا تتوفر تقسيمات إدارية لهذه الدولة في الدليل؛ أدخل العنوان كما في وثيقة الإقامة.';
                }
            } catch {
                if (current !== generation) return;
                manual(governorate, initial ? saved.governorate : '');
                manual(district, initial ? saved.district : '');
                status.textContent = 'تعذر تحميل القوائم. أعد اختيار الدولة للمحاولة مجددًا، أو أدخل عنوان الإقامة يدويًا.';
            }
        };
        country.addEventListener('change', () => loadCountry());
        governorate.select.addEventListener('change', () => loadDistricts());
        governorate.manual.addEventListener('input', () => { district.manual.value = ''; });
        loadCountry(true);
    });
})();
