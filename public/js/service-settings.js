(() => {
  const form = document.querySelector('[data-service-settings]');
  if (!form) return;
  // Reveal invalid fields inside closed sections before native browser validation focuses them.
  form.addEventListener('invalid', event => {
    let section = event.target.parentElement;
    while (section && section !== form) {
      if (section.tagName === 'DETAILS') section.open = true;
      section = section.parentElement;
    }
  }, true);
})();
