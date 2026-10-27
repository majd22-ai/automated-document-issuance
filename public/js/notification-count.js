(() => {
  'use strict';
  const script = document.querySelector('script[data-count-url]');
  const badge = document.querySelector('.gov-notification-count');
  if (!script || !badge) return;
  let pending = false;
  async function refresh() {
    if (pending || document.visibilityState === 'hidden') return;
    pending = true;
    try {
      const response = await fetch(script.dataset.countUrl, { credentials: 'same-origin', headers: { Accept: 'application/json' }, cache: 'no-store' });
      if (!response.ok || !response.headers.get('content-type')?.includes('application/json')) return;
      const result = await response.json();
      if (Number.isSafeInteger(result.unread) && result.unread >= 0) badge.textContent = String(result.unread);
    } catch { /* Keep the current count when the network is unavailable. */ }
    finally { pending = false; }
  }
  refresh();
  setInterval(refresh, 30000);
  document.addEventListener('visibilitychange', refresh);
})();
