(() => {
    const root = document.querySelector('[data-birth-details]');
    if (!root) return;
    const locations = JSON.parse(root.querySelector('[data-birth-locations]').textContent);
    const country = root.querySelector('#field-birth_country');
    const governorate = root.querySelector('#field-birth_governorate');
    const districtOptions = root.querySelector('#options-birth_district');
    const governorateOptions = root.querySelector('#options-birth_governorate');
    const fill = (list, values) => list.replaceChildren(...values.map(value => new Option(value, value)));
    const update = () => {
        const isYemen = !country.value || ['اليمن', 'الجمهورية اليمنية'].includes(country.value.trim());
        fill(governorateOptions, isYemen ? locations.map(row => row.name) : []);
        fill(districtOptions, isYemen ? (locations.find(row => row.name === governorate.value)?.districts || []) : []);
    };
    // Use the browser's Arabic territory names; stored values remain the displayed names.
    if (typeof Intl.DisplayNames === 'function') {
        const codes = 'AD AE AF AG AI AL AM AO AQ AR AS AT AU AW AX AZ BA BB BD BE BF BG BH BI BJ BL BM BN BO BQ BR BS BT BV BW BY BZ CA CC CD CF CG CH CI CK CL CM CN CO CR CU CV CW CX CY CZ DE DJ DK DM DO DZ EC EE EG EH ER ES ET FI FJ FK FM FO FR GA GB GD GE GF GG GH GI GL GM GN GP GQ GR GS GT GU GW GY HK HM HN HR HT HU ID IE IL IM IN IO IQ IR IS IT JE JM JO JP KE KG KH KI KM KN KP KR KW KY KZ LA LB LC LI LK LR LS LT LU LV LY MA MC MD ME MF MG MH MK ML MM MN MO MP MQ MR MS MT MU MV MW MX MY MZ NA NC NE NF NG NI NL NO NP NR NU NZ OM PA PE PF PG PH PK PL PM PN PR PS PT PW PY QA RE RO RS RU RW SA SB SC SD SE SG SH SI SJ SK SL SM SN SO SR SS ST SV SX SY SZ TC TD TF TG TH TJ TK TL TM TN TO TR TT TV TW TZ UA UG UM US UY UZ VA VC VE VG VI VN VU WF WS YE YT ZA ZM ZW'.split(' ');
        const names = new Intl.DisplayNames(['ar'], {type: 'region'});
        fill(root.querySelector('#options-birth_country'), [...new Set(codes.map(code => code === 'YE' ? 'اليمن' : names.of(code)))].sort((a,b) => a.localeCompare(b, 'ar')));
    }
    update();
    const controls = new Map();
    const enhance = input => {
        const list = root.querySelector('#' + input.getAttribute('list'));
        const allowsCustom = input.id !== 'field-birth_place';
        const required = input.required;
        const select = document.createElement('select');
        select.id = input.id + '-choices';
        select.setAttribute('aria-label', input.labels[0]?.textContent || 'اختر');
        const refresh = () => {
            const current = input.value;
            const isListed = current && Array.from(list.options).some(option => option.value === current);
            select.replaceChildren(
                new Option('اختر من القائمة', ''),
                ...Array.from(list.options, option => new Option(option.value, option.value)),
                ...(allowsCustom ? [new Option('غير موجود في القائمة — أدخل الاسم', '__custom')] : []),
            );
            select.value = isListed ? current : current && allowsCustom ? '__custom' : '';
            input.hidden = ! (select.value === '__custom' || (current && ! allowsCustom && ! isListed));
            input.required = required && ! input.hidden;
            select.required = required && input.hidden;
        };
        input.insertAdjacentElement('beforebegin', select);
        // Keep the named input as the canonical value, including user-entered locations.
        select.addEventListener('change', () => {
            const isCustom = allowsCustom && select.value === '__custom';
            input.hidden = ! isCustom;
            input.value = isCustom ? '' : select.value;
            input.required = required && isCustom;
            select.required = required && ! isCustom;
            if (!input.hidden) input.focus();
            input.dispatchEvent(new Event('input', {bubbles: true}));
        });
        if (input.getAttribute('aria-invalid')) select.setAttribute('aria-invalid', 'true');
        if (input.getAttribute('aria-describedby')) select.setAttribute('aria-describedby', input.getAttribute('aria-describedby'));
        // Clicking the field label focuses the visible select.
        root.querySelector('label[for="' + input.id + '"]')?.setAttribute('for', select.id);
        controls.set(input, refresh);
        refresh();
    };
    root.querySelectorAll('input[list]').forEach(enhance);
    governorate.addEventListener('input', () => {
        root.querySelector('#field-birth_district').value = '';
        root.querySelector('#field-birth_village').value = '';
        update();
        controls.get(root.querySelector('#field-birth_district'))();
    });
    country.addEventListener('input', () => {
        governorate.value = '';
        root.querySelector('#field-birth_district').value = '';
        root.querySelector('#field-birth_village').value = '';
        update();
        controls.get(governorate)();
        controls.get(root.querySelector('#field-birth_district'))();
    });
})();
