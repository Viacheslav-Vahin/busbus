// public/driver/sw.js
const CACHE = 'driver-v1';
const OFFLINE_URLS = [
    '/driver/app',
    '/driver/manifest.webmanifest',
];

self.addEventListener('install', e => {
    e.waitUntil(caches.open(CACHE).then(c => c.addAll(OFFLINE_URLS)));
    self.skipWaiting();
});

self.addEventListener('activate', e => self.clients.claim());

self.addEventListener('fetch', e => {
    const { request } = e;
    e.respondWith(
        caches.match(request).then(r => r || fetch(request).then(resp => {
            const copy = resp.clone();
            caches.open(CACHE).then(c => c.put(request, copy));
            return resp;
        }).catch(() => caches.match('/driver/app')))
    );
});
