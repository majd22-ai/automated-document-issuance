 document.addEventListener('DOMContentLoaded', () => {
    const dropdowns = document.querySelectorAll('.gov-nav-dropdown');
    const setOpen = (dropdown, open) => {
        dropdown.classList.toggle('is-open', open);
        dropdown.querySelector('.gov-dropdown-trigger')?.setAttribute('aria-expanded', String(open));
    };
    const closeDropdowns = () => dropdowns.forEach(dropdown => setOpen(dropdown, false));
    dropdowns.forEach(dropdown => {
        dropdown.querySelector('.gov-dropdown-trigger')?.addEventListener('click', () => {
            const open = !dropdown.classList.contains('is-open');
            closeDropdowns();
            setOpen(dropdown, open);
        });
    });
    const toggle = document.querySelector('.gov-nav-toggle');
    const checkbox = document.querySelector('.gov-nav-check');
    toggle?.addEventListener('click', () => {
        if (!checkbox) return;
        checkbox.checked = !checkbox.checked;
        toggle.setAttribute('aria-expanded', String(checkbox.checked));
        if (!checkbox.checked) closeDropdowns();
    });
    document.addEventListener('click', event => {
        if (!event.target.closest('.gov-nav-dropdown')) closeDropdowns();
        if (!event.target.closest('.gov-main-nav') && checkbox?.checked) {
            checkbox.checked = false;
            toggle?.setAttribute('aria-expanded', 'false');
        }
    });
    document.addEventListener('keydown', event => {
        if (event.key !== 'Escape') return;
        const activeDropdown = document.activeElement?.closest('.gov-nav-dropdown.is-open');
        closeDropdowns();
        if (activeDropdown) {
            activeDropdown.querySelector('.gov-dropdown-trigger')?.focus();
        } else if (checkbox?.checked) {
            checkbox.checked = false;
            toggle?.setAttribute('aria-expanded', 'false');
            toggle?.focus();
        }
    });
});
