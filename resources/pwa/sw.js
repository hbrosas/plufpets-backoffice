const CACHE_NAME = 'plufpets-pwa-v2';
const PRECACHE_URLS = ['/offline', '/icons/icon-192.png', '/icons/icon-512.png', '/icons/icon-maskable-512.png', '/icons/apple-touch-icon.png'];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches
            .open(CACHE_NAME)
            .then((cache) =>
                Promise.all(
                    PRECACHE_URLS.map(async (url) => {
                        try {
                            await cache.add(url);
                        } catch {
                            // A failed precache must not block installation.
                        }
                    }),
                ),
            )
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

    if (url.pathname.startsWith('/build/')) {
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

    const response = await fetch(request);

    if (response.ok && !response.redirected) {
        try {
            const cache = await caches.open(CACHE_NAME);
            await cache.put(request, response.clone());
        } catch {
            // Keep the network response when the cache rejects the request.
        }
    }

    return response;
}

async function networkFirstNavigation(request) {
    try {
        return await fetch(request);
    } catch {
        return (await caches.match('/offline')) ?? Response.error();
    }
}
