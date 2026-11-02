self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (event) =>
    event.waitUntil(self.clients.claim()),
);
self.addEventListener('fetch', () => {}); // network only; nothing is cached on purpose

self.addEventListener('push', (event) => {
    let data = {};

    try {
        data = event.data ? event.data.json() : {};
    } catch {
        data = { body: event.data ? event.data.text() : '' };
    }

    // some package versions nest the notification options
    const n = { ...data, ...(data.options || {}) };

    event.waitUntil(
        self.registration.showNotification(n.title || 'New activity', {
            body: n.body || '',
            icon: n.icon || '/icons/icon-192.png',
            tag: n.tag,
            data: n.data || {},
        }),
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const path =
        (event.notification.data && event.notification.data.url) ||
        '/dashboard';
    const target = new URL(path, self.location.origin).href;

    event.waitUntil(
        (async () => {
            const windows = await clients.matchAll({
                type: 'window',
                includeUncontrolled: true,
            });

            for (const client of windows) {
                if (
                    new URL(client.url).origin === self.location.origin &&
                    'focus' in client
                ) {
                    await client.focus();

                    try {
                        await client.navigate(target); // may be refused on iPhone; the app is open anyway
                    } catch {}

                    return;
                }
            }

            await clients.openWindow(target);
        })(),
    );
});
