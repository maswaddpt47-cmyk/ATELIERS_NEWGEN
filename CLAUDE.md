# Règles de travail — ATELIERS_NEWGEN

> **Travaux en cours, décisions en attente et mesures à faire :
> [`CHANTIERS.md`](CHANTIERS.md).** À lire en début de session — une session
> ne transmet rien à la suivante, seul ce qui est commité survit. À
> réactualiser **à chaque avancée**, pas en fin de session : une session peut
> s'interrompre sans préavis. Une tâche finie s'en **retire** (son récit va
> dans `git log`) ; `scripts/check-chantiers.sh`, lancé au démarrage de
> session, signale quand le ménage est dû (> 300 lignes, en-tête de plus de
> 7 jours, tâches ✅ ou barrées).
>
> **Contradiction d'une proposition par une autre session :
> [`AGORA.md`](AGORA.md)** — quand la déclencher, section 8.
>
> Avant d'ajouter une règle ici, lire `MD-LIB/hygiene-instructions.md` : une
> contrainte formulable en test doit devenir un test, pas un paragraphe de
> plus. Ce fichier est passé de 298 à 229 lignes le 19/09/2026, était remonté à
> 297 le 26/09/2026, ramené à ~264, puis à 258 le 04/11/2026 (règles communes chargées depuis MD-LIB) ; le laisser regrossir, c'est le rendre
> moins appliqué, pas mieux.

## 1. Workflow git

1. **Mettre à jour avant de lire ou modifier le moindre fichier** : `git fetch origin main`
   puis `git merge --ff-only origin/main`, **jamais `git pull`** sur `main` (MD-LIB règle 63 :
   le 04/11/2026, un `git pull` a fusionné `sandbox` dans `main` et mis en production du code
   non essayé).
2. Appliquer les modifications, tests selon la section 2.
3. **Un commit par modification logique**, jamais de commit fourre-tout.
   Message : `type: description courte`, avec `feat`, `fix`, `refactor`,
   `style`, `docs` ou `chore`.
4. Pousser sur `main` : `git push origin main`.
   **Livraison directe sur `main`, jamais par `sandbox` sauf demande explicite de l'utilisateur** (depuis le 25/10/2026, rappelé le 04/11/2026 : un détour par le bac à sable non demandé a coûté un déploiement et un tour).
5. **Avant d'écrire « en ligne »** : `MD-LIB/outils/attendre-deploiement.sh` (hook
   automatique après un push, sinon à lancer à la main) doit avoir dit ✅.

**Branche imposée par la plateforme.** Claude Code sur le web impose parfois
une branche dédiée (`claude/…`). Dans ce cas, merger dans `main` et pousser à
la fin de chaque session — le déploiement GitHub Pages ne se déclenche que
sur `main`, une branche de feature seule reste invisible en production :

```bash
git checkout main && git merge <branche> --no-ff && git push origin main
```

## 2. Tests

| Runner | Ce qu'il vérifie |
|---|---|
| `node --test utils.test.js` | `utils.js` — dates, ICS, communes |
| `node --test logic.test.js` | `logic.js` — KPI, validation, filtres, matériel |
| `node --test contract.test.js` | format des données envoyées à l'API |
| `node --test veilleur.test.js` | cadence du veilleur d'intégrité (`veilleur.yml`, AG-039) : faux `gh`, aucune attente |
| `node --test integrite.test.js` | requête-témoin du contrôle d'intégrité (`integrite-api.yml`, AG-039) : faux `curl`, faux journaux |
| `npx playwright test` | tests navigateur, en parallèle (`e2e/`, ~45 s) : |
| ↳ `e2e/sandbox.spec.js` | `utils.js`+`logic.js` chargent dans un navigateur |
| ↳ `e2e/smoke.spec.js` | les deux pages s'ouvrent, chaque onglet répond, sans erreur JS |
| ↳ `e2e/reseau.spec.js` | politique d'appel : plafonds, reprises, doublage |
| ↳ `e2e/appels.spec.js` | nombre d'appels émis à l'ouverture et après écriture |
| ↳ `e2e/avis-admin.spec.js` | Admin : avis un par un, suppression d'un avis |

Les tests navigateur exigent `npm ci` et un Chromium (préinstallé en
local, sinon `npx playwright install chromium`).

**Quand lancer quoi**

- `node --check` sur tout fichier touché : **systématique**. C'est lui qui
  attrape la casse au chargement (point-virgule manquant, IIFE cassée) qui
  laisse les deux pages blanches alors que les suites Node passent.
- `utils.js`, `logic.js` ou le format entry modifiés → les trois suites Node.
- `veilleur.yml` modifié → **`veilleur.test.js`** ; `integrite-api.yml` modifié → **`integrite.test.js`** (ils extraient et exécutent les scripts réels).
- `gasAppel`, `gasUnAppel`, `gasLectureDoublee` ou une constante `GAS_*`
  modifiée → **`e2e/reseau.spec.js`**. Il verrouille notamment qu'une
  écriture n'est jamais doublée.
