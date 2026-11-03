(() => {
  'use strict';
  const script = document.querySelector('script[data-count-url]');
  const badge = document.querySelector('.gov-notification-count');
  if (!script || !badge) return;
  if (script.dataset.pollingStarted) return;
  script.dataset.pollingStarted = 'true';
  let pending = false;
  let timer = null;
  let controller;
  let lastAttempt = -Infinity;
  let stopped = false;
  async function refresh() {
    if (stopped || pending || document.visibilityState === 'hidden' || Date.now() - lastAttempt < 120000) return;
    pending = true;
    lastAttempt = Date.now();
    controller = new AbortController();
    try {
      const response = await fetch(script.dataset.countUrl, { credentials: 'same-origin', headers: { Accept: 'application/json' }, cache: 'no-store', signal: controller.signal });
      if ([401, 403, 419].includes(response.status)) { stopped = true; pause(); return; }
      if (!response.ok || !response.headers.get('content-type')?.includes('application/json')) return;
      const result = await response.json();
      if (Number.isSafeInteger(result.unread) && result.unread >= 0) badge.textContent = String(result.unread);
    } catch { /* Keep the current count when the network is unavailable. */ }
    finally { pending = false; }
  }
  function pause() {
    if (timer !== null) clearInterval(timer);
    timer = null;
    controller?.abort();
  }
  function resume() {
    if (stopped || document.visibilityState === 'hidden') return;
    if (timer === null) timer = setInterval(refresh, 120000);
    refresh();
  }
  document.addEventListener('visibilitychange', () => document.visibilityState === 'hidden' ? pause() : resume());
  window.addEventListener('pagehide', pause);
  window.addEventListener('pageshow', resume);
  resume();
})();
