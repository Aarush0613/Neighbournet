/* NeighbourNet PWA: cache only public static shell assets. Never cache HTML pages,
 * API responses, authentication, uploads, maps, or user-specific information. */
'use strict';
const CACHE_NAME = 'neighbournet-static-v2';
const OFFLINE_URL = '/neighbournet/offline.html';
const STATIC_ASSETS = [
  '/neighbournet/offline.html',
  '/neighbournet/manifest.json',
  '/neighbournet/assets/css/style.css',
  '/neighbournet/assets/css/profile-settings.css',
  '/neighbournet/assets/css/community-actions.css',
  '/neighbournet/assets/css/map.css',
  '/neighbournet/assets/js/app.js',
  '/neighbournet/assets/js/community-actions.js',
  '/neighbournet/assets/js/map.js',
  '/neighbournet/assets/images/icon-192.png',
  '/neighbournet/assets/images/icon-512.png',
  '/neighbournet/assets/images/default-avatar.svg'
];

self.addEventListener('install', event => {
  event.waitUntil(caches.open(CACHE_NAME).then(cache =>
    Promise.all(STATIC_ASSETS.map(url => cache.add(url).catch(() => undefined)))
  ));
  self.skipWaiting();
});

self.addEventListener('activate', event => {
  event.waitUntil(caches.keys().then(keys => Promise.all(
    keys.filter(key => key.startsWith('neighbournet-') && key !== CACHE_NAME).map(key => caches.delete(key))
  )));
  self.clients.claim();
});

self.addEventListener('fetch', event => {
  const request = event.request;
  if (request.method !== 'GET') return;
  const url = new URL(request.url);
  if (url.origin !== self.location.origin) return;

  // Never store navigations or server-generated/private pages. Offline fallback only.
  if (request.mode === 'navigate') {
    event.respondWith(fetch(request).catch(async () =>
      (await caches.match(OFFLINE_URL)) || new Response('You are offline.', {
        status: 503, headers: { 'Content-Type': 'text/plain; charset=utf-8' }
      })
    ));
    return;
  }

  // Cache only known public static assets under /assets/ plus the public manifest.
  const isStaticAsset = url.pathname.startsWith('/neighbournet/assets/') &&
    /\.(?:css|js|png|jpe?g|webp|svg|woff2?)$/i.test(url.pathname);
  const isManifest = url.pathname === '/neighbournet/manifest.json';
  if (!isStaticAsset && !isManifest) return;

  event.respondWith(caches.match(request).then(cached => {
    const network = fetch(request).then(response => {
      if (response && response.ok && response.type === 'basic') {
        const copy = response.clone();
        caches.open(CACHE_NAME).then(cache => cache.put(request, copy));
      }
      return response;
    });
    return cached || network;
  }));
});
