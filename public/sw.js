const VERSION = 'v1';
const SHELL_CACHE = `asaan-shell-${VERSION}`;
const ASSET_CACHE = `asaan-assets-${VERSION}`;
const IMAGE_CACHE = `asaan-images-${VERSION}`;
const OWNED_CACHES = [SHELL_CACHE, ASSET_CACHE, IMAGE_CACHE];

const SHELL_URLS = ['/offline.html', '/manifest.webmanifest', '/icons/pwa-192x192.png'];

const NEVER_CACHE = ['/api/', '/livewire/', '/sanctum/', '/admin/'];
const NEVER_CACHE_EXT = ['.php', '.json'];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches
            .open(SHELL_CACHE)
            .then((cache) => cache.addAll(SHELL_URLS))
            .catch(() => undefined)
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((keys) =>
                Promise.all(keys.filter((k) => !OWNED_CACHES.includes(k)).map((k) => caches.delete(k)))
            )
            .then(() => self.clients.claim())
    );
});

function isCacheableImage(request, url) {
    return (
        url.origin === self.location.origin &&
        request.destination === 'image' &&
        !NEVER_CACHE.some((p) => url.pathname.startsWith(p))
    );
}

function isStaticAsset(url) {
    return (
        url.origin === self.location.origin &&
        (url.pathname.startsWith('/build/') ||
            url.pathname.startsWith('/templates/') ||
            url.pathname.startsWith('/css/') ||
            url.pathname.startsWith('/js/') ||
            url.pathname.startsWith('/icons/') ||
            url.pathname.startsWith('/logos/') ||
            url.pathname.startsWith('/fonts/') ||
            /\.(?:css|js|woff2?|ttf|svg|png|jpe?g|gif|webp|avif)$/i.test(url.pathname))
    );
}

function shouldBypass(request, url) {
    if (request.method !== 'GET') return true;
    if (NEVER_CACHE.some((p) => url.pathname.startsWith(p))) return true;
    if (NEVER_CACHE_EXT.some((ext) => url.pathname.endsWith(ext))) return true;
    // Inertia partial reloads must never be served from cache.
    if (request.headers.get('X-Inertia')) return true;
    if (request.headers.get('X-Requested-With') === 'XMLHttpRequest') return true;
    if (request.headers.get('Accept')?.includes('text/vnd.inertia')) return true;
    return false;
}

async function cacheFirst(request) {
    const cached = await caches.match(request);
    if (cached) return cached;
    const response = await fetch(request);
    if (response.ok) {
        const clone = response.clone();
        caches.open(ASSET_CACHE).then((cache) => cache.put(request, clone));
    }
    return response;
}

async function staleWhileRevalidate(request) {
    const cache = await caches.open(IMAGE_CACHE);
    const cached = await cache.match(request);
    const network = fetch(request)
        .then((response) => {
            if (response.ok) cache.put(request, response.clone());
            return response;
        })
        .catch(() => undefined);
    return cached || network || Response.error();
}

async function networkFirstNavigation(request) {
    try {
        const response = await fetch(request);
        if (response.ok) {
            const clone = response.clone();
            caches.open(ASSET_CACHE).then((cache) => cache.put(request, clone));
        }
        return response;
    } catch {
        const cached = await caches.match(request);
        if (cached) return cached;
        const offline = await caches.match('/offline.html');
        if (offline) return offline;
        return new Response('You are offline.', {
            status: 503,
            headers: { 'Content-Type': 'text/plain; charset=utf-8' },
        });
    }
}

self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);

    if (shouldBypass(request, url)) return;

    if (request.mode === 'navigate') {
        event.respondWith(networkFirstNavigation(request));
        return;
    }

    if (isCacheableImage(request, url)) {
        event.respondWith(staleWhileRevalidate(request));
        return;
    }

    if (isStaticAsset(url)) {
        event.respondWith(cacheFirst(request));
    }
});