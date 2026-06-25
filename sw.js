const CACHE_NAME = "radeon-v2";
const STATIC_ASSETS = [
  "/",
  "/assets/index.css",
  "/assets/index.js",
  "/manifest.json"
];

// Install: cache static assets
self.addEventListener("install", event => {
  event.waitUntil(
    caches.open(CACHE_NAME).then(cache => {
      return cache.addAll(STATIC_ASSETS);
    }).then(() => self.skipWaiting())
  );
});

// Activate: remove old caches
self.addEventListener("activate", event => {
  event.waitUntil(
    caches.keys().then(keys =>
      Promise.all(
        keys.filter(k => k !== CACHE_NAME).map(k => caches.delete(k))
      )
    ).then(() => self.clients.claim())
  );
});

// Fetch: network-first for API/PHP, cache-first for static assets
self.addEventListener("fetch", event => {
  const url = new URL(event.request.url);

  // Always network for PHP pages, API calls, POST requests
  if (
    event.request.method !== "GET" ||
    url.pathname.endsWith(".php") ||
    url.pathname.startsWith("/api/") ||
    url.pathname.startsWith("/version/")
  ) {
    event.respondWith(fetch(event.request));
    return;
  }

  // Cache-first for static assets (CSS, JS, fonts, images)
  if (
    url.pathname.startsWith("/assets/") ||
    url.pathname.startsWith("/fonts/") ||
    url.pathname.match(/\.(woff2?|ttf|ico|png|svg|webp)$/)
  ) {
    event.respondWith(
      caches.match(event.request).then(cached => {
        return cached || fetch(event.request).then(response => {
          if (response.ok) {
            const clone = response.clone();
            caches.open(CACHE_NAME).then(c => c.put(event.request, clone));
          }
          return response;
        });
      })
    );
    return;
  }

  // Network-first for everything else
  event.respondWith(
    fetch(event.request).catch(() => caches.match(event.request))
  );
});