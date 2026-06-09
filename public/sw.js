/**
 * Service worker en la raíz (scope /) para instalación PWA.
 * VetSaaS usa Inertia + HTML del servidor; no cacheamos rutas dinámicas.
 */
self.addEventListener('install', (event) => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(self.clients.claim());
});
