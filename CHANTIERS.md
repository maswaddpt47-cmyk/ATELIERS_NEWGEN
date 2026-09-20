# CHANTIERS — ATELIERS_NEWGEN

Dernière mise à jour : 20/09/2026, référence commit `0f960a4` (main).

## Décision à trancher (priorité 1)

**Divergence architecturale non tranchée entre NEWGEN et ateliers-cd47_NextStep**,
tous deux confrontés à la même panne de livraison Apps Script (voir CLAUDE.md
§ 5) :
- NEWGEN **double (hedge)** une lecture lente : relance un 2e appel en
  parallèle après 7 s, garde le premier qui répond.
- NextStep **sérialise** tous les appels dans une file à un seul en vol, sans
  hedge, pariant que c'est la concurrence qui fait rater la redirection
  `/exec`.

Aucune des deux stratégies n'a été confrontée aux données de l'autre
(Journal des opérations des deux côtés, sur une même fenêtre temporelle).
Les deux sont marquées "hypothèse non vérifiée" dans leur propre code. À
trancher si le sujet latence revient — comparer les journaux avant de
choisir, pas en théorie (règle de collaboration n°13).

## Chantiers ouverts (par priorité)

1. **Confirmer sur device réel que "Installer l'application" apparaît** sur
   Android pour `index.html` et `admin.html` (manifest + icônes + service
   worker déployés le 19/09/2026, jamais vérifié en dehors des tests
   automatisés — voir `MD-LIB/pwa-service-worker.md`).
2. **Vérifier la lisibilité des couleurs de la Frise du parc** (barres
   colorées par conum depuis le 19/09/2026, `FriseMateriel` dans
   `shared.js`) pour chaque conseiller existant en conditions réelles — pas
   de vérification visuelle faite, seulement les tests automatisés (qui ne
   testent pas le contraste texte/fond).

## Points à ne pas défaire

- `periodePretMateriel` retombe sur la veille/lendemain **ouvrés** (jamais
  un jour de week-end) quand les dates de prélèvement/retour ne sont pas
  saisies — alignée sur NextStep le 19/09/2026. Ne pas revenir au repli
  "jour même de l'atelier".
- Plafonds d'appel GAS et politique `sw.js` (pas de cache, jamais de
  `respondWith`) : voir CLAUDE.md §§ 4-5, déjà verrouillés par
  `reseau.test.js` — ne pas dupliquer ici.

---
*État transitoire, pas de la documentation permanente — supprimer ce
fichier une fois les chantiers soldés (`MD-LIB/collaboration.md`, règle
8ter).*
