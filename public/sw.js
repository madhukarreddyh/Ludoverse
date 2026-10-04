/* LudoVerse service worker.
 * - Cache-first for static assets (icons, manifest, offline page).
 * - Network-first for /play/* and /api/* (live match data must be fresh),
 *   with a cache fallback and the offline page as a last resort.
 * - Navigation failures fall back to /offline.html.
 */
const CACHE_NAME = 'ludoverse-v1';
const STATIC_ASSETS = [
    '/offline.html',
    '/manifest.json',
    '/icons/icon-192.png',
    '/icons/icon-512.png',
    '/icons/maskable-512.png',
    '/icons/apple-touch-icon.png',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => cache.addAll(STATIC_ASSETS))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(
                keys.filter((k) => k !== CACHE_NAME).map((k) => caches.delete(k))
            ))
            .then(() => self.clients.claim())
    );
});

function isLivePath(pathname) {
    return pathname.startsWith('/play/') || pathname.startsWith('/api/');
}

function networkFirst(request) {
    return fetch(request).then((response) => {
        const copy = response.clone();
        caches.open(CACHE_NAME).then((cache) => cache.put(request, copy));
        return response;
    }).catch(() =>
        caches.match(request).then((hit) => hit || caches.match('/offline.html'))
    );
}

function cacheFirst(request) {
    return caches.match(request).then((hit) => {
        if (hit) return hit;
        return fetch(request).then((response) => {
            const copy = response.clone();
            caches.open(CACHE_NAME).then((cache) => cache.put(request, copy));
            return response;
        }).catch(() => {
            if (request.mode === 'navigate') return caches.match('/offline.html');
            throw new Error('offline');
        });
    });
}

self.addEventListener('fetch', (event) => {
    const { request } = event;
    if (request.method !== 'GET') return;
    const url = new URL(request.url);
    if (url.origin !== self.location.origin) return;

    if (isLivePath(url.pathname)) {
        event.respondWith(networkFirst(request));
    } else {
        event.respondWith(cacheFirst(request));
    }
});
