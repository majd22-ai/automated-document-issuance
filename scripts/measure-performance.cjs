// Local CDP audit. Requires a separate Chrome with --remote-debugging-port=9223.
// Stores only public-page metrics; never signs in or changes application data.
const fs = require('node:fs');
(async () => {
  const label = process.argv[2] || 'final';
  const suite = process.argv[3] || 'normal';
  if (!/^[\w-]+$/.test(label)) throw new Error('Invalid output label');
  const tabs = await (await fetch('http://127.0.0.1:9223/json/list')).json();
  const ws = new WebSocket(tabs.find(t => t.type === 'page').webSocketDebuggerUrl);
  await new Promise(resolve => ws.addEventListener('open', resolve, { once: true }));
  let serial = 0, requests = new Map();
  const pending = new Map();
  const send = (method, params = {}) => new Promise((resolve, reject) => {
    pending.set(++serial, { resolve, reject }); ws.send(JSON.stringify({ id: serial, method, params }));
  });
  ws.onmessage = event => {
    const m = JSON.parse(event.data), p = m.params;
    if (m.id) { const r = pending.get(m.id); pending.delete(m.id); m.error ? r.reject(m.error) : r.resolve(m.result); return; }
    if (m.method === 'Network.requestWillBeSent') requests.set(p.requestId, { url: p.request.url, type: p.type, bytes: 0 });
    if (m.method === 'Network.responseReceived' && requests.has(p.requestId)) Object.assign(requests.get(p.requestId), { status: p.response.status, mime: p.response.mimeType, cached: !!p.response.fromDiskCache, headers: Object.fromEntries(Object.entries(p.response.headers).filter(([k]) => ['cache-control','etag','last-modified'].includes(k.toLowerCase()))) });
    if (m.method === 'Network.loadingFinished' && requests.has(p.requestId)) Object.assign(requests.get(p.requestId), { bytes: p.encodedDataLength, complete: true });
    if (m.method === 'Network.loadingFailed' && requests.has(p.requestId)) Object.assign(requests.get(p.requestId), { error: p.errorText, complete: true });
  };
  const evaluate = async expression => (await send('Runtime.evaluate', { expression, returnByValue: true })).result.value;
  await send('Network.enable'); await send('Page.enable'); await send('Network.clearBrowserCookies');
  await send('Page.addScriptToEvaluateOnNewDocument', { source: `window.__lcpEntries=[];new PerformanceObserver(list=>{for(const e of list.getEntries()) window.__lcpEntries.push({time:e.startTime,size:e.size,url:e.url,tag:e.element?.tagName,id:e.element?.id,classes:e.element?.className});}).observe({type:'largest-contentful-paint',buffered:true});` });
  const results = [];
  const paths = ['/', '/services/identity', '/services/passports', '/services/driving-licenses'];
  const cases = suite === 'slow' ? ['desktop','mobile'].flatMap(mode => ['Slow 3G','Fast 3G'].flatMap(network => paths.slice(0,3).map(path => ({ path, mode, network })))) : ['desktop','mobile'].flatMap(mode => paths.map(path => ({ path, mode, network: 'unthrottled' })));
  for (const { path, mode, network } of cases) {
    await send('Network.setCacheDisabled', { cacheDisabled: true });
    await send('Emulation.setDeviceMetricsOverride', { width: mode === 'mobile' ? 390 : 1366, height: mode === 'mobile' ? 844 : 900, deviceScaleFactor: 1, mobile: mode === 'mobile' });
    await send('Network.emulateNetworkConditions', { offline: false, latency: network === 'Slow 3G' ? 400 : network === 'Fast 3G' ? 150 : 0, downloadThroughput: network === 'Slow 3G' ? 62500 : network === 'Fast 3G' ? 200000 : -1, uploadThroughput: network === 'unthrottled' ? -1 : 62500 });
    await send('Page.navigate', { url: 'about:blank' }); await new Promise(r => setTimeout(r, 200)); requests = new Map();
    await send('Page.navigate', { url: 'http://127.0.0.1:8000' + path });
    await new Promise(r => setTimeout(r, network === 'unthrottled' ? 12000 : 30000));
    const metrics = JSON.parse(await evaluate(`JSON.stringify({title:document.title,lcp:window.__lcpEntries,paint:performance.getEntriesByType('paint').map(x=>({name:x.name,time:x.startTime})),decoded:[...performance.getEntriesByType('resource'),...performance.getEntriesByType('navigation')].reduce((n,x)=>n+x.decodedBodySize,0),images:[...document.images].map(x=>({src:x.currentSrc,natural:[x.naturalWidth,x.naturalHeight],rect:[x.getBoundingClientRect().x,x.getBoundingClientRect().y,x.getBoundingClientRect().width,x.getBoundingClientRect().height],class:x.className})),layout:[...document.querySelectorAll('.gov-header-visual,.gov-header-identity,.gov-welcome-picture,.service-banner,.service-banner-document')].map(x=>({class:x.className,rect:[x.getBoundingClientRect().x,x.getBoundingClientRect().y,x.getBoundingClientRect().width,x.getBoundingClientRect().height]}))})`));
    const rows = [...requests.values()].filter(r => /^https?:/.test(r.url));
    const result = { path, mode, network, bytes: rows.reduce((n,r)=>n+r.bytes,0), requests: rows.length, ...metrics, rows };
    results.push(result);
    fs.writeFileSync(`storage/app/performance-${label}-${suite}.json`, JSON.stringify(results,null,2));
    if (network === 'unthrottled') {
      const shot = await send('Page.captureScreenshot', { format: 'png' });
      fs.writeFileSync(`storage/app/performance-${label}-${mode}-${path === '/' ? 'home' : path.split('/').pop()}.png`, Buffer.from(shot.data,'base64'));
    }
    console.log(JSON.stringify({ path,mode,network,bytes:result.bytes,requests:result.requests,lcp:metrics.lcp?.at(-1),fcp:metrics.paint.find(x=>x.name==='first-contentful-paint')?.time,errors:rows.filter(x=>x.error||x.status>=400).length }));
  }
  await send('Network.emulateNetworkConditions', { offline:false,latency:0,downloadThroughput:-1,uploadThroughput:-1 }); ws.close();
})().catch(error => { console.error(error); process.exit(1); });
