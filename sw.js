// Service worker minimal — sert uniquement à rendre le site installable en PWA
// sur Android (certaines versions de Chrome exigent un service worker
// enregistré, avec un handler 'fetch' présent, pour proposer
// "Installer l'application" plutôt que le simple raccourci navigateur).
//
// Il ne met VOLONTAIREMENT rien en cache et n'intercepte VOLONTAIREMENT
// aucune requête (pas d'event.respondWith) :
// 1. Le site a un système de cache-busting explicite (`?v=N` sur
//    app.css/utils.js/shared.js/app.js/..., voir CLAUDE.md) — un SW qui
//    mettrait les fichiers en cache-first recréerait le même type
//    d'incident que celui déjà rencontré sur ateliers-cd47_NextStep
//    (correctif invisible sur des postes avec l'ancienne version en cache,
//    16/09/2026).
// 2. Un SW qui répond lui-même à une requête (respondWith(fetch(...)))
//    la ré-émet depuis le contexte du service worker, hors de portée des
//    mocks réseau des tests (page.route()/interception GAS dans
//    e2e.test.js, appels.test.js, reseau.test.js) — confirmé sur
//    ateliers-cd47_NextStep le 19/09/2026 : avec respondWith, tous les
//    tests de connexion échouaient (réponse mockée jamais reçue) ; sans,
//    tout passe. Ne pas ajouter respondWith() ici sans revalider les
//    quatre suites de tests.

self.addEventListener('install', (event) => {
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(self.clients.claim());
});

self.addEventListener('fetch', (event) => {
  // Ne rien faire : laisser le navigateur traiter la requête normalement.
});
