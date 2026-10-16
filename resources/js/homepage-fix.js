document.addEventListener('DOMContentLoaded', () => {
    const dropdowns = document.querySelectorAll('.gov-nav-dropdown');

    dropdowns.forEach((dropdown) => {
        const trigger = dropdown.querySelector('.gov-dropdown-trigger');

        if (!trigger) return;

        trigger.addEventListener('click', () => {
            const isOpen = dropdown.classList.contains('is-open');

            dropdowns.forEach((item) => item.classList.remove('is-open'));

            dropdown.classList.toggle('is-open', !isOpen);
        });
    });

    document.addEventListener('click', (event) => {
        if (!event.target.closest('.gov-nav-dropdown')) {
            dropdowns.forEach((item) => item.classList.remove('is-open'));
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            dropdowns.forEach((item) => item.classList.remove('is-open'));
        }
    });
});