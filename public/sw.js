/**
 * Service Worker VetSaaS — Fase 8 offline (+ centro de sync /offline/cola).
 */
const STATIC_CACHE = 'vetsaas-static-v10';
const INERTIA_OFFLINE_CACHE = 'vetsaas-inertia-offline-v10';
const OFFLINE_PREFIXES = [
    '/offline',
    '/caja',
    '/clinica',
    '/servicios',
    '/inventario',
    '/facturacion',
    '/comunicaciones',
    '/reportes',
    '/configuracion',
];

/** Rutas de plataforma/superadmin: siempre red, nunca caché (evita 500 por assets viejos en PWA). */
const NETWORK_ONLY_PREFIXES = ['/plataforma', '/login', '/dashboard'];

self.addEventListener('install', (event) => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((keys) =>
                Promise.all(
                    keys
                        .filter(
                            (key) =>
                                key !== STATIC_CACHE &&
                                key !== INERTIA_OFFLINE_CACHE,
                        )
                        .map((key) => caches.delete(key)),
                ),
            )
            .then(() => self.clients.claim()),
    );
});

function isNavigationOrInertia(request) {
    return (
        request.mode === 'navigate' ||
        request.headers.get('X-Inertia') === 'true' ||
        request.headers.get('X-Requested-With') === 'XMLHttpRequest'
    );
}

function isOfflineModulePath(pathname) {
    return OFFLINE_PREFIXES.some(
        (prefix) => pathname === prefix || pathname.startsWith(`${prefix}/`),
    );
}

function isNetworkOnlyPath(pathname) {
    return NETWORK_ONLY_PREFIXES.some(
        (prefix) => pathname === prefix || pathname.startsWith(`${prefix}/`),
    );
}

function isStaticAsset(url) {
    return (
        url.pathname.startsWith('/build/') ||
        url.pathname.startsWith('/icons/') ||
        url.pathname.endsWith('.css') ||
        url.pathname.endsWith('.js') ||
        url.pathname.endsWith('.woff2')
    );
}

async function cachePut(request, response) {
    if (!response || !response.ok) {
        return;
    }

    const cache = await caches.open(INERTIA_OFFLINE_CACHE);
    await cache.put(request, response.clone());
}

async function matchByPathname(url) {
    const cache = await caches.open(INERTIA_OFFLINE_CACHE);
    const keys = await cache.keys();

    for (const req of keys) {
        const cachedUrl = new URL(req.url);

        if (cachedUrl.pathname === url.pathname) {
            return cache.match(req);
        }
    }

    return undefined;
}

self.addEventListener('fetch', (event) => {
    if (event.request.method !== 'GET') {
        return;
    }

    const url = new URL(event.request.url);

    if (url.origin !== self.location.origin) {
        return;
    }

    if (isNetworkOnlyPath(url.pathname)) {
        event.respondWith(fetch(event.request));
        return;
    }

    if (isStaticAsset(url)) {
        event.respondWith(
            caches.open(STATIC_CACHE).then(async (cache) => {
                const cached = await cache.match(event.request);

                if (cached) {
                    return cached;
                }

                try {
                    const response = await fetch(event.request);

                    if (response.ok) {
                        await cache.put(event.request, response.clone());
                    }

                    return response;
                } catch {
                    return cached || Response.error();
                }
            }),
        );

        return;
    }

    if (!isOfflineModulePath(url.pathname)) {
        return;
    }

    if (!isNavigationOrInertia(event.request)) {
        return;
    }

    event.respondWith(
        (async () => {
            try {
                const response = await fetch(event.request);
                await cachePut(event.request, response);

                return response;
            } catch {
                const exact = await caches.match(event.request);

                if (exact) {
                    return exact;
                }

                const byPath = await matchByPathname(url);

                if (byPath) {
                    return byPath;
                }

                return new Response(
                    JSON.stringify({
                        message:
                            'Sin conexión. Abre Caja, Clínica o Inventario con internet al menos una vez.',
                    }),
                    {
                        status: 503,
                        headers: { 'Content-Type': 'application/json' },
                    },
                );
            }
        })(),
    );
});
