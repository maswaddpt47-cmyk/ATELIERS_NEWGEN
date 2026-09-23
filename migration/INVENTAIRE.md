# Inventaire GAS → PHP + MySQL

Spécification de travail pour la refonte (AG-009, `CHANTIERS.md` section
« 🧭 Refonte d'architecture »). Relevé le 23/09/2026 sur
`gas/GAS_NEWGEN.js` et `ateliers-cd47_NextStep/gas/GAS_NEXTSTEP.js`.
**Transitoire** : à supprimer après la bascule.

Cible : le **client NEWGEN** parle à l'API ; les **données** viennent du
classeur **NextStep** (production). L'API reproduit donc le contrat du GAS
NEWGEN, et l'import convertit le classeur NextStep.

## 1. Données — 4 feuilles → 5 tables

| Feuille | Contenu | Table cible |
|---|---|---|
| `Ateliers_next_step` (même nom dans les deux projets) | 1 ligne par atelier, colonnes libres lues par en-tête | `ateliers` + `ateliers_materiel` |
| `Config` | paires clé/valeur, plusieurs valeurs en JSON | `config (cle, valeur)` |
| `Comptes` | `Conseiller`, `Hash`, `Role`, `Actif`, `FailCount`, `LockUntil` | `comptes` |
| `Logs_Connexion` | 9 colonnes, **deux formats historiques mêlés** (`GAS_NEXTSTEP.js:944-990`) | `journal` |
| — (PropertiesService / CacheService) | tokens (6 h), anti-force brute (5 essais → 15 min) | `sessions`, `tentatives` |

**Colonnes d'un atelier** (contrat client, `contract.test.js:12-34`) :
`_id`, `_n`, `statut`, `date` (`yyyy-MM-dd`), `horaire` (`HH:mm`), `ampm`,
`thematique`, `commune`, `lieu`, `conseiller`, `co_animateur`, `orienteur`,
`public`, `residence`, `remarques`, `inscrits`, `presents`,
`nb_ordinateurs`, `date_prelevement_materiel`, `date_retour_materiel`,
`materiel` (tableau en sortie).
⚠️ **Liste réelle des en-têtes du classeur NextStep inconnue** — le GAS écrit
toute colonne dont le nom correspond à une clé (`GAS_NEXTSTEP.js:688-692`).
À relever sur le fichier exporté avant d'écrire le schéma définitif.

**Matériel — les deux projets divergent** :
- NextStep : 9 colonnes fixes `OUI`/vide (`GAS_NEXTSTEP.js:482`, `:668`).
- NEWGEN : **toute colonne hors liste fixe** est un matériel
  (`GAS_NEWGEN.js:683-685`), comparée via `_normMat`.
→ table `ateliers_materiel (atelier_id, materiel)` : plus de colonne à
ajouter quand un matériel apparaît.

**Clé de config divergente** : NextStep `materiels_masques` → getAll
`materiels_masques` ; NEWGEN `materiels_caches` → getAll `materielsCaches`.
L'import renomme vers la forme NEWGEN.

## 2. Actions — 22, toutes par `doGet`

| Groupe | Actions | Protection actuelle |
|---|---|---|
| Lecture | `getAll` (`year` ou `years`), `getConfig`, `getVisibility`, `getComptes`, `verifierIds` | **aucune** |
| Connexion | `checkPassword`, `logLogin`, `logAccesIndex`, `selfSetPassword` | — |
| Écriture ateliers | `saveEntry`, `saveMany`, `delete` | token + verrou |
| Admin | `saveLists`, `saveConfig`, `setConfig`, `saveVisibility`, `saveColors`, `saveEmails`, `saveCompte`, `resetPassword`, `setPassword`, `getLogs` | token + rôle `admin`/`superviseur` |

Particularités à reproduire : mode `maintenance` (getAll refusé hors
`source=admin`), `saveLists` crée les comptes manquants (`_ensureCompte`),
`_n` = numéro de ligne à la création, `_id` fourni par le client (rejouer
une écriture ne crée pas de doublon — **à garder**, clé primaire).

**Disparaissent sans équivalent** : cache `getAll` par tranches, verrou
`LockService` (→ transaction SQL), `keepAlive`, `onEdit`/`onChange` et
`installerTriggerChangement` (plus de classeur modifié à la main),
`invaliderCacheGetAll`, migrations de colonnes (`ajouterColonnes*`).

## 3. Tâches hors requête

| GAS | Remplacement |
|---|---|
| `envoyerAlertesRetard` : chaque jour à 8 h, ateliers `Planifié` passés, 1 mail par conseiller (config `emails`, `rappels_actifs`) — déclencheur installé côté NEWGEN seulement (`GAS_NEWGEN.js:1072`) | tâche planifiée Alwaysdata + `mail()`/SMTP. ⚠️ Le mail partait du compte Google : expéditeur à choisir, délivrabilité à tester |
| `backupGAS` (NEWGEN, copie Drive) | sauvegardes de l'hébergeur + export SQL planifié |

## 4. Sécurité — ce que la migration doit corriger, pas copier

1. **Mots de passe par défaut prévisibles** : `cd47` + prénom
   (`GAS_NEXTSTEP.js:1050-1057`), **stockés en clair** tant que le compte ne
   s'est pas connecté (le hash n'est écrit qu'au premier succès, `:914`), et
   `resetPassword` renvoie le mot de passe en clair.
2. **SHA-256 sans sel** (`:1058`). Migration sans connaître les mots de
   passe : stocker `password_hash(sha256_hex)` et vérifier
   `password_verify(hash('sha256', $mdp), …)`. Comptes encore en clair :
   hachés à l'import et marqués « à changer ».
3. **Lectures sans token** : `getAll`, `getConfig` (clé `emails`),
   `getComptes`. Dans l'API : token exigé partout sauf `checkPassword` et la
   liste des noms nécessaire à l'écran de connexion (**décision à prendre** :
   liste réduite aux noms actifs, ou saisie libre du nom).
4. **Mot de passe dans l'URL** (GET) → finirait dans les journaux d'accès de
   l'hébergeur. L'API passe en **POST** ; changement côté `shared.js`.
5. CORS limité à `https://maswaddpt47-cmyk.github.io`.
6. `SS_ID` du classeur en clair dans les dépôts publics
   (`GAS_NEXTSTEP.js:119`) : sans effet tant que le classeur est privé ;
   devient caduc après la bascule.

## 5. Garde-fous de test

⚠️ **Correction d'une affirmation de la proposition** : `contract.test.js`
ne vérifie que la forme de l'objet **construit par le client**, pas les
réponses du serveur. Il ne garde pas l'API.
→ À écrire : un test de contrat **serveur** qui appelle chaque action de
l'API PHP locale et vérifie la forme des réponses attendues par NEWGEN
(`ok`, `entries`, `lists`, `visibility`, `conseiller_colors`, `emails`,
`stockOrdinateurs`, `materielsCaches`, `token`, `role`…).

## 6. Ordre de travail proposé

1. Obtenir un **export du classeur NextStep** (xlsx, 4 feuilles) — donne les
   vrais en-têtes. ⚠️ RGPD : données d'agents et d'ateliers, à ne pas
   commiter ; travailler sur une copie locale, ou un export anonymisé.
2. Schéma SQL + script d'import.
3. API PHP, puis test de contrat serveur.
4. `shared.js` NEWGEN : URL + POST, derrière un interrupteur.