- Effets de démarrage d'`app.js`/`admin_app.js`, chemin d'écriture ou
  `addLog` modifiés → **`e2e/appels.spec.js`**.
- Changement mineur (texte, style, élément UI sans logique) → pas besoin de
  relancer `sandbox`/`smoke` localement, la CI s'en charge à chaque push.

**Règles de décision**

- Test qui échoue après une correction de bug → corriger le code, pas le test.
- Test qui échoue après un changement intentionnel → mettre à jour test et
  code dans le même commit.
- Ne jamais supprimer ni désactiver un test pour faire passer un commit.
- **La CI bloque le déploiement si un test échoue.** Un push cassé ne déploie
  jamais.

## 3. Cache-busting obligatoire

`index.html` et `admin.html` chargent `app.css`/`admin.css`, `utils.js`,
`logic.js` (depuis le 26/09/2026), `shared.js`, `app.js`/`admin_app.js`/`admin_config.js`/`xlsxstyle.js` avec un
paramètre `?v=N`. À chaque commit qui modifie le **contenu** d'un de ces
fichiers, incrémenter son `?v=` dans **chaque** page HTML qui le charge —
`shared.js` est partagé par les deux pages et doit être bumpé dans les deux,
même si une seule a changé par ailleurs. Sans ce bump, le correctif n'atteint
jamais les navigateurs qui ont déjà l'ancienne version en cache (incident
confirmé sur ateliers-cd47_NextStep le 16/09/2026, porté ici en garde-fou
préventif — `scripts/check-cache-busting.js`, vérifié en CI). Vérifier ce
point avant de conclure qu'un correctif ne marche pas.

## 4. Plus de PWA — `sw.js` de désinstallation

Les pages ne sont **plus installables** depuis le 24/09/2026 (AG-012,
décision de l'utilisateur : l'usage nomade passe par le navigateur ; le mode
installé, sans barre d'adresse, empêchait tout rechargement forcé de
`index.html`/`admin.html`). Ni manifeste, ni `apple-touch-icon`, ni
enregistrement de service worker dans les pages.

- **`sw.js` reste publié, sans date de fin** : il se désinscrit sur les
  appareils où l'ancien est installé. Ne pas le supprimer.
- **Jamais de désinscription depuis la page** : l'origine est partagée avec
  NextStep et GDINV2 (commentaire en tête de `sw.js`).
- `icons/` reste : favicon des deux pages. `manifest-*.json` retirés le
  30/09/2026 : plus cités par aucune page, une installation restante n'en
  dépend pas.
- Si une PWA revenait un jour : `MD-LIB/pwa-service-worker.md` (jamais de
  cache, jamais de `respondWith()`).

## 5. Serveur — API PHP chez Alwaysdata (depuis le 25/09/2026)

- Code dans `api/`, tests dans `api-tests/`, déployé par `deploy-api.yml`
  **seulement si ses tests passent** (base MySQL jetable). NextStep et NEWGEN
  partagent cette API et cette base : un changement ici touche les deux sites.
- Tests en local : `ATELIERS_TEST_MYSQL=root@127.0.0.1 ATELIERS_TEST_MYSQL_MDP=…
  php api-tests/api.test.php` : dans l'environnement « Micky », MariaDB est installé et
  démarré par le script de démarrage (04/11/2026), mot de passe `testmdp` (base locale
  jetable). Suite complète une seule fois avant le push, mutations groupées (règle 55).
- Google Apps Script est **coupé** et son code retiré du dépôt (30/09/2026) :
  version qui tournait en ligne, `git show 3da2257:gas/GAS_NEWGEN.js` ; banc
  de mesure GAS/Alwaysdata, `git log --diff-filter=D -- banc/` puis
  `git show <commit>^:banc/`. Les mesures de l'époque
  (pertes de livraison, doublage) sont dans l'historique git de
  `CHANTIERS.md`.

Règles héritées, toujours valables :

- **Ne jamais rallonger les plafonds d'appel** (12 s lecture et écriture,
  25 s `saveMany`) : attendre ne récupère aucune réponse perdue, ça allonge
  l'écran d'attente (84 s relevées le 18/09/2026). Verrouillé par
  `e2e/reseau.spec.js`.
- **Une écriture n'est jamais doublée** (`GAS_ACTIONS_ECRITURE`). La rejouer
  est sûr : l'`_id` vient du client et l'API remplace la ligne existante
  (`api/lib/ecriture.php:144`).
- **Avant d'ajouter un appel au démarrage**, vérifier que l'info ne voyage pas
  déjà dans `getAll`. `e2e/appels.spec.js` échoue si un appel supprimé
  réapparaît.
