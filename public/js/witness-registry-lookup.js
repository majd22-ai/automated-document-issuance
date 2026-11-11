(() => {
    document.querySelectorAll('[data-witness-registry-lookup]').forEach(root => {
        const type = root.querySelector('select[name$="[document_type]"]');
        const number = root.querySelector('[data-witness-document-number]');
        const button = root.querySelector('[data-witness-lookup-button]');
        const status = root.querySelector('[data-witness-lookup-status]');
        const autoFields = Object.fromEntries(
            Array.from(root.querySelectorAll('[data-witness-autofill]')).map(input => [input.dataset.witnessAutofill, input]),
        );
        const dateFields = root.querySelector('[data-witness-issued-date]');
        let lastLookup = null;
        let activeRequest = null;

        if (!type || !number || !button || !status || !dateFields) return;

        const clearAutofill = () => {
            ['name', 'phone', 'issued_place'].forEach(field => {
                if (autoFields[field]) autoFields[field].value = '';
            });
            dateFields.querySelectorAll('select').forEach(select => {
                select.value = '';
            });
        };
        const updateLookup = () => {
            const currentKey = `${type.value}\u0000${number.value.trim()}`;
            if (lastLookup && currentKey !== lastLookup) {
                clearAutofill();
                lastLookup = null;
                status.textContent = 'تغير نوع البطاقة أو رقمها؛ أعد البحث لتعبئة البيانات.';
            }
        };

        type.addEventListener('change', updateLookup);
        number.addEventListener('input', updateLookup);
        number.addEventListener('change', updateLookup);
        button.addEventListener('click', async () => {
            const documentNumber = number.value.trim();
            if (!type.value || !documentNumber) {
                status.textContent = 'اختر نوع البطاقة وأدخل رقمها أولًا.';
                return;
            }

            activeRequest?.abort();
            const request = new AbortController();
            activeRequest = request;
            button.disabled = true;
            status.textContent = 'جارٍ البحث في سجلات المحاكاة…';
            try {
                const response = await fetch(root.dataset.lookupUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    signal: request.signal,
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    },
                    body: JSON.stringify({document_type: type.value, document_number: documentNumber}),
                });
                const payload = await response.json();
                if (!response.ok) {
                    const validationMessage = Object.values(payload.errors || {}).flat()[0];
                    throw new Error(validationMessage || payload.message || 'تعذر العثور على وثيقة مطابقة في سجلات المحاكاة.');
                }

                const record = payload.data;
                ['name', 'phone', 'issued_place'].forEach(field => {
                    if (autoFields[field]) autoFields[field].value = record[field] || '';
                });
                if (record.issued_at) {
                    const [year, month, day] = record.issued_at.split('-');
                    dateFields.querySelector('[name$="[issued_at_day]"]').value = String(Number(day));
                    dateFields.querySelector('[name$="[issued_at_month]"]').value = String(Number(month));
                    dateFields.querySelector('[name$="[issued_at_year]"]').value = String(Number(year));
                }
                lastLookup = `${type.value}\u0000${documentNumber}`;
                status.textContent = 'تمت التعبئة من سجل محاكاة. راجع البيانات؛ هذه ليست بيانات سجل حكومي.';
            } catch (error) {
                if (error.name !== 'AbortError') {
                    if (lastLookup) clearAutofill();
                    lastLookup = null;
                    status.textContent = error.message || 'تعذر البحث في سجلات المحاكاة.';
                }
            } finally {
                if (activeRequest === request) {
                    activeRequest = null;
                    button.disabled = false;
                }
            }
        });
    });
})();
