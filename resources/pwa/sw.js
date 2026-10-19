const CACHE_NAME = 'plufpets-pwa-v1';
const PRECACHE_URLS = ['/offline', '/icons/icon-192.png', '/icons/icon-512.png', '/icons/apple-touch-icon.png'];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches
            .open(CACHE_NAME)
            .then((cache) => cache.addAll(PRECACHE_URLS))
            .then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((keys) => Promise.all(keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key))))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const { request } = event;

    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);

    if (url.origin !== self.location.origin) {
        return;
    }

    if (url.pathname.startsWith('/build/') || url.pathname.startsWith('/icons/')) {
        event.respondWith(cacheFirst(request));

        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(networkFirstNavigation(request));
    }
});

async function cacheFirst(request) {
    const cached = await caches.match(request);

    if (cached) {
        return cached;
    }

    try {
        const response = await fetch(request);

        if (response.ok && !response.redirected) {
            const cache = await caches.open(CACHE_NAME);
            await cache.put(request, response.clone());
        }

        return response;
    } catch {
        return Response.error();
    }
}

async function networkFirstNavigation(request) {
    try {
        return await fetch(request);
    } catch {
        return (await caches.match('/offline')) ?? Response.error();
    }
}
