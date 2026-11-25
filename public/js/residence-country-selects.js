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
        let neighborhoodGeneration = 0;
        const neighborhoodInput = root.querySelector('[data-neighborhood-manual]');
        const neighborhoodList = root.querySelector('[data-neighborhood-list]');
        const neighborhoodLabel = root.querySelector('[data-neighborhood-label]');
        const neighborhoodStatus = root.querySelector('[data-neighborhood-status]');
        const officialId = root.querySelector('[data-neighborhood-official-id]');
        const officialField = root.querySelector('[data-neighborhood-official]');
        const savedNeighborhood = neighborhoodInput?.value || '';
        const savedOfficial = officialId?.value || '';
        const syncOfficial = () => {
            const selected = neighborhoodList.selectedOptions[0];
            officialId.value = selected?.dataset.id || '';
            officialField.querySelector('input').value = selected?.dataset.name || '';
            officialField.hidden = !officialId.value;
        };
        const loadNeighborhoods = async (initial = false) => {
            if (!neighborhoodList) return;
            const current = ++neighborhoodGeneration;
            const governor = locations.find(row => row.name === governorate.select.value);
            const area = governor?.districts.find(row => row.name === district.select.value);
            const useDirectory = Boolean(area?.has_neighborhood_directory);
            neighborhoodInput.hidden = neighborhoodInput.disabled = useDirectory;
            neighborhoodList.hidden = !useDirectory;
            neighborhoodList.disabled = !useDirectory;
            neighborhoodLabel.htmlFor = useDirectory ? neighborhoodList.id : neighborhoodInput.id;
            neighborhoodInput.value = initial ? savedNeighborhood : '';
            neighborhoodStatus.textContent = '';
            neighborhoodList.replaceChildren(new Option('جارٍ تحميل الأحياء…', ''));
            syncOfficial();
            if (!useDirectory) return;
            try {
                const response = await fetch(root.dataset.officialsUrl.replace('__DISTRICT__', area.id), {
                    headers: {Accept: 'application/json'}, cache: 'no-store'
                });
                if (!response.ok) throw new Error('neighborhoods');
                const rows = await response.json();
                if (current !== neighborhoodGeneration) return;
                neighborhoodList.replaceChildren(new Option(rows.length ? 'اختر الحي / الحارة' : 'لا توجد أحياء متاحة', ''));
                rows.forEach(row => {
                    const option = new Option(row.neighborhood, row.neighborhood);
                    option.dataset.id = row.id;
                    option.dataset.name = row.full_name;
                    neighborhoodList.add(option);
                });
                if (initial) {
                    const options = Array.from(neighborhoodList.options);
                    const match = options.findIndex(option => option.value && (savedOfficial
                        ? option.dataset.id === savedOfficial && option.value === savedNeighborhood
                        : option.value === savedNeighborhood));
                    if (match > 0) neighborhoodList.selectedIndex = match;
                    else if (savedNeighborhood) neighborhoodStatus.textContent = 'الحي المحفوظ: ' + savedNeighborhood + '. اختر حيًا متاحًا للمتابعة.';
                }
                syncOfficial();
            } catch {
                if (current !== neighborhoodGeneration) return;
                neighborhoodList.replaceChildren(new Option('تعذّر تحميل الأحياء', ''));
                neighborhoodStatus.textContent = 'أعد اختيار المديرية للمحاولة مجددًا.';
            }
        };
        const label = (item, id) => item.label?.setAttribute('for', id);
        const list = (item, rows, placeholder, selected = '') => {
            item.manual.hidden = true;
            item.manual.disabled = true;
            item.manual.value = '';
            item.select.hidden = false;
            item.select.disabled = !rows.length;
            item.select.name = item.name;
            item.select.replaceChildren(new Option(placeholder, ''), ...rows.map(row => new Option(row.label ?? row.name, row.name)));
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
        const loadDistricts = (selected = '', initial = false) => {
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
            loadNeighborhoods(initial);
        };
        const loadCountry = async (initial = false) => {
            const current = ++generation;
            const code = country.selectedOptions[0]?.dataset.countryCode;
            locations = [];
            list(governorate, [], country.value ? 'جارٍ تحميل المحافظات…' : 'اختر الدولة أولًا');
            list(district, [], 'اختر المحافظة أولًا');
            loadNeighborhoods(initial);
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
                    cache.set(code, fetch(root.dataset.residenceUrl.replace(/ZZ$/, code), {cache: 'no-cache', headers: {'Accept': 'application/json'}})
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
                    loadDistricts(initial ? saved.district : '', initial);
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
        district.select.addEventListener('change', () => loadNeighborhoods());
        neighborhoodList?.addEventListener('change', syncOfficial);
        loadCountry(true);
    });
})();
