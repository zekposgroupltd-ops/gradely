/* GRADELY service worker.
 *
 * Privacy rule: pages and API responses are NEVER cached. Result data and dashboards must not
 * sit in a shared phone's cache. Only static design assets and a generic offline page are stored.
 * Bump VERSION on every release so installed apps pick up new assets.
 */
const VERSION = 'gradely-v5';
const STATIC_CACHE = VERSION + '-static';
const scope = self.registration.scope; // always ends with "/", works under a sub-folder too
const abs = (p) => new URL(p, scope).href;
const OFFLINE_URL = abs('offline.html');
const ASSET_PREFIX = new URL('assets/', scope).pathname;
const PRECACHE = [
  'offline.html',
  'assets/css/app.css',
  'assets/js/pwa.js',
  'assets/js/app.js',
  'assets/css/report.css',
  'assets/img/gradely-logo.png',
  'assets/icons/icon-192.png',
].map(abs);

self.addEventListener('install', (event) => {
  // No automatic skipWaiting: the page shows an "Update available" prompt instead.
  event.waitUntil(caches.open(STATIC_CACHE).then((cache) => cache.addAll(PRECACHE)));
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(keys.filter((k) => k.startsWith('gradely-') && k !== STATIC_CACHE).map((k) => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('message', (event) => {
  if (event.data && event.data.type === 'SKIP_WAITING') {
    self.skipWaiting();
  }
});

self.addEventListener('fetch', (event) => {
  const req = event.request;
  if (req.method !== 'GET') return;
  const url = new URL(req.url);
  if (url.origin !== self.location.origin) return;

  // Page loads: always go to the network; only when offline show the generic offline page.
  if (req.mode === 'navigate') {
    event.respondWith(fetch(req).catch(() => caches.match(OFFLINE_URL)));
    return;
  }

  // Static design assets: stale-while-revalidate.
  if (url.pathname.startsWith(ASSET_PREFIX)) {
    event.respondWith(
      caches.open(STATIC_CACHE).then(async (cache) => {
        const cached = await cache.match(req);
        const network = fetch(req).then((res) => {
          if (res && res.ok) cache.put(req, res.clone());
          return res;
        }).catch(() => cached);
        return cached || network;
      })
    );
  }
  // Everything else (uploads, downloads, API-like routes): untouched, network only.
});
