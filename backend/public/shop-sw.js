self.addEventListener("install", () => self.skipWaiting());

self.addEventListener("activate", (event) => {
    event.waitUntil(self.clients.claim());
});

// Keep installation support without caching or changing network behavior.
self.addEventListener("fetch", () => {});
