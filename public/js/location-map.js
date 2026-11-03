(() => {
  'use strict';
  document.querySelectorAll('iframe[data-map-src]').forEach(frame => {
    const load = () => {
      if (!frame.dataset.mapSrc) return;
      frame.src = frame.dataset.mapSrc;
      delete frame.dataset.mapSrc;
    };
    if (!('IntersectionObserver' in window)) { load(); return; }
    const observer = new IntersectionObserver(entries => {
      if (entries.some(entry => entry.isIntersecting)) { load(); observer.disconnect(); }
    }, { rootMargin: '300px' });
    observer.observe(frame);
    window.addEventListener('pagehide', () => observer.disconnect(), { once: true });
    window.addEventListener('pageshow', () => { if (frame.dataset.mapSrc) observer.observe(frame); });
  });
})();