- **Après une écriture, ne jamais recharger pour relire** : les écritures
  s'appliquent localement (`appliquerEntree`/`retirerEntree`).
- Les noms `GAS_*`, `gasAppel`, `__gasLog` sont restés dans le code : ils
  désignent la couche d'appel, plus Google.

## 6. Routine RGPD & sécurité des accès/données

Source canonique : `MD-LIB/rgpd-securite.md`. À vérifier en début de session
(dès qu'un fichier touchant à des données utilisateurs, accès, identifiants
ou config d'hébergement est lu/modifié) et avant de considérer un chantier
terminé (formulaire, export, nouvel appel API, stockage, authentification).

Checklist condensée :
- **RGPD** : minimisation des champs collectés, base légale de la collecte,
  durée de conservation/purge, droits des personnes (accès/rectification/
  suppression), sous-traitants et hébergement (hébergeur, CDN — hors UE ?), données
  sensibles, traçabilité des traitements.
- **Sécurité** : pas de secret/clé/token en clair dans le code ou poussé sur
  le repo, action sensible protégée par authentification réelle, échanges en
  HTTPS, pas de donnée sensible en localStorage/cookies sans nécessité, libs/
  CDN externes vérifiées, permissions par défaut minimales, logs sans données
  personnelles en clair.
- **Captures d'écran** (charte IA du CD47 §4, 06/10/2026) : celles envoyées à
  Claude se font dans le bac à sable (données fictives) ou recadrées. Une
  capture qui montre des données réelles (noms d'agents, remarques de
  stagiaires) se signale en une ligne : rappel d'habitude, pas alerte RGPD.
- **Références CD47** : consignes IA → `MD-LIB/charte-ia-cd47.md` ; durées
  de conservation et purges (tableau de gestion, visa des Archives
  départementales, versions V0.x/V1) → `MD-LIB/archivage-cd47.md`.

Signaler tout point non garanti explicitement dans la réponse (`⚠️ RGPD/
sécurité : ...`), même sans qu'on le demande — immédiatement si critique
(secret exposé, donnée sensible non protégée), sinon en une ligne courte.

**Audit trimestriel :** en complément, un audit de sécurité approfondi
(`/security-review` sur `main` + checklist RGPD/sécurité complète sur tout
le repo) est prévu tous les trois mois. C'est une routine planifiée
(`create_trigger`, mode session neuve à chaque déclenchement — indépendante
de toute session de travail), avec notification push/email. Voir
`MD-LIB/rgpd-securite.md` pour le détail.
**Audit Codex** (OpenAI, MD-LIB `agora.md` §12) : le même jour, et après tout
changement structurant de sécurité (connexion, page publique, hébergement),
avec `ateliers-backups/documents/sources/consigne-audit-codex.md` ; chaque
point vérifié dans le code, rapport rangé dans `ateliers-backups`.

## 7. Règles de collaboration avec Claude

Règles communes de collaboration : MD-LIB/collaboration.md, chargé automatiquement par MD-LIB/CLAUDE.md (04/11/2026). Ici, seulement ce qui est propre au projet.

- **Réseau et serveur** : sur un problème réseau qui dure plus de 3 itérations, demander une capture Network DevTools ou le **journal Admin** ; dès qu'un bug réseau ou serveur est signalé, vérifier d'abord que le dernier `deploy-api.yml` a réussi et à quelle heure.
- **Coût des tests** : `npx playwright test` ~45 s en tout, un seul fichier quand c'est possible (`npx playwright test e2e/reseau.spec.js`) ; `node --check` et les suites Node sont quasi gratuits (section 2 : laquelle se déclenche sur quoi).
- **Tailles de référence du rendu** : 390 px (téléphone) et 1280x720.
- **Toute modification des interfaces se fait sur NEWGEN *et* NextStep** (demande de l'utilisateur, 26/09/2026) : les deux applis partagent la même API et la même base depuis la bascule du 25/09/2026. Un changement fait sur une seule est l'exception, annoncée comme telle au moment du choix. En fin de livraison, dire en une ligne ce qui est en ligne sur chacune (commit, `?v=`) pour que l'utilisateur sache quoi recharger. **Nouveautés — choix de l'utilisateur (25/10/2026).** Après test et validation d'une évolution, proposer les changements significatifs pour l'équipe avec un texte court, puis demander lesquels l'utilisateur souhaite annoncer. **Attendre son accord explicite sur les sujets et le texte avant publication.** Validation du fonctionnement (« tout est OK ») et choix des annonces sont deux étapes distinctes. Une annonce retenue est ajoutée à `NOUVEAUTES` (`shared.js`) dans les deux applications avec l'`id` suivant ; cette rubrique remplace les mails d'annonce. **Jamais d'annonce réservée à l'Admin** (`admin: true`) : l'utilisateur est le seul Admin, elle n'apprendrait rien à personne (décision du 26/10/2026). **Avant d'en écrire une, relire `NOUVEAUTES`** : un sujet déjà annoncé se met à jour dans son entrée, pas dans une nouvelle (02/10/2026 : dépêche 16 publiée puis retirée, la Corbeille était annoncée depuis le 25/09 par la 9) ; un `id` publié puis retiré n'est jamais réutilisé (la pastille ne compte que les `id` au-delà du dernier vu). Les annonces déjà publiées (30 à 32) restent. Pas les correctifs invisibles. Les écarts restants sont listés et surveillés par `scripts/parite.js` (workflow `parite.yml`) : un écart voulu s'inscrit dans `scripts/parite-ecarts.json`, pas dans ce fichier.

## 8. AGORA — demander la contradiction d'une autre session

Espace d'échange entre sessions Claude : [`AGORA.md`](AGORA.md). Règle
complète et justification : `MD-LIB/agora.md`.

**Ne pas déclencher au ressenti.** La confiance de Claude est mal calibrée :
elle est la plus haute là où il raisonne sur ce qu'il n'a pas vérifié. Le
déclenchement se fait sur des faits constatables dans le diff.

**Soumettre un bloc dès qu'un de ces sept critères est rempli :**

1. la décision **ferme une porte** — schéma de données, format `localStorage`,
   nouvelle dépendance, contrat entre `shared.js` et les applis ;
2. **deux options envisagées, une seule écrite**, sans arbitrage extérieur ;
3. **trois itérations sans résolution** sur le même problème (cf. règle 10) ;
4. proposition de **défaire un existant dont la raison n'est pas retrouvée**
   (le cas `keepAlive` supprimé puis remis) ;
5. la proposition **contredit une note datée** de `CLAUDE.md`, `CHANTIERS.md`
   ou un commentaire de décision ;
6. **coût irréversible côté usager** — perte de données, migration, rupture
   d'une PWA déjà installée ;
7. **le changement touche un calcul ou un chiffre affiché** (indicateur, total,
   taux, filtre, définition ; écran, export, impression) — **systématique,
   quelle que soit sa taille** (décision du 26/10/2026, après AG-032).
   Contradicteur : Codex. Bloc ouvert avant la correction, définitions métier
   séparées des défauts démontrés et tranchées par l'utilisateur.

**N'y vont pas :** un changement de rendu pur (règle 17), un correctif
localisé appuyé sur une preuve, tout ce qui se défait en un commit — **sauf
calcul ou chiffre affiché : critère 7, toujours.**

**Comment :** écrire le bloc, le committer **sur `main` immédiatement**
(exception assumée au workflow de branche de la section 1 — c'est du texte,
ça ne peut rien casser), puis annoncer en une ligne, sur le modèle du
signalement RGPD, **sur une ligne à part en tête du message, 🔴 et en gras** (MD-LIB `agora.md` §4, 04/11/2026 : un « pull » noyé dans un compte rendu n'a pas été vu) : `🔴 **⚖️ AGORA : critère N — <sujet>. Bloc AG-00N commité. → Dis « pull » à Codex.**`
(**bloc pour Codex : dire « pull » à Codex suffit**, son `AGENTS.md` le fait
répondre, décision du 03/11/2026 ; l'annonce reste due) ou, pour une session
Claude, la phrase à coller dans l'autre session, **libellé de session compris**
(« …réponds à AG-00N, tu es la session B ») : les deux comptes poussant sous
la même identité GitHub, c'est la seule chose qui distingue les auteurs. **Soumettre d'office sans
demander l'autorisation, et ne jamais bloquer dessus** : rien ne garantit
qu'une réponse arrive. Soumettre **au moment du choix**, pas après
l'implémentation — devant du code déjà écrit, le contradicteur valide par
biais de statu quo.

**En réponse à un bloc :** ne pas refaire la proposition, chercher ce qui
manque. Verdict `confirmé` (en disant ce qui n'a pas pu être vérifié),
`amendé` (le cas le plus utile) ou `contredit`. **Sans `fichier:ligne`,
mesure ou log, la réponse ne compte pas.** **Ne jamais répondre à un bloc
qu'on a soi-même ouvert** — dans le doute, demander. L'utilisateur tranche,
pas le contradicteur.

**Une entrée « décision à trancher » dans `CHANTIERS.md` est par définition un
candidat** : au moment de l'écrire, dire pourquoi on ouvre un bloc ou non.

**Sécurité, mots de passe, données personnelles : contradicteur Codex**
(OpenAI) au lieu de la session B (06/10/2026, mode d'emploi : `AGORA.md`).
Sinon deux comptes Claude : canal = ce dépôt, relais par l'utilisateur.
