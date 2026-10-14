const CACHE = 'saldoup-static-v2';
const PUBLIC_FILES = ['/offline.html', '/icons/icon.svg', '/icons/icon-192.png', '/icons/icon-512.png', '/manifest.webmanifest'];
function isStaticResponse(path, response) {
    if (!response.ok || response.redirected || response.headers.get('Cache-Control')?.includes('no-store')) return false;
    const type = response.headers.get('Content-Type') || '';
    if (path === '/offline.html') return type.startsWith('text/html');
    if (path === '/manifest.webmanifest') return /application\/(manifest\+json|json)/.test(type);
    if (path.endsWith('.css')) return type.startsWith('text/css');
    if (path.endsWith('.js')) return /^(text|application)\/(javascript|ecmascript)/.test(type);
    if (/\.woff2?$/.test(path)) return /^(font\/|application\/font)/.test(type);
    return type.startsWith('image/');
}
self.addEventListener('install', event => event.waitUntil((async () => {
    const cache = await caches.open(CACHE);
    for (const path of PUBLIC_FILES) {
        const request = new Request(new URL(path, self.location.origin), { credentials: 'omit' });
        const response = await fetch(request);
        if (!isStaticResponse(path, response)) throw new Error('Arquivo público indisponível.');
        await cache.put(request, response);
    }
})()));
self.addEventListener('activate', event => event.waitUntil(Promise.all([caches.keys().then(keys => Promise.all(keys.filter(key => key.startsWith('saldoup-static-') && key !== CACHE).map(key => caches.delete(key)))), self.clients.claim()])));
self.addEventListener('message', event => { if (event.data?.type === 'SKIP_WAITING') self.skipWaiting(); });
self.addEventListener('fetch', event => {
    const request = event.request;
    if (request.method !== 'GET') return;
    const url = new URL(request.url);
    if (url.origin !== self.location.origin) return;
    if (request.mode === 'navigate') {
        event.respondWith(fetch(request).catch(() => caches.match('/offline.html')));
        return;
    }
    const asset = !url.search && (PUBLIC_FILES.includes(url.pathname) || /^\/build\/assets\/[a-zA-Z0-9_.-]+\.(css|js|woff2?|svg|png|webp)$/.test(url.pathname));
    if (!asset) return;
    event.respondWith(caches.open(CACHE).then(async cache => {
        const hit = await cache.match(request);
        if (hit) return hit;
        const response = await fetch(new Request(request, { credentials: 'omit' }));
        if (isStaticResponse(url.pathname, response)) {
            await cache.put(request, response.clone());
            const keys = await cache.keys();
            if (keys.length > 60) await cache.delete(keys.find(key => !PUBLIC_FILES.includes(new URL(key.url).pathname)));
        }
        return response;
    }));
});
