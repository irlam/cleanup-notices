'use strict';
const CACHE = 'site-documents-public-v1';
// Only public artwork and the generic offline screen are stored. Never cache
// authenticated pages, PDFs, uploads, API responses or form submissions.
const PUBLIC_FILES = [
  '/offline.html', '/assets/brand/site-documents-logo.png',
  '/assets/icons/icon-32.png', '/assets/icons/icon-96.png',
  '/assets/icons/icon-180.png', '/assets/icons/icon-192.png',
  '/assets/icons/icon-512.png', '/assets/icons/maskable-192.png',
  '/assets/icons/maskable-512.png'
];
self.addEventListener('install', event => {
  event.waitUntil(caches.open(CACHE).then(cache => cache.addAll(PUBLIC_FILES)));
});
self.addEventListener('activate', event => {
  event.waitUntil(caches.keys().then(keys => Promise.all(keys
    .filter(key => key.startsWith('site-documents-public-') && key !== CACHE)
    .map(key => caches.delete(key)))).then(() => self.clients.claim()));
});
self.addEventListener('fetch', event => {
  const request = event.request;
  if (request.method !== 'GET') return;
  const url = new URL(request.url);
  if (url.origin !== self.location.origin) return;
  if (request.mode === 'navigate') {
    event.respondWith(fetch(request).catch(() => caches.match('/offline.html')));
    return;
  }
  if (PUBLIC_FILES.includes(url.pathname) && !url.search) {
    event.respondWith(caches.match(request).then(cached => cached || fetch(request)));
  }
});
