// Service worker for the installable website (PWA).
// - Precaches the offline page and brand icons.
// - Static build assets (/build/, /images/, /icons/) use cache-first (they are content-hashed or immutable).
// - Page navigations use network-first and fall back to /offline when the network is down.
// - Account, admin, staff, API and any non-GET request always go to the network and are never cached.
const VERSION = 'arj-v1';
const PRECACHE = ['/offline', '/icons/icon-192.png', '/icons/icon-512.png'];
const NEVER = [/^\/account/, /^\/admin/, /^\/staff/, /^\/api\//, /^\/enquiries/, /^\/places/];

self.addEventListener('install', (e) => {
  e.waitUntil(caches.open(VERSION).then((c) => c.addAll(PRECACHE)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (e) => {
  e.waitUntil(caches.keys().then((keys) => Promise.all(keys.filter((k) => k !== VERSION).map((k) => caches.delete(k)))).then(() => self.clients.claim()));
});

self.addEventListener('fetch', (e) => {
  const req = e.request;
  const url = new URL(req.url);
  if (req.method !== 'GET' || url.origin !== location.origin || NEVER.some((r) => r.test(url.pathname))) return;

  if (/^\/(build|images|icons)\//.test(url.pathname)) {
    e.respondWith(caches.match(req).then((hit) => hit || fetch(req).then((res) => {
      if (res.ok) { const copy = res.clone(); caches.open(VERSION).then((c) => c.put(req, copy)); }
      return res;
    })));
    return;
  }
  if (req.mode === 'navigate') {
    e.respondWith(fetch(req).catch(() => caches.match('/offline')));
  }
});
