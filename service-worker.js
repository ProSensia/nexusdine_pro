const CACHE_NAME = 'nexusdine-pro-v1.0.0';
const OFFLINE_URL = '/offline.html';

const PRECACHE_URLS = [
    '/',
    '/index.php',
    '/customer/index.php',
    '/customer/menu.php',
    '/customer/cart.php',
    '/customer/games.php',
    '/assets/css/style.css',
    '/assets/css/pwa.css',
    '/assets/js/main.js',
    '/assets/js/offline-manager.js',
    '/assets/js/sync-manager.js',
    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css',
    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css',
    'https://code.jquery.com/jquery-3.6.0.min.js',
    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js'
];

// Install event - precache resources
self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(cache => {
                console.log('Opened cache');
                return cache.addAll(PRECACHE_URLS);
            })
            .then(() => self.skipWaiting())
    );
});

// Activate event - clean up old caches
self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(cacheNames => {
            return Promise.all(
                cacheNames.map(cacheName => {
                    if (cacheName !== CACHE_NAME) {
                        console.log('Deleting old cache:', cacheName);
                        return caches.delete(cacheName);
                    }
                })
            );
        }).then(() => self.clients.claim())
    );
});

// Fetch event - serve from cache, fall back to network
self.addEventListener('fetch', event => {
    // Skip cross-origin requests
    if (!event.request.url.startsWith(self.location.origin)) {
        return;
    }

    // Handle API requests
    if (event.request.url.includes('/api/')) {
        event.respondWith(
            fetch(event.request)
                .then(response => {
                    // Clone the response to cache it
                    const responseClone = response.clone();
                    caches.open(CACHE_NAME).then(cache => {
                        cache.put(event.request, responseClone);
                    });
                    return response;
                })
                .catch(() => {
                    // If network fails, try cache
                    return caches.match(event.request);
                })
        );
        return;
    }

    // Handle navigation requests
    if (event.request.mode === 'navigate') {
        event.respondWith(
            fetch(event.request)
                .catch(() => {
                    return caches.match(OFFLINE_URL);
                })
        );
        return;
    }

    // For other requests, try cache first, then network
    event.respondWith(
        caches.match(event.request)
            .then(response => {
                if (response) {
                    return response;
                }
                return fetch(event.request)
                    .then(response => {
                        // Check if we received a valid response
                        if (!response || response.status !== 200 || response.type !== 'basic') {
                            return response;
                        }

                        // Clone the response
                        const responseToCache = response.clone();

                        caches.open(CACHE_NAME)
                            .then(cache => {
                                cache.put(event.request, responseToCache);
                            });

                        return response;
                    });
            })
            .catch(() => {
                // If both cache and network fail, show offline page
                if (event.request.mode === 'navigate') {
                    return caches.match(OFFLINE_URL);
                }
                return new Response('Offline', {
                    status: 503,
                    statusText: 'Service Unavailable',
                    headers: new Headers({
                        'Content-Type': 'text/plain'
                    })
                });
            })
    );
});

// Background sync for offline data
self.addEventListener('sync', event => {
    if (event.tag === 'sync-offline-data') {
        event.waitUntil(syncOfflineData());
    }
});

async function syncOfflineData() {
    try {
        // Get offline data from IndexedDB
        const offlineData = await getOfflineData();
        
        if (offlineData && offlineData.length > 0) {
            for (const data of offlineData) {
                try {
                    const response = await fetch(data.url, {
                        method: data.method || 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify(data.body)
                    });

                    if (response.ok) {
                        // Remove from offline storage
                        await removeOfflineData(data.id);
                    }
                } catch (error) {
                    console.error('Sync error:', error);
                }
            }
        }
    } catch (error) {
        console.error('Sync failed:', error);
    }
}

// Helper functions for IndexedDB
function getOfflineData() {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open('nexusdine_offline', 1);
        
        request.onerror = () => reject(request.error);
        request.onsuccess = (event) => {
            const db = event.target.result;
            const transaction = db.transaction(['offline_queue'], 'readonly');
            const store = transaction.objectStore('offline_queue');
            const getAllRequest = store.getAll();
            
            getAllRequest.onsuccess = () => resolve(getAllRequest.result);
            getAllRequest.onerror = () => reject(getAllRequest.error);
        };
        
        request.onupgradeneeded = (event) => {
            const db = event.target.result;
            if (!db.objectStoreNames.contains('offline_queue')) {
                const store = db.createObjectStore('offline_queue', { keyPath: 'id', autoIncrement: true });
                store.createIndex('sync_status', 'sync_status', { unique: false });
            }
        };
    });
}

function removeOfflineData(id) {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open('nexusdine_offline', 1);
        
        request.onerror = () => reject(request.error);
        request.onsuccess = (event) => {
            const db = event.target.result;
            const transaction = db.transaction(['offline_queue'], 'readwrite');
            const store = transaction.objectStore('offline_queue');
            const deleteRequest = store.delete(id);
            
            deleteRequest.onsuccess = () => resolve();
            deleteRequest.onerror = () => reject(deleteRequest.error);
        };
    });
}

// Push notifications
self.addEventListener('push', event => {
    let data = {};
    
    if (event.data) {
        data = event.data.json();
    }
    
    const options = {
        body: data.body || 'New update from NexusDine Pro',
        icon: '/assets/images/icon-192.png',
        badge: '/assets/images/badge-72.png',
        vibrate: [100, 50, 100],
        data: {
            url: data.url || '/'
        },
        actions: [
            {
                action: 'view',
                title: 'View'
            },
            {
                action: 'close',
                title: 'Close'
            }
        ]
    };
    
    event.waitUntil(
        self.registration.showNotification(data.title || 'NexusDine Pro', options)
    );
});

self.addEventListener('notificationclick', event => {
    event.notification.close();
    
    if (event.action === 'close') {
        return;
    }
    
    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true })
            .then(windowClients => {
                // Check if there's already a window/tab open
                for (const client of windowClients) {
                    if (client.url === event.notification.data.url && 'focus' in client) {
                        return client.focus();
                    }
                }
                
                // If not, open a new window/tab
                if (clients.openWindow) {
                    return clients.openWindow(event.notification.data.url);
                }
            })
    );
});