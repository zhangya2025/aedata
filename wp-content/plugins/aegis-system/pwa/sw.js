const CACHE_VERSION = 'v1';
const CACHE_NAME = `aegis-system-portal-${CACHE_VERSION}`;
const ASSETS = [
  '/wp-content/plugins/aegis-system/pwa/manifest.json',
  '/wp-content/plugins/aegis-system/pwa/icons/icon.svg',
  '/wp-content/plugins/aegis-system/pwa/sw.js',
  '/wp-content/plugins/aegis-system/assets/css/portal.css',
  '/wp-content/plugins/aegis-system/assets/css/typography.css',
  '/wp-content/plugins/aegis-system/assets/js/portal.js',
  '/wp-content/plugins/aegis-system/assets/js/portal-mobile.js',
  '/wp-content/plugins/aegis-system/assets/js/scanner-1d.js'
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => cache.addAll(ASSETS))
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(
        keys
          .filter((key) => key.startsWith('aegis-system-portal-') && key !== CACHE_NAME)
          .map((key) => caches.delete(key))
      )
    )
  );
});

self.addEventListener('fetch', (event) => {
  if (event.request.method !== 'GET') {
    return;
  }

  const url = new URL(event.request.url);

  if (url.origin !== self.location.origin) {
    return;
  }

  if (url.pathname.startsWith('/wp-json/') || url.pathname.includes('admin-ajax.php')) {
    return;
  }

  if (!url.pathname.startsWith('/wp-content/plugins/aegis-system/')) {
    return;
  }

  event.respondWith(
    fetch(event.request)
      .then((response) => {
        const copy = response.clone();
        caches.open(CACHE_NAME).then((cache) => cache.put(event.request, copy));
        return response;
      })
      .catch(() => caches.match(event.request))
  );
});
