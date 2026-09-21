# Règles de travail — ATELIERS_NEWGEN

> **Travaux en cours, décisions en attente et mesures à faire :
> [`CHANTIERS.md`](CHANTIERS.md).** À lire en début de session — une session
> ne transmet rien à la suivante, seul ce qui est commité survit. À
> réactualiser **à chaque avancée**, pas en fin de session : une session peut
> s'interrompre sans préavis.
>
> **Contradiction d'une proposition par une autre session :
> [`AGORA.md`](AGORA.md)** — quand la déclencher, section 8.
>
> Avant d'ajouter une règle ici, lire `MD-LIB/hygiene-instructions.md` : une
> contrainte formulable en test doit devenir un test, pas un paragraphe de
> plus. Ce fichier est passé de 298 à 229 lignes le 19/09/2026 ; le laisser
> regrossir, c'est le rendre moins appliqué, pas mieux.

## 1. Workflow git

1. **`git pull origin main` avant de lire ou modifier le moindre fichier**,
   même si le repo semble à jour. L'oubli est une cause récurrente
   d'écrasement de travail entre deux sessions.
2. Appliquer les modifications, tests selon la section 2.
3. **Un commit par modification logique**, jamais de commit fourre-tout.
   Message : `type: description courte`, avec `feat`, `fix`, `refactor`,
   `style`, `docs` ou `chore`.
4. Pousser sur `main` : `git push origin main`.

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
| `node --test contract.test.js` | format des données envoyées à GAS |
| `node sandbox.test.js` | `utils.js`+`logic.js` chargent dans un navigateur |
| `node e2e.test.js` | les deux pages s'ouvrent, chaque onglet répond, sans erreur JS |
| `node reseau.test.js` | politique d'appel GAS : plafonds, reprises, doublage |
| `node appels.test.js` | nombre d'appels GAS émis à l'ouverture et après écriture |

Les quatre runners navigateur exigent `npm ci` et un Chromium (préinstallé en
local, sinon `npx playwright install chromium`).

**Quand lancer quoi**

- `node --check` sur tout fichier touché : **systématique**. C'est lui qui
  attrape la casse au chargement (point-virgule manquant, IIFE cassée) qui
  laisse les deux pages blanches alors que les suites Node passent.
- `utils.js`, `logic.js` ou le format entry modifiés → les trois suites Node.
- `gasAppel`, `gasUnAppel`, `gasLectureDoublee` ou une constante `GAS_*`
  modifiée → **`reseau.test.js`** (~40 s). Il verrouille notamment qu'une
  écriture n'est jamais doublée : deux `saveEntry` en vol en même temps
  peuvent tous deux conclure « ligne absente » et faire chacun leur
  `appendRow`, soit un atelier en double dans le classeur.
- Effets de démarrage d'`app.js`/`admin_app.js`, chemin d'écriture ou
  `addLog` modifiés → **`appels.test.js`**.
- Changement mineur (texte, style, élément UI sans logique) → pas besoin de
  relancer `sandbox`/`e2e` localement, la CI s'en charge à chaque push.

**Règles de décision**

- Test qui échoue après une correction de bug → corriger le code, pas le test.
- Test qui échoue après un changement intentionnel → mettre à jour test et
  code dans le même commit.
- Ne jamais supprimer ni désactiver un test pour faire passer un commit.
- **La CI bloque le déploiement si un test échoue.** Un push cassé ne déploie
  jamais.

## 3. Cache-busting obligatoire

`index.html` et `admin.html` chargent `app.css`/`admin.css`, `utils.js`,
`shared.js`, `app.js`/`admin_app.js`/`admin_config.js`/`xlsxstyle.js` avec un
paramètre `?v=N`. À chaque commit qui modifie le **contenu** d'un de ces
fichiers, incrémenter son `?v=` dans **chaque** page HTML qui le charge —
`shared.js` est partagé par les deux pages et doit être bumpé dans les deux,
même si une seule a changé par ailleurs. Sans ce bump, le correctif n'atteint
jamais les navigateurs qui ont déjà l'ancienne version en cache (incident
confirmé sur ateliers-cd47_NextStep le 16/09/2026, porté ici en garde-fou
préventif — `scripts/check-cache-busting.js`, vérifié en CI). Vérifier ce
point avant de conclure qu'un correctif ne marche pas.

