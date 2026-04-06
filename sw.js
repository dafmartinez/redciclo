/**
 * Redciclo — Service Worker
 *
 * Estrategias de caché:
 *  • App shell (HTML + assets locales) → Cache First, actualiza en background
 *  • CDN externo (Tailwind)            → Stale-While-Revalidate
 *  • API (/api/)                       → Network Only (nunca cachear respuestas de datos)
 *  • Navegación offline                → Sirve index.html desde caché
 */

'use strict';

const CACHE_VERSION = 'v7';
const SHELL_CACHE   = `redciclo-shell-${CACHE_VERSION}`;
const CDN_CACHE     = `redciclo-cdn-${CACHE_VERSION}`;

// Recursos críticos que se pre-cachean durante la instalación.
// Si alguno falla (404), toda la instalación falla — solo incluir lo que existe.
const PRECACHE_ASSETS = [
  './index.html',
  './manifest.json',
  './icon/Logo-Redciclo.jpg',
  './icon/favicon.svg',
  './icon/favicon-32.png',
  './icon/favicon-16.png',
  './icon/apple-touch-icon.png',
  './icon/favicon.ico',
];

// Recursos que intentamos cachear en la instalación pero no son críticos.
// Se usan Promise.allSettled para no bloquear si alguno falta.
const OPTIONAL_ASSETS = [
  './icon/icon-192.png',
  './icon/icon-512.png',
  './icon/ui-user.svg',
  './icon/ui-lock.svg',
  './icon/ui-alert.svg',
  './icon/ui-download.svg',
  './icon/ui-moon.svg',
  './icon/ui-sun.svg',
  './icon/ui-logout.svg',
  './icon/ui-plus.svg',
  './icon/ui-reload.svg',
  './icon/ui-close.svg',
  './icon/ui-chat.svg',
  './icon/ui-location.svg',
  './icon/ui-camera.svg',
];

// Orígenes de CDN que manejamos con Stale-While-Revalidate
const CDN_HOSTS = [
  'cdn.tailwindcss.com',
  'fonts.googleapis.com',
  'fonts.gstatic.com',
];

// ─── INSTALL ─────────────────────────────────────────────────────────────────
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(SHELL_CACHE).then(async cache => {
      // Precache crítico: falla si algún asset no existe
      await cache.addAll(PRECACHE_ASSETS);

      // Precache opcional: no bloquea la instalación si faltan
      await Promise.allSettled(
        OPTIONAL_ASSETS.map(url =>
          cache.add(url).catch(err =>
            console.warn(`[SW] Asset opcional no encontrado: ${url}`, err)
          )
        )
      );
    }).then(() => {
      // Activa el SW inmediatamente sin esperar a que cierren las pestañas existentes
      return self.skipWaiting();
    })
  );
});

// ─── ACTIVATE ────────────────────────────────────────────────────────────────
self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys()
      .then(keys => Promise.all(
        keys
          .filter(k => k !== SHELL_CACHE && k !== CDN_CACHE)
          .map(k => {
            console.log(`[SW] Eliminando caché antigua: ${k}`);
            return caches.delete(k);
          })
      ))
      // Toma control de todas las pestañas abiertas sin necesidad de recarga
      .then(() => self.clients.claim())
  );
});

// ─── FETCH ───────────────────────────────────────────────────────────────────
self.addEventListener('fetch', event => {
  const { request } = event;

  // Solo manejar GET (POST, FormData de fotos, etc. siempre van a la red)
  if (request.method !== 'GET') return;

  const url = new URL(request.url);

  // 1. Peticiones a la API → siempre red, nunca caché
  if (url.pathname.includes('/api/')) return;

  // 2. CDN externo → Stale-While-Revalidate
  if (CDN_HOSTS.some(host => url.hostname.includes(host))) {
    event.respondWith(staleWhileRevalidate(request, CDN_CACHE));
    return;
  }

  // 3. Assets del mismo origen → Cache First con fallback a red
  if (url.origin === self.location.origin) {
    // Peticiones de navegación: servir siempre index.html (SPA)
    if (request.mode === 'navigate') {
      event.respondWith(serveShell());
      return;
    }
    event.respondWith(cacheFirst(request, SHELL_CACHE));
  }
});

// ─── ESTRATEGIAS DE CACHÉ ────────────────────────────────────────────────────

/**
 * Cache First: sirve desde caché si existe.
 * Si no está en caché, va a la red y guarda la respuesta para la próxima vez.
 */
async function cacheFirst(request, cacheName) {
  const cached = await caches.match(request);
  if (cached) return cached;

  try {
    const response = await fetch(request);
    if (response.ok) {
      const cache = await caches.open(cacheName);
      cache.put(request, response.clone()); // guardar en background
    }
    return response;
  } catch {
    return offlineFallback();
  }
}

/**
 * Stale-While-Revalidate: sirve desde caché instantáneamente
 * y actualiza la caché con la respuesta de red en background.
 */
async function staleWhileRevalidate(request, cacheName) {
  const cache  = await caches.open(cacheName);
  const cached = await cache.match(request);

  // Lanzar fetch en background independientemente de si tenemos caché
  const fetchPromise = fetch(request)
    .then(response => {
      if (response.ok) cache.put(request, response.clone());
      return response;
    })
    .catch(() => null);

  // Si tenemos versión en caché, devolverla de inmediato
  return cached ?? await fetchPromise ?? offlineFallback();
}

/**
 * Sirve el shell (index.html) para cualquier petición de navegación.
 * Necesario para que la SPA funcione offline.
 */
async function serveShell() {
  const cached = await caches.match('./index.html');
  if (cached) return cached;
  try {
    return await fetch('./index.html');
  } catch {
    return offlineFallback();
  }
}

/**
 * Respuesta mínima de emergencia cuando no hay caché ni red.
 * Solo se llega aquí con assets no críticos (imágenes, etc.).
 */
function offlineFallback() {
  return new Response(
    `<!DOCTYPE html>
    <html lang="es">
    <head>
      <meta charset="UTF-8"/>
      <meta name="viewport" content="width=device-width,initial-scale=1"/>
      <title>Sin conexión — Redciclo</title>
      <style>
        body { font-family: system-ui, sans-serif; display: flex; align-items: center;
               justify-content: center; min-height: 100dvh; margin: 0;
               background: #f0fdf4; color: #14532d; text-align: center; padding: 1rem; }
        .card { background: white; border-radius: 1rem; padding: 2rem;
                box-shadow: 0 4px 24px rgba(0,0,0,.08); max-width: 320px; width: 100%; }
        h1 { font-size: 2.5rem; margin: 0 0 .5rem; }
        p  { color: #4b5563; margin: 0 0 1.5rem; }
        button { background: #16a34a; color: white; border: none; padding: .75rem 2rem;
                 border-radius: .5rem; font-size: 1rem; cursor: pointer; width: 100%; }
        button:active { background: #15803d; }
      </style>
    </head>
    <body>
      <div class="card">
        <h1>♻️</h1>
        <h2>Sin conexión</h2>
        <p>No hay señal de internet. Cuando vuelvas a conectarte, la app se actualizará automáticamente.</p>
        <button onclick="location.reload()">Reintentar</button>
      </div>
    </body>
    </html>`,
    {
      status:  503,
      headers: { 'Content-Type': 'text/html; charset=utf-8' },
    }
  );
}
