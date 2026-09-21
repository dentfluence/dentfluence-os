/*
 |--------------------------------------------------------------------------
 | Dentfluence OS — Service Worker
 |--------------------------------------------------------------------------
 | Deliberately conservative. A clinic OS must NEVER show a stale patient
 | record, ledger balance or appointment slot from cache.
 |
 | Policy:
 |   - HTML / API / anything dynamic  -> network only (never cached)
 |   - Versioned & static assets      -> cache-first (safe, immutable-ish)
 |   - Navigation failure offline     -> /offline.html
 |
 | Bump CACHE_VERSION on every deploy that changes static assets.
 */

const CACHE_VERSION = 'df-v1';
const STATIC_CACHE  = `${CACHE_VERSION}-static`;
const OFFLINE_URL   = '/offline.html';

const PRECACHE = [
    OFFLINE_URL,
    '/icons/icon-192.png',
    '/icons/icon-512.png',
];

// Paths whose responses are safe to cache.
const STATIC_PREFIXES = ['/build/', '/css/', '/js/', '/images/', '/icons/', '/assets/', '/fonts/'];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(STATIC_CACHE)
            .then((cache) => cache.addAll(PRECACHE))
            .then(() => self.skipWaiting())
            .catch(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(
                keys.filter((k) => !k.startsWith(CACHE_VERSION)).map((k) => caches.delete(k))
            ))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('message', (event) => {
    if (event.data === 'SKIP_WAITING') self.skipWaiting();
});

function isStaticAsset(url) {
    return STATIC_PREFIXES.some((p) => url.pathname.startsWith(p));
}

self.addEventListener('fetch', (event) => {
    const request = event.request;

    // Only GET, only same-origin. Everything else goes straight to the network.
    if (request.method !== 'GET') return;

    const url = new URL(request.url);
    if (url.origin !== self.location.origin) return;

    // Page navigations: always network. Offline -> offline page.
    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request).catch(() => caches.match(OFFLINE_URL))
        );
        return;
    }

    // Static assets: cache-first, refresh in background.
    if (isStaticAsset(url)) {
        event.respondWith(
            caches.match(request).then((cached) => {
                const network = fetch(request).then((response) => {
                    if (response && response.status === 200 && response.type === 'basic') {
                        const copy = response.clone();
                        caches.open(STATIC_CACHE).then((cache) => cache.put(request, copy));
                    }
                    return response;
                }).catch(() => cached);

                return cached || network;
            })
        );
        return;
    }

    // Everything else (XHR, API, exports, PDFs): network only.
});
