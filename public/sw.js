/*
 * Service worker SIKG (P3-02). Sengaja sempit demi privasi (M11): hanya menyimpan aset bundel statis (/build/*) dan halaman offline.
 * Halaman, respons Inertia, dan data pasien TIDAK pernah disimpan di cache. Antrean pencatatan offline ada di aplikasi (IndexedDB
 * terenkripsi), bukan di sini.
 */
const SHELL_CACHE = 'sikg-shell-v1';
const ASSET_CACHE = 'sikg-assets-v1';
const SHELL = ['/offline.html', '/manifest.webmanifest', '/icons/icon-192.png', '/icons/icon-512.png', '/favicon.ico'];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches
      .open(SHELL_CACHE)
      .then((cache) => cache.addAll(SHELL))
      .then(() => self.skipWaiting()),
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches
      .keys()
      .then((keys) => Promise.all(keys.filter((key) => key !== SHELL_CACHE && key !== ASSET_CACHE).map((key) => caches.delete(key))))
      .then(() => self.clients.claim()),
  );
});

self.addEventListener('fetch', (event) => {
  const request = event.request;
  if (request.method !== 'GET') {
    return;
  }
  const url = new URL(request.url);
  if (url.origin !== self.location.origin) {
    return;
  }

  // Aset bundel Vite bernama hash: aman disimpan dan tidak berubah isinya.
  if (url.pathname.startsWith('/build/')) {
    event.respondWith(
      caches.open(ASSET_CACHE).then((cache) =>
        cache.match(request).then(
          (hit) =>
            hit ||
            fetch(request).then((response) => {
              if (response.ok) {
                cache.put(request, response.clone());
              }
              return response;
            }),
        ),
      ),
    );
    return;
  }

  // Navigasi saat offline: halaman offline statis. Saat online, selalu dari jaringan (tanpa menyimpan).
  if (request.mode === 'navigate') {
    event.respondWith(fetch(request).catch(() => caches.match('/offline.html')));
  }
});
