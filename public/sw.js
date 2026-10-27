// Service worker minimo: rende l'app installabile sul telefono.
// Non mette in cache le pagine: i dati del banco devono essere sempre quelli
// del server, e senza rete un rientro non va registrato.
self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (event) => event.waitUntil(self.clients.claim()));
self.addEventListener('fetch', () => {});