## 4. PWA & service worker

Source canonique : `MD-LIB/pwa-service-worker.md`. Les deux pages sont
installables en PWA depuis le 19/09/2026 (`manifest-app.json`,
`manifest-admin.json`, `icons/`, `sw.js`). Prolonge directement la règle 3 :
un service worker est le seul code du projet qui **survit au déploiement
suivant**, puisqu'il reste installé sur l'appareil.

- **`sw.js` ne met rien en cache et n'intercepte rien, volontairement.** Le
  versioning est déjà assuré par le `?v=N` ci-dessus ; un service worker en
  cache-first recréerait l'incident du 16/09/2026 en pire, son cache ne
  partant pas avec les données de navigation.
- **Jamais de `respondWith()`.** La requête serait ré-émise depuis le contexte
  du service worker, hors de portée des mocks réseau de `e2e.test.js`,
  `appels.test.js` et `reseau.test.js`. Le symptôme ne ressemble pas à sa
  cause : on croit à une régression de l'authentification.
- **Enregistrement sur `load`**, en fin de `<body>`, avec un `catch` vide.
- ⚠️ **En mode installé, il n'y a plus de barre d'adresse, donc plus de
  rechargement forcé.** Le `?v=N` protège `shared.js`, `app.js` et les CSS,
  **pas `index.html`/`admin.html` eux-mêmes**. Sortie de secours à connaître :
  désinstaller/réinstaller l'application, ou vider les données du site.
- Après toute modification de `sw.js` : relancer les suites navigateur **sur
  les vraies pages** (une page de test nue n'enregistre pas le service
  worker, donc ne prouve rien).

## 5. Backend GAS

Le script Google Apps Script (URL dans `shared.js` → `GS_URL`) n'est pas
déployé depuis ce repo — pas d'API de push GAS, le déploiement reste manuel
via l'éditeur script.google.com. `gas/GAS_NEWGEN.js` en est une copie de
référence versionnée (diffable), à tenir à jour manuellement après chaque
déploiement confirmé — voir `gas/README.md` pour la procédure.

### Limite connue — la réponse n'est pas lente, elle est perdue

**Mesuré** les 15-16/09/2026 sur NextStep puis le 18/09/2026 sur NEWGEN
(journal Admin, PC *et* Android, 221 ateliers), captures croisées Journal
client / Exécutions Apps Script. Le comportement est **bimodal**, pas
« lent » :

| Livraison réussie | Livraison ratée |
|---|---|
| `getAll` ok en **1.1 s** | `getAll` HTTP 404 en **27.3 s** |
| `getComptes` ok en **1.8 s** | `getConfig` HTTP 404 en **29.8 s** |
| `checkPassword` ok en **2.7 s** | `getAll` bloqué, abandonné après **35 s** |

Un 404 authentique revient en ~200 ms. **Un 404 au bout de 27 s signifie que
la réponse est perdue, pas en retard** — l'exécution `doGet` correspondante
dure moins de 2 s côté serveur. Au-delà d'une dizaine de secondes, attendre
ne la fera jamais venir. La cause est dans l'acheminement (redirection
`/exec`), hors de portée du code ; seuls ses effets s'atténuent côté client.

Conséquences, à ne pas réapprendre à chaque session :

- **Ne pas conclure « GAS est lent » ni « le classeur est trop gros »** sur un
  appel long : quand la livraison passe, 221 ateliers reviennent en 1 s.
- **Ne jamais rallonger les plafonds.** L'erreur la plus coûteuse : à 35 s,
  chaque livraison ratée devenait 35 s d'écran d'attente — 84 s relevées pour
  une seule connexion, dont 51 d'attente pure sur des appels déjà morts.
  Plafonds actuels : 12 s en lecture, 12 s en écriture, 25 s pour `saveMany`.
  **Verrouillés par `reseau.test.js`** : si un de ses cas échoue, c'est qu'on
  est en train de refaire l'erreur.
