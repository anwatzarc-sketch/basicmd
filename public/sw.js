/**
 * Service worker - installable-app shell only.
 *
 * This deliberately does NOT cache pages. The app serves patient records,
 * appointment status, and financial balances that must never be shown stale
 * or served from a shared browser's cache after someone signs out - a
 * cached admin or portal page sitting in Cache Storage would be a real PHI
 * leak on a shared front-desk or kiosk machine, and none of that is worth
 * the offline convenience.
 *
 * What IS cached, and why it's safe to:
 *   - /assets/* and /dist/* - CSS, JS, fonts, icons. Every one of these
 *     URLs is fingerprinted with a ?v=<mtime> query string (see
 *     View::asset()), so a given URL's content can never change under a
 *     cached response - caching it aggressively is exactly as safe as the
 *     browser's own HTTP cache would be, just resilient to being offline.
 *   - offline.html and the manifest/icons - static, public, identical for
 *     every visitor.
 *
 * Every navigation (an actual page load) always goes to the network first.
 * The cache is only ever used as a fallback when that fetch fails outright
 * - never as a shortcut to avoid asking the server what's true right now.
 */

const SHELL_CACHE = 'medicaremini-shell-v1';

const PRECACHE_URLS = [
    '/offline.html',
    '/manifest.webmanifest',
    '/assets/img/favicon.svg',
    '/assets/img/icons/icon-192.png',
    '/assets/img/icons/icon-512.png',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(SHELL_CACHE)
            .then((cache) => cache.addAll(PRECACHE_URLS))
            .then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(
                keys.filter((key) => key !== SHELL_CACHE).map((key) => caches.delete(key)),
            ))
            .then(() => self.clients.claim()),
    );
});

/** Static, fingerprinted assets only - never a page, never an API response. */
function isCacheableAsset(url) {
    return url.origin === self.location.origin
        && (url.pathname.startsWith('/assets/') || url.pathname.startsWith('/dist/'));
}

self.addEventListener('fetch', (event) => {
    const { request } = event;

    // Only ever act on our own GET requests. POST (form submissions),
    // cross-origin calls, and anything else pass straight through
    // untouched - the same as if this worker did not exist.
    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);

    if (url.origin !== self.location.origin) {
        return;
    }

    // A real page load: network first, offline shell on failure. This is
    // the request type most likely to carry a session-specific, possibly
    // sensitive page, so it is the one case this worker refuses to ever
    // answer from cache.
    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request).catch(() => caches.match('/offline.html')),
        );
        return;
    }

    if (isCacheableAsset(url)) {
        event.respondWith(
            caches.match(request).then((cached) => {
                if (cached) {
                    return cached;
                }

                return fetch(request).then((response) => {
                    if (response.ok) {
                        const copy = response.clone();
                        caches.open(SHELL_CACHE).then((cache) => cache.put(request, copy));
                    }

                    return response;
                });
            }),
        );
    }

    // Everything else (API calls, uploaded media, anything dynamic):
    // no respondWith() at all, so the browser handles it exactly as it
    // would with no service worker installed.
});
