/* Service worker File Manager.
 * KEAMANAN: hanya aset statis publik (/assets/...) yang di-cache. Halaman, API, unduhan,
 * pratinjau, dan tautan berbagi TIDAK pernah disimpan, jadi tidak ada data pengguna
 * yang tertinggal di cache perangkat. Saat offline, navigasi menampilkan halaman offline. */
const VERSION = 'fm-v1.8.1';
const STATIC_CACHE = 'static-' + VERSION;
const OFFLINE_HTML = `<!doctype html><html lang="id"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex">
<title>Tidak ada koneksi</title><style>
:root{color-scheme:light dark}
body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;background:#f8f9fa;color:#212529;text-align:center;padding:1.5rem}
@media(prefers-color-scheme:dark){body{background:#212529;color:#e9ecef}p{color:#adb5bd!important}}
.b{max-width:26rem}h1{font-size:1.4rem;margin:.8rem 0 .4rem}p{color:#6c757d;margin:0 0 1.4rem}
button{background:#0d6efd;color:#fff;border:0;border-radius:.5rem;padding:.6rem 1.3rem;font-size:1rem;cursor:pointer}
svg{width:84px;height:84px;color:#0d6efd}</style></head><body><div class="b">
<svg viewBox="0 0 16 16" fill="currentColor"><path d="M8 3.5a.5.5 0 0 0-1 0V9a.5.5 0 0 0 .252.434l3.5 2a.5.5 0 0 0 .496-.868L8 8.71z"/><path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16m7-8A7 7 0 1 1 1 8a7 7 0 0 1 14 0"/></svg>
<h1>Tidak ada koneksi internet</h1>
<p>File Manager butuh koneksi untuk menampilkan berkas Anda. Periksa jaringan lalu coba lagi.</p>
<button onclick="location.reload()">Coba lagi</button></div></body></html>`;

const SERVER_HTML = OFFLINE_HTML
  .replace('Tidak ada koneksi internet', 'Server tidak merespons')
  .replace('File Manager butuh koneksi untuk menampilkan berkas Anda. Periksa jaringan lalu coba lagi.', 'Koneksi Anda aktif, tetapi server tidak menjawab (mungkin sibuk atau sedang dipulihkan). Tunggu sebentar lalu coba lagi.')
  .replace('<title>Tidak ada koneksi</title>', '<title>Server tidak merespons</title>');

self.addEventListener('install', (e) => { self.skipWaiting(); });

self.addEventListener('activate', (e) => {
  e.waitUntil((async () => {
    for (const k of await caches.keys()) {
      if (k.startsWith('static-') && k !== STATIC_CACHE) await caches.delete(k);
    }
    await self.clients.claim();
  })());
});

self.addEventListener('fetch', (e) => {
  const req = e.request;
  if (req.method !== 'GET') return;
  const url = new URL(req.url);
  if (url.origin !== self.location.origin) return;

  const scopePath = new URL(self.registration.scope).pathname;     // mis. "/" atau "/berkas/"
  const rel = url.pathname.startsWith(scopePath) ? url.pathname.slice(scopePath.length) : null;
  if (rel === null) return;

  // Navigasi halaman: selalu ke jaringan. Gangguan sesaat (hosting memutus/menahan permintaan) dicoba ulang
  // sekali; halaman "tidak ada koneksi" hanya untuk perangkat yang benar-benar offline.
  if (req.mode === 'navigate') {
    e.respondWith((async () => {
      try { return await fetch(req); } catch (err1) {
        await new Promise((r) => setTimeout(r, 700));
        try { return await fetch(req); } catch (err2) {
          const offline = self.navigator && self.navigator.onLine === false;
          return new Response(offline ? OFFLINE_HTML : SERVER_HTML, {
            status: 503, headers: { 'Content-Type': 'text/html; charset=utf-8', 'Cache-Control': 'no-store' },
          });
        }
      }
    })());
    return;
  }

  // Aset statis publik saja: stale-while-revalidate.
  if (rel.startsWith('assets/')) {
    e.respondWith((async () => {
      const cache = await caches.open(STATIC_CACHE);
      const hit = await cache.match(req);
      const net = fetch(req).then((res) => {
        if (res.ok && res.type === 'basic') cache.put(req, res.clone());
        return res;
      }).catch(() => null);
      return hit || (await net) || Response.error();
    })());
  }
  // Selain itu (api/, files/, s/, admin/, ...) tidak disentuh service worker.
});
