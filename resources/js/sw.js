/// Service worker (Workbox injectManifest), PRD 11.2.
import { precacheAndRoute, cleanupOutdatedCaches } from 'workbox-precaching';
import { registerRoute, setCatchHandler } from 'workbox-routing';
import { NetworkFirst, CacheFirst, NetworkOnly, StaleWhileRevalidate } from 'workbox-strategies';
import { ExpirationPlugin } from 'workbox-expiration';
import { flush } from './modules/queue';

cleanupOutdatedCaches();
precacheAndRoute(self.__WB_MANIFEST || []);

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open('pages').then((c) => c.add('/offline')).catch(() => {}));
});

self.addEventListener('message', (event) => {
    if (event.data?.type === 'SKIP_WAITING') self.skipWaiting();
});

// exams & attempts: network only, never cached
registerRoute(({ url }) => url.pathname.startsWith('/app/api/attempts') || url.pathname.startsWith('/app/api/exams') || url.pathname.startsWith('/exams'), new NetworkOnly(), 'GET');
registerRoute(({ url }) => url.pathname.startsWith('/app/api/attempts') || url.pathname.startsWith('/app/api/exams'), new NetworkOnly(), 'POST');

// flashcards due: stale-while-revalidate
registerRoute(({ url }) => url.pathname === '/app/api/flashcards/due', new StaleWhileRevalidate({ cacheName: 'api-flashcards' }));

// lesson images & audio: cache-first, 30 days, max 300 entries
registerRoute(
    ({ request, url }) => ['image', 'audio'].includes(request.destination) || url.pathname.startsWith('/storage/'),
    new CacheFirst({
        cacheName: 'lesson-media',
        plugins: [new ExpirationPlugin({ maxEntries: 300, maxAgeSeconds: 30 * 24 * 3600 })],
    }),
);

// learner HTML pages: network-first, fall back to cache, then /offline
registerRoute(
    ({ request, url }) => request.mode === 'navigate' && !url.pathname.startsWith('/admin') && !url.pathname.startsWith('/exams') && !url.pathname.startsWith('/horizon'),
    new NetworkFirst({ cacheName: 'pages', networkTimeoutSeconds: 4 }),
);

setCatchHandler(async ({ request }) => {
    if (request.destination === 'document') {
        return (await caches.match('/offline', { cacheName: 'pages' })) || Response.error();
    }
    return Response.error();
});

// Background Sync: replay practice answers and flashcard reviews made offline
self.addEventListener('sync', (event) => {
    if (event.tag === 'ep-sync') event.waitUntil(flush());
});

// Web Push (PRD 6.4)
self.addEventListener('push', (event) => {
    let data = {};
    try { data = event.data?.json() ?? {}; } catch (e) { data = { title: 'EnglishPath', body: event.data?.text() }; }
    event.waitUntil(self.registration.showNotification(data.title || 'EnglishPath', {
        body: data.body,
        icon: data.icon || '/icons/icon-192.png',
        badge: '/icons/icon-192.png',
        data: data.data || {},
    }));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const url = event.notification.data?.url || '/dashboard';
    event.waitUntil(self.clients.matchAll({ type: 'window' }).then((list) => {
        const open = list.find((c) => c.url.includes(url));
        return open ? open.focus() : self.clients.openWindow(url);
    }));
});