- **La panne frappe par fenêtres de temps, pas par appel.** Le 18/09 à 22:10,
  les trois appels d'ouverture meurent dans la même seconde et leurs trois
  doublons réussissent dans la même seconde. Donc moins d'appels simultanés =
  moins de chances de tout perdre d'un coup. Avant d'ajouter un appel au
  démarrage, vérifier que l'info ne voyage pas déjà dans `getAll` (drapeau
  maintenance, listes, visibilité, couleurs, stock). **`appels.test.js`**
  échoue si un appel supprimé réapparaît.
- **Rejouer une écriture est sûr** — vérifié en production le 18/09 : le
  client génère l'`_id` avant l'envoi et `actionSaveEntry` retrouve la ligne
  pour la remplacer (`saveEntry #1` abandonné, `#2` réussi, aucun doublon
  constaté dans la feuille). Mais les écritures restent **séquentielles,
  jamais doublées** : deux appels en parallèle pourraient tous deux conclure
  « ligne absente » et faire chacun leur `appendRow`.
- **Après une écriture, ne jamais recharger pour relire.** `actionSaveEntry`
  invalide le cache `getAll` juste avant de rendre la main : le `loadData()`
  qui suivait relisait la feuille entière, au tarif maximum, pour retrouver ce
  qu'on venait d'écrire. Les écritures s'appliquent localement
  (`appliquerEntree`/`retirerEntree`, prop `onEntryUpdated` côté vues).
- Les Exécutions Apps Script n'affichent que `doGet`, jamais le nom de
  l'action — comparer par horodatage avec `window.__gasLog`.

## 6. Routine RGPD & sécurité des accès/données

