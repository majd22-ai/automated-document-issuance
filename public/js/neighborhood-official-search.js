(() => {
    const form = document.querySelector('[data-official-suggestions]');
    if (!form) return;

    const input = form.elements.namedItem('q');
    const selection = form.elements.namedItem('official_id');
    const list = form.querySelector('#official-name-suggestions');
    const status = form.querySelector('[data-suggestion-status]');
    let timer;
    let pending;

    function clearSuggestions() {
        clearTimeout(timer);
        pending?.abort();
        list.replaceChildren();
        list.hidden = true;
        status.textContent = '';
    }

    async function loadSuggestions(query) {
        pending = new AbortController();
        const { signal } = pending;
        const url = new URL(form.dataset.officialSuggestions, window.location.href);
        url.searchParams.set('q', query);
        try {
            const response = await fetch(url, { signal, headers: { Accept: 'application/json' } });
            if (!response.ok) throw new Error('Suggestions unavailable');
            const rows = await response.json();
            if (signal.aborted) return;
            for (const row of rows) {
                const item = document.createElement('li');
                const button = document.createElement('button');
                button.type = 'button';
                button.textContent = row.name + ' — ' + row.location;
                button.addEventListener('click', () => {
                    input.value = row.name;
                    selection.value = row.id;
                    form.requestSubmit();
                });
                item.append(button);
                list.append(item);
            }
            list.hidden = rows.length === 0;
            status.textContent = rows.length ? `الاقتراحات المتاحة ${rows.length}` : 'لم يُعثر على أسماء مطابقة.';
        } catch {
            if (!signal.aborted) status.textContent = 'تعذّر تحميل الاقتراحات. يمكنك الضغط على بحث.';
        }
    }

    input.addEventListener('input', () => {
        selection.value = '';
        clearSuggestions();
        const query = input.value.trim();
        if (query) timer = setTimeout(() => loadSuggestions(query), 250);
    });

    form.addEventListener('keydown', event => {
        if (event.key === 'Escape' && !list.hidden) {
            event.preventDefault();
            clearSuggestions();
            input.focus();
        }
    });
    document.addEventListener('pointerdown', event => {
        if (!form.contains(event.target)) clearSuggestions();
    });
})();
