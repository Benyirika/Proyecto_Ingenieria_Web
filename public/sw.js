const CACHE_NAME = 'luxstay-static-v1';
const OFFLINE_URL = '/offline.html';

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => cache.addAll([OFFLINE_URL, '/icons/luxstay.svg'])),
    );
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((cacheNames) => Promise.all(
            cacheNames
                .filter((cacheName) => cacheName.startsWith('luxstay-static-') && cacheName !== CACHE_NAME)
                .map((cacheName) => caches.delete(cacheName)),
        )),
    );
    self.clients.claim();
});

self.addEventListener('fetch', (event) => {
    const request = event.request;
    const url = new URL(request.url);

    if (request.method !== 'GET' || url.origin !== self.location.origin) {
        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request).catch(() => caches.match(OFFLINE_URL)),
        );
        return;
    }

    const isStaticAsset = url.pathname.startsWith('/build/assets/')
        || url.pathname.startsWith('/icons/')
        || url.pathname === '/manifest.webmanifest';

    if (!isStaticAsset) {
        return;
    }

    event.respondWith(
        caches.match(request).then((cachedResponse) => {
            if (cachedResponse) {
                return cachedResponse;
            }

            return fetch(request).then((response) => {
                if (response.ok && response.type === 'basic') {
                    return caches.open(CACHE_NAME)
                        .then((cache) => cache.put(request, response.clone()))
                        .then(() => response)
                        .catch((error) => {
                            console.error('LuxStay static asset could not be cached.', error);
                            return response;
                        });
                }

                return response;
            });
        }),
    );
});
