// sw.js
self.addEventListener('install', (event) => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    console.log('Service Worker каталога успешно активирован!');
});

self.addEventListener('fetch', (event) => {
    event.respondWith(fetch(event.request));
});