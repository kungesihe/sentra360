/* SENTRA 360 service worker.
 * - Caches only static files (styles, scripts, icons) and the offline page.
 * - Pages are ALWAYS fetched from the network: account data is never cached.
 * - When offline, shows /offline.html, which says plainly that alerts and
 *   reports cannot be sent and gives the emergency numbers.
 */
const CACHE = 'sentra-static-v8';
const STATIC = ['/offline.html', '/assets/css/app.css?v=5', '/assets/js/app.js?v=7', '/assets/icons/icon-192.png', '/favicon.svg'];

self.addEventListener('install', (e) => {
  e.waitUntil(caches.open(CACHE).then((c) => c.addAll(STATIC)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (e) => {
  e.waitUntil(caches.keys().then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k)))).then(() => self.clients.claim()));
});

self.addEventListener('fetch', (e) => {
  const req = e.request;
  if (req.method !== 'GET') return; // never touch form posts (panic alerts, reports)
  const url = new URL(req.url);
  if (url.origin !== self.location.origin) return;

  if (req.mode === 'navigate') {
    e.respondWith(fetch(req).catch(() => caches.match('/offline.html')));
    return;
  }
  if (url.pathname.startsWith('/assets/') || url.pathname.startsWith('/assets/icons/') || url.pathname === '/favicon.svg') {
    e.respondWith(caches.match(req).then((hit) => hit || fetch(req).then((res) => {
      const copy = res.clone();
      caches.open(CACHE).then((c) => c.put(req, copy));
      return res;
    })));
  }
});
