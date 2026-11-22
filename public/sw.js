self.addEventListener('install', (event) => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(self.clients.claim());
});

self.addEventListener('fetch', (event) => {
    // Pass through network requests safely so Filament/Livewire/CSRF never break
    if (event.request.method !== 'GET') {
        return;
    }
});

self.addEventListener('push', (event) => {
    let data = {
        title: 'TOURISM-SALES',
        body: '',
        url: '/agent',
        icon: '/images/pwa-icon-192.png',
        badge: '/images/favicon.png',
        tag: 'tourism-sales-notification',
    };

    if (event.data) {
        try {
            data = Object.assign(data, event.data.json());
        } catch (e) {
            data.body = event.data.text();
        }
    }

    const options = {
        body: data.body,
        icon: data.icon || '/images/pwa-icon-192.png',
        badge: data.badge || '/images/favicon.png',
        tag: data.tag || 'tourism-sales-notification',
        renotify: true,
        dir: data.dir || 'rtl',
        lang: data.lang || 'he',
        data: {
            url: data.url || '/agent',
        },
    };

    event.waitUntil(self.registration.showNotification(data.title, options));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const targetUrl = (event.notification.data && event.notification.data.url) || '/agent';

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
            for (const client of clientList) {
                if ('focus' in client && client.url.includes(self.location.origin)) {
                    if ('navigate' in client) {
                        client.navigate(targetUrl);
                    }
                    return client.focus();
                }
            }
            if (self.clients.openWindow) {
                return self.clients.openWindow(targetUrl);
            }
        })
    );
});