Source canonique : `MD-LIB/rgpd-securite.md`. À vérifier en début de session
(dès qu'un fichier touchant à des données utilisateurs, accès, identifiants
ou config d'hébergement est lu/modifié) et avant de considérer un chantier
terminé (formulaire, export, nouvel appel API, stockage, authentification).

Checklist condensée :
- **RGPD** : minimisation des champs collectés, base légale de la collecte,
  durée de conservation/purge, droits des personnes (accès/rectification/
  suppression), sous-traitants et hébergement (GAS, CDN — hors UE ?), données
  sensibles, traçabilité des traitements.
- **Sécurité** : pas de secret/clé/token en clair dans le code ou poussé sur
  le repo, action sensible protégée par authentification réelle, échanges en
  HTTPS, pas de donnée sensible en localStorage/cookies sans nécessité, libs/
  CDN externes vérifiées, permissions par défaut minimales, logs sans données
  personnelles en clair.

Signaler tout point non garanti explicitement dans la réponse (`⚠️ RGPD/
sécurité : ...`), même sans qu'on le demande — immédiatement si critique
(secret exposé, donnée sensible non protégée), sinon en une ligne courte.

**Audit trimestriel :** en complément, un audit de sécurité approfondi
(`/security-review` sur `main` + checklist RGPD/sécurité complète sur tout
le repo) est prévu tous les trois mois. C'est une routine planifiée
(`create_trigger`, mode session neuve à chaque déclenchement — indépendante
de toute session de travail), avec notification push/email. Voir
`MD-LIB/rgpd-securite.md` pour le détail.

## 7. Règles de collaboration avec Claude

Extrait du guide de collaboration multi-projets, adapté pour ce dépôt.

### Côté Claude — priorité haute

1. Ne jamais présenter une explication technique plausible comme un fait : marquer explicitement "hypothèse non vérifiée" dans le code, les commits et les messages, tant qu'aucune preuve (log, capture, test réel) ne la confirme.
2. Ne jamais déclarer "c'est réparé", "c'est en ligne" ou "testé" sans vérification réelle du chemin critique (déploiement GitHub Actions, rendu navigateur, test exécuté) — pas une lecture de code qui "devrait marcher".
3. Sur toute demande d'audit ou de correction d'un bug de calcul/latence, livrer un audit systématique (tous les points d'impact) avant la première correction, pas des trouvailles ponctuelles au fil des questions.
4. Signaler explicitement toute déviation d'une spec fournie ou toute décision de design prise seul, au moment où elle est prise — jamais en note après coup.
5. Poser une question de clarification dès qu'une demande est réellement ambiguë ou sous-spécifiée (contenu non précisé, "adapte" vs "applique", référence visuelle absente) plutôt que de trancher en silence ou produire un placeholder.
6. Sur tout appel Bash touchant un repo précis en contexte multi-repo, utiliser `cd /chemin/complet &&` systématiquement ; vérifier `git status`/`git log` et la cohérence CLAUDE.md vs instructions de session avant d'agir, pas après.
7. Toujours faire un `git pull origin main` avant de lire ou modifier le moindre fichier, même si le repo semble à jour — l'oubli est une cause récurrente d'écrasement de travail. Respecter la politique de push définie plus haut (push direct sur `main`, sauf instruction de session explicite contraire) et signaler tout conflit entre les deux avant d'agir, pas après.
8. Après toute reprise de session ou résumé de contexte, relire l'état réel du fichier concerné avant de le modifier ou de le renvoyer — ne jamais présumer qu'un correctif précédent est encore en place.
8bis. Utiliser des dates explicites (JJ/MM ou JJ/MM/AAAA) plutôt que des termes relatifs ("hier", "aujourd'hui", "la semaine dernière", "demain") : la perception du temps de Claude vient d'un contexte injecté en début de session, pas d'une horloge en temps réel — elle devient peu fiable sur une session qui s'étale sur plusieurs jours ou plusieurs reprises.
9. Avant de pousser un changement visuel (CSS/layout), vérifier mentalement les interactions connues à risque (stacking context, overflow, position sticky/fixed) sur les zones sensibles existantes.
10. Sur tout problème réseau/GAS qui dure plus de 3 itérations : demander une capture Network DevTools ou les Exécutions GAS avant de continuer à supposer.
11. Vérifier l'état exact du déploiement GAS (version + URL active dans `shared.js` → `GS_URL`) en début de session dès qu'un bug réseau est signalé.

14. **Doser les tests à leur valeur, pas à la prudence.** Les suites navigateur de ce dépôt coûtent cher à chaque lancement (`reseau.test.js` ~40 s, `sandbox`/`e2e` davantage) : les lancer une seule fois, juste avant le commit, jamais à chaque étape intermédiaire — la section 2 dit déjà laquelle se déclenche sur quoi. `node --check` et les suites Node, elles, sont quasi gratuites : les lancer librement. Écrire un ou deux tests ciblés par correctif, pas quatre à six ; réserver la contre-preuve — celle qui rejoue l'implémentation fautive — aux pièges réellement subtils, ceux qu'on remettrait sans s'en apercevoir.
15. **Les tests ne trouvent pas les défauts de sens.** Ils vérifient des calculs et des états, pas ce qu'un écran est censé signifier : un affichage peut calculer juste et raconter faux. Un test écrit après coup empêche la régression, il ne découvre rien. Ne jamais présenter une suite verte comme une garantie que l'affichage est correct, ni s'en servir pour décharger l'utilisateur du contrôle visuel.
16. **Un harnais de vérification qui échoue est du gaspillage, pas de la prudence.** Avant de conclure à une anomalie, éliminer d'abord l'instrument : générateur de données mal distribué, page non chargée, mauvaise sélection. Réutiliser un harnais qui a déjà fonctionné plutôt que le réécrire à chaque fois.

17. **Le rendu se vérifie à ton œil, pas par un test.** Un changement de rendu pur (couleur, libellé, position, CSS, mise en page) ne justifie ni test ni capture : dire quoi regarder et laisser l'utilisateur confirmer coûte moins cher et voit mieux. La ligne de partage est **rendu / calcul**, pas visible / invisible — un calcul, un filtre ou un format de données garde son test ciblé, parce que l'œil ne contrôle que le cas affiché ce jour-là : une régression sur une combinaison de valeurs rare passera inaperçue. `sandbox`/`e2e`/`reseau`/`appels` ne se lancent que si le changement touche ce qu'ils couvrent vraiment (section 2) ; pour le reste la CI au push suffit. Capture avant/après à la demande, pas par défaut.

**Bonnes pratiques à maintenir**

12. Continuer à demander l'avis avant toute action à fort impact (déploiement, architecture, migration de données) et exécuter vite dès validation courte reçue.
13. Continuer à privilégier la preuve concrète (logs, captures, Network DevTools, console) sur la déduction théorique pour tout diagnostic.

### Côté utilisateur — priorité haute

1. Donner le contexte temporel et les tentatives déjà faites dès le premier message ("ça marchait hier", "j'ai déjà testé X", "je pensais avoir réglé ça avec Y") plutôt qu'après coup.
2. Pour un bug visuel, "bizarre" ou réseau, ajouter une ligne de description du symptôme précis, une capture annotée ou le Network DevTools plutôt qu'une formule vague.
3. Signaler explicitement en début de message tout changement d'état fait hors session (redéploiement GAS, changement d'URL, config, branche renommée, settings modifiés).
4. Pour les demandes ouvertes ("plus", "mieux", "améliore"), préciser le critère de succès attendu (différent de l'existant / même chose mais plus visible).
5. Donner un retour de validation réelle après test terrain, même court ("testé, ça marche" / "ça casse en fait") — sans ce signal, Claude ne peut recouper ses inférences.
6. Quand on revient en arrière, préciser ce qui est conservé vs jeté — "on revient à hier" sans liste efface du travail potentiellement utile.

**Bonnes pratiques à maintenir**

7. Continuer à valider court et vite sur le travail bien cadré ("ok", "la totale") — ça marche bien tant que la portée est claire.
8. Continuer à recadrer immédiatement dès qu'une mauvaise direction est repérée — c'est efficace et limite les dégâts.

## 8. AGORA — demander la contradiction d'une autre session

Espace d'échange entre sessions Claude : [`AGORA.md`](AGORA.md). Règle
complète et justification : `MD-LIB/agora.md`.

**Ne pas déclencher au ressenti.** La confiance de Claude est mal calibrée :
elle est la plus haute là où il raisonne sur ce qu'il n'a pas vérifié. Le
déclenchement se fait sur des faits constatables dans le diff.

**Soumettre un bloc dès qu'un de ces six critères est rempli :**

1. la décision **ferme une porte** — schéma de données, format `localStorage`,
   nouvelle dépendance, contrat entre `shared.js` et les applis ;
2. **deux options envisagées, une seule écrite**, sans arbitrage extérieur ;
3. **trois itérations sans résolution** sur le même problème (cf. règle 10) ;
4. proposition de **défaire un existant dont la raison n'est pas retrouvée**
   (le cas `keepAlive` supprimé puis remis) ;
5. la proposition **contredit une note datée** de `CLAUDE.md`, `CHANTIERS.md`
   ou un commentaire de décision ;
6. **coût irréversible côté usager** — perte de données, migration, rupture
   d'une PWA déjà installée.

**N'y vont pas :** un changement de rendu pur (règle 17), un correctif
localisé appuyé sur une preuve, tout ce qui se défait en un commit.

**Comment :** écrire le bloc, le committer **sur `main` immédiatement**
(exception assumée au workflow de branche de la section 1 — c'est du texte,
ça ne peut rien casser), puis annoncer en une ligne, sur le modèle du
signalement RGPD : `⚖️ AGORA : critère N — <sujet>. Bloc AG-00N commité.`
avec la phrase à coller dans l'autre session. **Soumettre d'office sans
demander l'autorisation, et ne jamais bloquer dessus** : rien ne garantit
qu'une réponse arrive. Soumettre **au moment du choix**, pas après
l'implémentation — devant du code déjà écrit, le contradicteur valide par
biais de statu quo.

**En réponse à un bloc :** ne pas refaire la proposition, chercher ce qui
manque. Verdict `confirmé` (en disant ce qui n'a pas pu être vérifié),
`amendé` (le cas le plus utile) ou `contredit`. **Sans `fichier:ligne`,
mesure ou log, la réponse ne compte pas.** L'utilisateur tranche, pas le
contradicteur.
