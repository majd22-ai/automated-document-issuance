(() => {
    const root = document.querySelector('[data-identity-phones]');
    if (!root) return;
    const codes = JSON.parse(root.dataset.phoneCodes);
    const country = root.querySelector('[data-residence-country]');
    const phones = Array.from(root.querySelectorAll('input[name="data[phone]"], input[name="data[alternate_phone]"], input[data-witness-autofill="phone"]'));
    const selectedCode = () => country ? country.selectedOptions[0]?.dataset.countryCode : root.dataset.phoneCountry;
    phones.forEach(input => {
        const hint = document.createElement('small');
        input.insertAdjacentElement('afterend', hint);
        input.type = 'tel';
        input.dir = 'ltr';
        input.inputMode = 'tel';
        let previousPrefix = '';
        const refresh = (changed = false) => {
            const code = selectedCode();
            const prefix = codes[code] ? `+${codes[code]}` : '';
            if (changed && previousPrefix && input.value.startsWith(previousPrefix)) {
                input.value = prefix + input.value.slice(previousPrefix.length);
            }
            previousPrefix = prefix;
            hint.textContent = prefix ? `رمز الاتصال الدولي: ${prefix}${code === 'YE' ? ' — 9 أرقام محلية كحد أقصى' : ''}` : '';
            hint.dir = 'auto';
            input.placeholder = prefix ? `${prefix}…` : '';
            // Include the prefix in submitted values; retain optional empty fields.
            const normalize = () => {
                let value = input.value.trim();
                if (!value || !prefix) return;
                if (value.startsWith(`00${codes[code]}`)) value = '+' + value.slice(2);
                else if (!value.startsWith('+')) value = prefix + value;
                input.value = value;
            };
            normalize();
            input.maxLength = code === 'YE' ? prefix.length + 9 : 16;
            input.setCustomValidity(code === 'YE' && input.value && (!input.value.startsWith(prefix) || input.value.slice(prefix.length).length > 9)
                ? 'أدخل رقمًا يمنيًا لا يتجاوز 9 أرقام بعد +967.' : '');
        };
        input.addEventListener('input', () => refresh());
        input.addEventListener('change', () => refresh());
        country?.addEventListener('change', () => refresh(true));
        refresh();
    });
})();
