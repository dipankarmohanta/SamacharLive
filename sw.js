/*
 * Samachar Live - Service Worker (scope: /)
 * Strategy:
 *  - Precache core shell on install (home page + static assets).
 *  - Navigation (HTML) requests: network-first, fall back to the last
 *    cached copy, then to the precached home page. This gives basic
 *    offline reading of pages that have been visited.
 *  - Static assets: cache-first, then network (network copy stored).
 * Bump CACHE_VERSION when the shell/assets change.
 */
var CACHE_VERSION = 'np-v1';
var CACHE = 'samachar-' + CACHE_VERSION;

var PRECACHE_URLS = [
  '/',
  '/manifest.webmanifest',
  '/assets/img/favicon.svg',
  '/assets/img/icon-192.png',
  '/assets/img/icon-512.png',
  '/assets/css/style.css?v=2',
  '/assets/js/main.js?v=2'
];

self.addEventListener('install', function (event) {
  event.waitUntil(
    caches.open(CACHE).then(function (cache) {
      return cache.addAll(PRECACHE_URLS);
    }).then(function () {
      return self.skipWaiting();
    })
  );
});

self.addEventListener('activate', function (event) {
  event.waitUntil(
    caches.keys().then(function (keys) {
      return Promise.all(
        keys.filter(function (key) {
          return key !== CACHE;
        }).map(function (key) {
          return caches.delete(key);
        })
      );
    }).then(function () {
      return self.clients.claim();
    })
  );
});

self.addEventListener('fetch', function (event) {
  var request = event.request;
  if (request.method !== 'GET') { return; }

  var url = new URL(request.url);
  if (url.origin !== self.location.origin) { return; }

  /* HTML page navigation: try network, then cached copy, then precached home. */
  if (request.mode === 'navigate') {
    event.respondWith(
      fetch(request).then(function (response) {
        if (response && response.ok) {
          var copy = response.clone();
          caches.open(CACHE).then(function (cache) {
            cache.put(request, copy);
          });
        }
        return response;
      }).catch(function () {
        return caches.match(request).then(function (cached) {
          return cached || caches.match('/');
        });
      })
    );
    return;
  }

  /* Static assets: cache-first, fall back to network and store the result. */
  event.respondWith(
    caches.match(request).then(function (cached) {
      if (cached) { return cached; }
      return fetch(request).then(function (response) {
        if (response && response.ok) {
          var copy = response.clone();
          caches.open(CACHE).then(function (cache) {
            cache.put(request, copy);
          });
        }
        return response;
      });
    })
  );
});
