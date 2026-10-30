self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (event) =>
    event.waitUntil(self.clients.claim()),
);
self.addEventListener('fetch', () => {}); // network only; the app needs a connection
