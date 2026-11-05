// Public-page browser checks against the separate local Chrome CDP instance.
const fs = require('node:fs');
const assert = require('node:assert/strict');
(async () => {
  const tabs = await (await fetch('http://127.0.0.1:9223/json/list')).json();
  const ws = new WebSocket(tabs.find(t => t.type === 'page').webSocketDebuggerUrl);
  await new Promise(resolve => ws.addEventListener('open', resolve, { once: true }));
  let serial = 0;
  const pending = new Map();
  const send = (method, params = {}) => new Promise((resolve, reject) => {
    pending.set(++serial, { resolve, reject }); ws.send(JSON.stringify({ id: serial, method, params }));
  });
  ws.onmessage = event => { const m = JSON.parse(event.data); if (m.id) { const p = pending.get(m.id); pending.delete(m.id); m.error ? p.reject(m.error) : p.resolve(m.result); } };
  const read = async expression => (await send('Runtime.evaluate', { expression, returnByValue: true })).result.value;
  const wait = ms => new Promise(resolve => setTimeout(resolve, ms));
  await send('Network.enable'); await send('Page.enable');
  await send('Network.emulateNetworkConditions', { offline: false, latency: 0, downloadThroughput: -1, uploadThroughput: -1 });
  const results = [];
  for (const fallback of [false, true]) {
    await send('Emulation.setDisabledImageTypes', { imageTypes: fallback ? ['avif'] : [] });
    for (const dpr of [1, 2]) {
      await send('Network.setCacheDisabled', { cacheDisabled: true });
      await send('Emulation.setDeviceMetricsOverride', { width: 390, height: 844, deviceScaleFactor: dpr, mobile: true });
      await send('Page.navigate', { url: 'about:blank' }); await wait(200);
      await send('Page.navigate', { url: 'http://127.0.0.1:8000/' }); await wait(5000);
      const images = await read('[...document.images].map(x=>({src:x.currentSrc,loaded:x.complete&&x.naturalWidth>0}))');
      assert.ok(images.every(x => x.loaded), 'Every public image must decode');
      const header = images.find(x => x.src.includes('header-left-'));
      if (!header) throw new Error(JSON.stringify({ fallback, dpr, images }));
      assert.ok(header.src.endsWith(fallback ? '.webp' : '.avif'));
      assert.ok(header.src.includes(dpr === 1 ? '-320.' : '-640.'));
      const beforeMap = await read('({src:document.querySelector("iframe[data-map-src]")?.getAttribute("src"),external:performance.getEntriesByType("resource").filter(x=>new URL(x.name).origin!==location.origin).length})');
      assert.equal(beforeMap.external, 0);
      results.push({ fallback, dpr, header: header.src, imagesLoaded: images.length, beforeMap });
    }
  }
  await send('Emulation.setDisabledImageTypes', { imageTypes: [] });
  // Check actual map activation once without replacing its appearance or controls.
  await read('document.querySelector("iframe[data-map-src]").scrollIntoView()'); await wait(12000);
  const map = await read('({src:document.querySelector("iframe")?.src, pending:document.querySelectorAll("iframe[data-map-src]").length})');
  assert.ok(map.src?.includes('maps.google')); assert.equal(map.pending, 0);
  await read('window.scrollTo(0,0)'); await wait(500); await read('document.querySelector("iframe").scrollIntoView()'); await wait(500);
  assert.equal(await read('document.querySelectorAll("iframe").length'), 1);
  fs.writeFileSync('storage/app/responsive-browser-checks.json', JSON.stringify({ results, map }, null, 2));
  console.log(JSON.stringify({ passed: true, cases: results.length, map })); ws.close();
})().catch(error => { console.error(error); process.exit(1); });
