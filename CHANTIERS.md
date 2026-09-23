# Chantiers en cours — ATELIERS_NEWGEN

État au **22/09/2026**, commit de référence `e911aea`.
Fichier transitoire : à mettre à jour à chaque avancée, à supprimer quand tout
est soldé. Ce n'est pas de la documentation permanente (cf.
`MD-LIB/hygiene-instructions.md`).

Pour faire contredire une proposition par une autre session : `AGORA.md`
(section 8 du `CLAUDE.md`). Mis en place le 21/09/2026, un cycle complet
effectué — AG-001 a corrigé le protocole du banc avant la série.

> **Série du banc faite le 22/09/2026 (249 salves) — résultat au §1.** Le
> test à blanc de 07h12 avait validé le banc en conditions réelles.
>
> ⚠️ Pour lire une sortie de banc : « incomplètes » mesure ce que l'usager
> subit, pas le taux de perte réseau — celui-ci est dans le bloc *effet
> PARALLELISME*. Le test à blanc relevait 22 % / 44 % de premiers appels perdus
> pour 0 % de salves incomplètes : les reprises absorbent tout.

---

## 🧭 23/09/2026 — Refonte d'architecture : cap décidé, AG-009 tranché, étape 0 (mesure) en cours

Session de réflexion demandée par l'utilisateur (« prendre du recul, ne rien
faire sauf proposer »). Aucun code touché. **Reprendre ici.**

**Réponses de l'utilisateur (23/09/2026)** :
- Aucune différence métier NextStep / NEWGEN : NEWGEN devait **prendre la
  relève** de NextStep et servir de labo.
- **Pas d'hébergement** (ni mutualisé ni autre). S'il en faut un : un
  hébergeur où Claude peut agir comme sur GitHub.
- **Personne n'utilise le classeur Google directement.**
- Seuls NextStep et NEWGEN sont concernés (pas GDINV2, pas SMS-mail).

**Diagnostic** : deux codes jumeaux qui divergent (`shared.js` : 2 324 lignes
différentes ; stock dupliqué dans NEWGEN §6), transport GAS perdant 30-75 %
des appels (toute l'énergie depuis le 18/09 part à le compenser côté client),
endpoints lisibles sans token avec URL `/exec` publique, `shared.js` de
5 000 lignes, déploiement GAS par copier-coller.

**Proposition (non validée)** :
0. Geler NextStep (bugs seulement), porter ses quelques spécificités dans
   NEWGEN (liste NextStep §6).
1. API PHP reproduisant **1:1** les actions GAS (`getAll`, `saveEntry`,
   `saveMany`, suppression, comptes, config, `checkPassword`, `logLogin`) +
   MySQL. Côté client, quasi seulement l'URL change ; `contract.test.js` sert
   de garde-fou.
2. Import du classeur NEWGEN, bascule, GAS gardé en lecture seule un temps.
3. Import des données NextStep, redirection de son URL vers NEWGEN, archivage.
4. Nettoyage : retirer reprises/doublage/banc et les sections réseau des
   `CLAUDE.md`/`CHANTIERS.md` ; découper `shared.js` sans build.
Une seule migration de backend (NEWGEN), pas deux. Proxy devant GAS
**abandonné** : pansement inutile si GAS part. Pas de framework/build.

**Hébergement — critère** : déploiement déclenché par un push GitHub (Action
avec identifiants dans les Secrets du dépôt, ou intégration native) ; Claude
n'a jamais besoin des identifiants et vérifie via les journaux d'Actions.
Recommandations (tarifs à revérifier) :
1. **Alwaysdata** (Paris) — recommandé : français, PHP+MySQL, offre gratuite
   ~100 Mo, déploiement par Action SSH/rsync à écrire une fois.
2. **Clever Cloud** (Nantes) — push `main` → déploiement natif, payant.
3. Supabase — Postgres+auth sans code serveur, mais société US, pause après
   7 jours d'inactivité en gratuit.

**Précisé par l'utilisateur le 23/09/2026 (2e réponse)** :
- **NextStep est le site de production** : l'équipe travaille dessus, et
  c'est **son** classeur qu'il faudra copier dans la nouvelle base. NEWGEN est
  le labo — ses données ne sont pas à migrer (déduit, à confirmer au moment
  de l'import).
- Donc **pas de dédoublonnage** : un seul jeu de données réel.
- **Compte d'hébergement au nom de l'utilisateur**, faute de délégation pour
  l'ouvrir au nom du CD47. ⚠️ RGPD : données d'agents sur compte personnel —
  choisir un hébergeur français, permettant de **transférer le compte** à une
  entité plus tard, et prévoir une note pour la DSI/DPO. Provisoire assumé.

**Conséquences sur le plan** :
- Étape 2 : NEWGEN bascule sur la nouvelle base avec des données de test (ou
  une copie ponctuelle du classeur NextStep), pas son propre classeur.
- Étape 3 devient **la bascule de production** : import du classeur NextStep
  le jour J, gel des saisies pendant la copie, puis l'équipe passe sur le
  nouveau code.
- ⚠️ **Nouvelle question — l'URL de bascule.** L'équipe a NextStep en favori
  **et installé en PWA** (portée liée au chemin `/ateliers-cd47_NextStep/`).
  Rediriger vers l'URL NEWGEN casse les PWA installées (AGORA critère 6).
  **Piste recommandée** : publier le code convergé **à l'URL NextStep**
  (dépôt NextStep ou redirection de domaine), l'URL NEWGEN restant le labo.
  Idem pour les clés `localStorage` (préférences des conseillers) à
  conserver.

**✅ Décidé par l'utilisateur le 23/09/2026** (« oui pour tout ») :
1. Hébergeur : **Alwaysdata**, compte au nom de l'utilisateur.
2. Bascule **à l'URL de NextStep** (PWA et favoris de l'équipe préservés) ;
   URL NEWGEN = labo.
3. Données NEWGEN = test, **non migrées**. Seul le classeur NextStep l'est.

**⚖️ AG-009 tranché le 23/09/2026 — amendé, amendements acceptés par
l'utilisateur.** Texte complet : `git log -p AGORA.md` (commits `2dafa19`,
`a18c8c3`). À ne pas réapprendre :

- **Étape 0 avant tout code : mesurer.** Même poste, même moment, GAS
  NextStep contre un `ping.php` statique chez Alwaysdata. Si la perte reste
  comparable, elle vient du réseau des postes : la refonte garde ses autres
  motifs, mais **on ne retire pas** reprises/doublage (ex-étape 4).
- **Pas de 1:1 sur trois points** : `checkPassword` et écritures en `POST`
  `application/x-www-form-urlencoded` (pas de pré-vol CORS) ; aucun mot de
  passe en clair en MySQL (`password_hash` à l'import, vérif SHA-256 +
  réhachage au login, changement forcé après réinitialisation) ;
  `getComptes` public réduit (voir ci-dessous).
- **Tests** : `contract.test.js` ne garde pas l'API. Fixtures de réponse
  tirées des `MOCK_RESPONSE` d'`e2e.test.js:162` et `appels.test.js:95`,
  rejouées contre l'API locale. ⚠️ Ces mocks sont accrochés à
  `**/script.google.com/**` : **paramétrer le motif dans le même commit que
  le changement d'URL**, sinon les suites partent sur le vrai réseau.
- **Bascule** : l'ancien GAS passe **en maintenance** (`shared.js:1558-1562`),
  pas en lecture seule — un poste resté sur l'ancien `index.html` (non
  versionné) écrirait sinon dans le classeur abandonné, sans erreur.
- **`APP_NS` par déploiement** : `'nextstep'` à l'URL NextStep, `'newgen'` au
  labo. Sans ça, collision `localStorage` sur la même origine : préférences
  perdues **et** cache `ateliers_cache_<année>` du labo affiché en production.
- **Manifests et icônes de NextStep conservés** à la bascule (même
  `start_url`/`scope`, pas de champ `id` : l'appli installée garde son
  identité — lu, pas essayé sur appareil).
- Toute l'équipe se reconnecte à la bascule (jetons non migrés).

**Décisions de l'utilisateur du 23/09/2026** : amendements acceptés ; compte
Alwaysdata **ouvert** ; export du classeur NextStep **le 24/09/2026** ; écran
de connexion = **liste déroulante des seuls comptes actifs** (l'API publique
ne renvoie que les noms actifs, ni rôle ni état).

**Prochaines actions** (état au 23/09/2026) :
- ✅ Inventaire des actions GAS → [`migration/INVENTAIRE.md`](migration/INVENTAIRE.md).
- ✅ Compte Alwaysdata ouvert par l'utilisateur. **Claude écrit et maintient
  seul le PHP** (l'utilisateur n'en a pas fait depuis 30 ans) : code commenté
  en français, sûreté portée par les tests, pas par sa relecture.
- **Étape 0 — écrite le 23/09/2026, en attente des secrets** :
  `api/ping.php` (JSON statique ~4 Ko, CORS limité à github.io),
  `.github/workflows/deploy-api.yml` (rsync par SSH à chaque push touchant
  `api/`, ou lancement manuel), `banc/cibles.html` (une paire d'appels
  simultanés GAS NextStep `getConfig` / Alwaysdata toutes les 2 min, un seul
  essai, plafond 30 s, McNemar sur les paires discordantes ; testé en
  navigateur avec cibles simulées, pas encore contre les vraies).
  ⚠️ Écart assumé à l'amendement B : **page séparée** plutôt qu'une 3e cible
  dans `banc/index.html` — le banc compare des stratégies sur *un* backend et
  refuse les séries mélangées, il ne donne pas de mesure appariée entre
  backends.
  **Utilisateur** : activer le mot de passe SSH chez Alwaysdata, créer les
  secrets `ALWAYSDATA_COMPTE` et `ALWAYSDATA_SSH_PASSWORD` dans le dépôt,
  puis ouvrir `banc/cibles.html` une journée. Hypothèses d'hôte SSH et de
  dossier `~/www/` non vérifiées : le premier déploiement les confirme.
  **Lecture** : Alwaysdata ≈ 0 % de pertes quand GAS en perd 30 %+ avec
  McNemar > 3,84 → la perte vient de GAS, la refonte la supprime. Pertes
  comparables → réseau des postes, on garde reprises et doublage.
- **24/09/2026 — utilisateur** : export xlsx du classeur NextStep
  (4 feuilles), **hors dépôt** (données personnelles).
- Puis Claude, selon le résultat de l'étape 0 : schéma SQL + import → API
  PHP → test de contrat serveur → `shared.js` en POST derrière un
  interrupteur.

---

## 1. Tranché le 22/09/2026 — les lectures doublées l'emportent

**La question ouverte depuis le 18/09 est close.** Série du banc : 249 salves,
backend NEWGEN, 07h26→17h15, les deux stratégies en alternance sur le même
backend et le même poste.

| | file d'attente | lectures doublées |
|---|---|---|
| Salves incomplètes | **23/125 — 18,4 %** | **5/124 — 4,0 %** |
| Durée médiane | 26,0 s | 11,9 s |
| Salves > 30 s | 45 % | 15 % |
| Salves > 60 s | **10 %** | **0 %** |
| Appels par salve | 4,3 | 5,4 |

**Test apparié sur 124 paires consécutives** (celui qu'exige
`banc/README.md`) : McNemar χ² = **10,32**, significatif à 1 % — 23 paires où
seule la file échoue contre 5 où seul le doublage échoue. Différence de durée
appariée : **+11,8 s pour la file**, IC95 [+7,8 ; +15,9], la file perdant dans
73 % des paires.

### ⚖️ AG-003 tranché le 22/09/2026 — le verrou GAS d'abord, le portage après

Verdict de la session contradictrice : **amendé**. Ce qu'elle a établi, code à
l'appui, et qu'il ne faut pas réapprendre :

- **`_gasQueue` n'a jamais protégé les écritures.** Son commentaire la présente
  comme le test d'une hypothèse de *latence* (commit `f44f239`, « perf:
  sérialiser les appels GAS »). La retirer ne retire aucune garantie voulue.
- **Elle ne sérialise que le client, et seulement jusqu'à l'abandon** : elle
  repart à l'abandon du navigateur, pas à la fin du script — or un `saveEntry`
  parti en 404 a bien écrit sa ligne. Et elle ne voit ni un second onglet, ni
  un second conseiller.
- **Côté serveur, aucun verrou n'existait**, dans aucun des deux scripts.
  L'invariant « écritures séquentielles » des deux `CLAUDE.md` **n'est
  garantissable que côté GAS**, jamais côté client — à reformuler dans les
  deux fichiers au prochain passage.
- **Risque le plus grave, absent du bloc initial** : un `delete` concurrent
  d'une autre écriture décale les lignes du classeur → l'autre exécution
  **supprime ou écrase l'atelier voisin**. Présent aujourd'hui en production
  dans les deux projets, file ou pas. C'est ce qui justifie le verrou, pas la
  latence.

**Décision de l'utilisateur** : option recommandée — verrou d'abord.

### ✅ Verrou GAS déployé le 23/09/2026 — NEWGEN v11.37, NextStep v10.18.0

Confirmé par l'utilisateur sur les deux projets : tests de sécurité,
enregistrement et suppression d'un atelier ok. Bandeaux ⚠️ retirés.
**Le portage du doublage sur NextStep est débloqué.**

⚖️ **AG-004 tranché le 22/09/2026 — version à déployer : v11.37** (toute
copie plus ancienne est périmée). Les mails « Summary of failures » montraient
3 `keepAlive` bloqués **8 min** les 19 et 20/09. `keepAlive` tenait le verrou
de script pendant sa lecture : une fois le verrou d'écriture en ligne, un tel
blocage aurait refusé toutes les écritures pendant 8 min. Il n'y touche plus
(drapeau `CacheService`). Les refus serveur apparaissent désormais dans le
journal Admin avec le motif `serveur : …`. **Après déploiement, surveiller
les `doGet` d'écriture d'environ 20 s dans les Exécutions** : ce sont des
écritures refusées faute de verrou. Détail : `ATELIERS_NEWGEN/AGORA.md`, AG-004.

### Relevé NextStep du 22/09/2026 — l'angle mort n° 1 d'AG-003 se referme

44 appels sur le **backend NextStep**, 11:31 -> 20:27, usage réel (pas
d'alternance contrôlée) : **20 perdus, 45 %**, médiane des réponses livrées
3,5 s, **221 s d'attente sur des réponses mortes**. Le banc relevait 30-38 %
sur le backend NEWGEN : **NextStep n'est pas meilleur, il est au moins aussi
touché.** C'est l'indice qui manquait à AG-003 — un indice, pas une mesure
appariée.

⚠️ **13 des 20 pertes sont *exposées* au doublage — exposées, pas sauvées**
(amendé par AG-005) : un doublon ne rattrape une perte que s'il part hors de
la panne. Si la panne dure plus que l'écart de doublage (7 s), le jumeau meurt
aussi. Combien sont réellement sauvées : inconnu. `shared.js:880-886` :
`doubler = !ecriture && !GAS_SANS_DOUBLON.has(action)`. Doublées : getAll 5,
getComptes 3, getConfig 4, getVisibility 1 — **13**. Jamais doublées, par
conception : saveEntry 3, checkPassword 2, setConfig 1, logLogin 1 — **7**.
**Nuance, corrigée le 22/09/2026 (AG-005)** — la première version disait
« enregistrer un atelier ne sera pas plus rapide », c'était faux. L'écriture ne
gagne pas le *doublage*, mais elle gagne le *retrait de la file* : `_gasQueue`
sérialise tous les appels côté NextStep, donc une écriture derrière une lecture
morte attend 12 s avant de partir. Gain non chiffré.

### 🔴 Premier relevé avec le compteur de sauvetages — 22/09/2026 au soir

26 appels journalisés, 19 perdus. **Le compteur donne 8 doublons partis, 2 qui
ont sauvé la lecture, 6 morts avec leur jumeau — 25 % de sauvetages.** Le banc
du matin en annonçait 42 %.

**Taux de pertes réel : 21/28, soit 75 %** — pas 73 %. Les 2 originaux
rattrapés par leur doublon n'apparaissent pas dans le journal (voir
ci-dessous), il faut les rajouter des deux côtés de la fraction.

**Pourquoi les 6 doublons sont morts : la panne dure plus longtemps que le
délai de doublage.** Départs reconstruits (`fin - durée`) :

| 22:41:13 | `getAll#1` `getConfig#1` `getComptes#1` — **trois en parallèle** |
|---|---|
| 22:41:20 | les trois jumeaux, exactement `GAS_HEDGE_MS` plus tard |
| 22:41:27 | `logLogin#1` |
| 22:41:32 | `getAll#2` `getConfig#2` |
| 22:41:39 | `getAll#2b` |
| 22:41:52 | dernier mort |

⚠️ **Corrigé le 22/09/2026 (AG-006) — j'avais d'abord écrit ici « 39 s et 50 s
de panne continue, rien ne passe ». C'est faux, et c'est exactement l'erreur
que la session B m'avait déjà signalée en C3 d'AG-005.** Le résumé ne donne que
les 19 échecs, pas les 7 réussites avec leurs heures. Or les bornes de la
période les trahissent : la première ligne du journal est à **11:36:53**,
absente de la liste des échecs, donc **réussie** — 2 secondes après le départ
de `getComptes#1` (11:36:51), **en plein dans la fenêtre que je déclarais
morte**. La borne de fin (22:41:53) est une réussite aussi, 1 s après le
dernier mort.
**Ce qu'on peut dire à la place** : une lecture aboutit en 2 s pendant qu'un
appel parti la seconde d'avant est déjà condamné. **La perte semble se décider
par appel, pas par créneau** — et si c'est le cas, l'explication « le doublon
tombe dans le trou » ne suffit pas à expliquer les 6 morts. Ouvert en AG-006.

### AG-006 tranché le 22/09/2026 — les deux chiffres ne se contredisent pas

**Deux sessions (B et C) ont répondu séparément, sans se voir, et sont
arrivées aux mêmes quatre constats.** Ma proposition de geler le portage est
**rejetée**. Ce qu'il faut retenir, et ne pas réapprendre :

1. **25 % et 42 % n'ont pas le même dénominateur.** Le banc comptait les
   doublons **annulés** dans le total (`banc/index.html:232` et `:513`), la
   production ne les journalisait pas du tout (`shared.js`, branche
   `ctrl.inutile`). Le banc calculait `ok/(ok+ko+annulés)`, la production
   `ok/(ok+ko)`. Corrigé depuis : la production journalise l'annulation sous
   un motif distinct, exclu des deux comptes.
2. **Le taux de sauvetage n'est pas une propriété de la stratégie : il suit
   ≈ 1 − pertes ambiantes.** Ce soir-là, 75 % de pertes → 25 % de sauvetages
   est exactement la valeur attendue si les pertes sont indépendantes. Le
   relevé ne dit rien d'autre que « ce soir-là, 3 appels sur 4 mouraient ».
3. **2/8 ne contredit pas statistiquement 42 %** : Wilson 95 % = [7 % ; 59 %],
   binomial exact P(X ≤ 2 | 8 ; 0,42) = 0,275. Et les 8 ne sont pas
   indépendants — trois partent dans la même seconde.
4. **Le portage a été tranché sur le McNemar apparié** (χ² = 10,32), qui ne
   suppose aucun modèle de panne. Ce relevé ne compare pas deux stratégies, il
   en observe une seule dans un épisode à 75 %. Il ne peut donc ni le
   confirmer ni le réfuter.

**Ce qui était vraiment faux, c'est la promesse, pas le portage** : « 26 s ->
12 s en médiane » vaut **au régime du banc (30-38 % de pertes)**. À 75 %,
aucune stratégie d'appel côté client ne tient 12 s — le levier est alors le
proxy (§3), pas le client. À dire à l'équipe conditionné au régime, ou pas du
tout.

**Et le relevé du soir plaide *pour* le retrait de la file, pas contre** : à
taux de pertes élevé, chaque appel mort dans `_gasQueue` bloque les suivants
12 s. Plus les pertes montent, plus la file coûte cher.

**Mesure à suivre après portage** : le taux de connexions ressenties en échec
(seuil 15 %, §2), **pas** le taux de sauvetage.

Note au passage : les trois appels de 22:41:13 partent **ensemble**, ce qui
confirme une fois de plus que NEWGEN n'a pas de file. Et `logLogin` (2/2
perdus) comme `checkPassword` (3/4) ne sont jamais doublés — le doublage ne
les protégera jamais.

⚠️ **Troisième raison, trouvée le 22/09/2026, de ne PAS comparer les deux
taux — et elle est structurelle.** Quand l'un des deux appels doublés aboutit,
`gasLectureDoublee` **annule** l'autre, et un appel annulé n'est **pas
journalisé** (`shared.js`, branche `ctrl.inutile`). Donc **si le doublon
gagne, l'original perdu disparaît du journal.** Le taux de pertes de NEWGEN
est mécaniquement sous-estimé, celui de NextStep est complet (pas de
doublage). Les deux chiffres ne mesurent pas la même chose, quelle que soit la
méthode de comptage.
**En contrepartie, la trace est exploitable** : le doublon porte le numéro de
son jumeau suivi de `b`, et une ligne `#Nb ok` **est** une lecture sauvée (le
doublon ne part qu'après `GAS_HEDGE_MS` ; s'il gagne, c'est que l'original se
taisait encore). `resumeLogsTexte` compte désormais ces sauvetages —
l'équivalent NEWGEN de la mesure d'attente en file ajoutée côté NextStep.

⚠️ **Deux affirmations retirées le 22/09/2026 (AG-005)** : la « confirmation »
des fenêtres de panne (le résumé ne donnait que les échecs, pas les réussites —
et un appel a manifestement réussi dans un créneau), et la comparaison directe
45 % / 30-38 % (par appel reprises comprises d'un côté, par salve de l'autre :
bases non communes). **L'angle mort n° 1 d'AG-003 est entamé, pas refermé.**

Détail complet et relevé brut : `CHANTIERS.md` d'ateliers-cd47_NextStep, même
section.

### ✅ Doublage porté sur NextStep le 23/09/2026

`gasLectureDoublee` portée telle quelle, `_gasQueue` retirée, tests à jour
(`reseau.test.js`, `e2e/appels.test.js`). Les deux projets ont désormais la
même couche d'appel. Détail et mesure à suivre : `CHANTIERS.md` de NextStep.
L'invariant « écritures séquentielles » est reformulé dans les deux
`CLAUDE.md` : jamais doublées côté client, sérialisées côté serveur (verrou).

## ✅ 23/09/2026 — années multiples et vérification après réponse perdue

Livré côté appli (les deux projets), **GAS v11.40 déployé le 23/09/2026**.
**Validé sur le terrain le 23/09/2026** par l'utilisateur : nouveau cycle de
7 ateliers enregistré correctement, sans doublon (projet non précisé). Reste
à observer : un cas réel de réponse perdue affichant « confirmé dans le
classeur ».
- **Sélecteur d'années à cases à cocher** (`ChoixAnnees`, `shared.js`) :
  plusieurs années chargées en **un seul** `getAll?years=` (AG-007,
  `ATELIERS_NEWGEN/AGORA.md`). `f_annee` stocke « 2026,2027 », l'ancien
  format se relit tel quel. Roadmap et Admin prennent **l'année en cours si
  elle est cochée**, sinon la plus récente (tranché par AG-007 — la version
  précédente de cette ligne disait « la plus récente », périmée).
- **Réponse d'enregistrement perdue** → l'appli demande `verifierIds` avant
  d'annoncer un échec (idée de l'utilisateur). Sans le GAS déployé :
  comportement d'avant (message « recliquez, sans doublon »).
- Tests : `e2e/appels.test.js` de NextStep (cas 8 et 9).
- **AG-007 tranché le 23/09/2026** (amendé par la session B, décision de
  l'utilisateur) : avertissement si le GAS en ligne ignore `years=` ;
  Roadmap/Admin sur l'année **en cours** si elle est cochée ; `keepAlive`
  réchauffe N+1 à partir de septembre (dans la version GAS à déployer).

## 🔴 23/09/2026 — cycle enregistré en double, et divergence entre les deux projets

**Incident** : cycle de 8 ateliers sur NextStep, réponse de `saveMany` perdue,
toast rouge, second clic → **16 lignes**. Chaque clic tirait de nouveaux
`_id`. **Corrigé côté appli, les deux projets** : `_id` gardés tant que
l'envoi n'a pas réussi, toast « recliquez, cela ne créera pas de doublon »
(test `e2e/appels.test.js` de NextStep, contre-preuve faite). Doublons
supprimés à la main par l'utilisateur.

**Divergence** : l'appli a continué d'afficher les doublons supprimés, parce
que NextStep n'avait **jamais reçu** l'`onEdit` ajouté à NEWGEN en v11.29. Et
`onEdit` ne voit de toute façon pas une suppression de lignes. **Déployé le
23/09/2026** : NextStep v10.19.0, NEWGEN v11.38. **Vérifié en ligne par
l'utilisateur le 23/09/2026** : `installerTriggerChangement` lancé dans les
deux projets, une ligne supprimée à la main disparaît de l'appli après un
Sync.

**Inventaire GAS du 23/09/2026** (fonctions présentes d'un seul côté) : hors
simples différences de nom, seuls `onEdit` et `invaliderCacheGetAll`
manquaient à NextStep. Restent propres à NEWGEN, sans équivalent NextStep :
`installerTrigger`/`verifierTrigger` (alerte retards), `backupGAS`,
`ajouterColonneAutre`. **Aucun garde-fou n'empêche la prochaine divergence** :
c'est la piste `gas-client.js` / test de parité (NextStep §6), à rouvrir.

## 🐞 23/09/2026 — « Ordinateurs prêtés » vidé au premier enregistrement : non reproduit

Signalé par l'utilisateur sur les deux projets (PC, nouvel atelier, champs
remplis dans l'ordre, nombre tapé au clavier, seul ce champ vidé). **Au
second essai du même jour, les deux projets enregistrent du premier coup**,
avec et sans dates de prêt. Non reproduit en navigateur automatisé non plus
(ordre exact de l'utilisateur, avec et sans dates). Cause inconnue.
**Hypothèse non vérifiée** : molette de la souris sur le champ numérique
encore actif (Chrome modifie alors la valeur). À rouvrir si ça revient :
demander si le champ affiche vide ou « 0 ».

## ⚠️ Origine commune — les deux applis partagent leur `localStorage`

Découvert le 21/09/2026. GitHub Pages sert les deux projets depuis la **même
origine** (`maswaddpt47-cmyk.github.io`), et `localStorage` est cloisonné par
origine, **pas par chemin**. Les deux applis utilisaient les mêmes clés.

**Ce que ça invalide** : toutes les mesures de journal antérieures au
21/09/2026 mélangent les deux projets. Le §1 attend « une mesure NextStep
fiable » — on sait maintenant qu'aucune ne pouvait l'être, et que la pastille
NEXTSTEP/NEWGEN ajoutée le 19/09 ne pouvait pas le révéler : elle nomme
l'appli qui **affiche** la liste, pas celle qui a **émis** l'appel. C'est
l'explication de l'attribution contestée des captures du 19/09.

**Corrigé** : le journal est cloisonné (`adm_logs_nextstep` /
`adm_logs_newgen`). Les lignes écrites sous l'ancienne clé `adm_logs` ne sont
pas reprises — elles mélangent les deux projets, elles ne sont pas
exploitables. Elles restent dans le navigateur tant qu'on ne vide pas les
données du site.

**Reste à faire** : les préférences sont toujours partagées —
`adm_conseiller`, `adm_dark`, `adm_sidebar_pinned`, `f_annee`, `cal_moisDeb`,
`cal_moisFin`, `sidebar_pinned`. Se connecter sur une appli change donc le
conseiller sélectionné ou le thème de l'autre. Jamais signalé comme un bug à
ce jour, mais c'est la même cause. Correction : préfixer ces clés comme pour
le journal, en migrant l'existant pour ne pas réinitialiser les préférences
des conseillers.

## 2. Le proxy se justifie-t-il ? — répondu à moitié le 22/09/2026

La série du banc donne les deux chiffres, et ils ne disent pas la même chose :

- **Taux de perte par appel : 30 à 38 %** (premier appel, première tentative
  — 30 % quand un seul appel est en vol, 38 % quand plusieurs partent
  ensemble). Dans la fourchette « 30-40 % » qui justifiait le proxy.
- **Taux d'échec ressenti avec le doublage : 4 %.** Sous le seuil des ~15 %
  en dessous duquel le `banc/README.md` dit « ne rien construire de plus,
  l'appli est utilisable ».

**Lecture retenue : le doublage d'abord, le proxy ensuite et sans urgence.**
Le doublage divise les échecs par cinq pour le coût d'un fichier déjà écrit ;
le proxy demande un hébergement qu'on n'a toujours pas (§3). Ce qui resterait
à gagner après le portage, c'est la latence — 11,9 s de médiane, ça reste
lent — pas la fiabilité.

À rouvrir si, après le portage, le taux ressenti remonte au-dessus de 15 %.

## 3. Chantier conditionnel — proxy pour supprimer la perte

La couche de reprise côté client a atteint sa limite : plafonds courts,
appels superflus supprimés, doublons annulés. À 54 % de pertes, aucune
politique de reprise ne compense.

Le proxy appellerait GAS **côté serveur** : la redirection `/exec →
googleusercontent`, qui expire avant d'être suivie depuis un mobile, serait
suivie en quelques dizaines de millisecondes depuis un datacenter. En bonus :
cache court mutualisé pour toute l'équipe, URL `/exec` retirée du JS public
(cf. §4), et les deux projets alignés sur la même couche réseau.

**Bloqué par une question sans réponse au 20/09/2026 :** l'utilisateur
dispose-t-il d'un hébergement exécutant PHP en HTTPS ? Recherche faite dans
les trois repos (fichiers `.php`, `.htaccess`, `CNAME`, workflows FTP,
domaines cités, historique git) : **aucune trace**. Les 13 dépôts sont
publics et vivent sur GitHub Pages, qui ne sert que du statique.

Alternative si aucun hébergement : Cloudflare Workers (gratuit, HTTPS
d'office, ~20 lignes). ⚠️ Ajoute un sous-traitant américain de plus dans la
chaîne — à assumer explicitement pour une collectivité.

## 4. ⚠️ Sécurité — endpoints accessibles sans token

`getAll`, `getComptes` et `getConfig` sont dans `READ_ACTIONS` côté GAS :
accessibles **sans aucun token**. L'URL `/exec` est en clair dans
`shared.js`, dans un dépôt **public**. Qui la lit peut récupérer les ateliers
de l'année, la liste des agents avec rôles et état actif, et la clé `emails`
de la config.

Chantier séparé, décidé le 18/09. Implique un redéploiement GAS manuel et de
revoir l'écran de connexion, qui appelle `getComptes` avant d'avoir un token.

## 5. Nettoyage restant — changelog dans `gas/GAS_NEWGEN.js`

35 entrées de version (~200 lignes sur 1020) subsistent en en-tête. Non
retirées volontairement : ce fichier est la **copie de référence diffable**
du script collé à la main dans l'éditeur Apps Script. Le nettoyer maintenant
désynchroniserait la copie et rendrait illisible le prochain diff avant
déploiement.

→ À faire **au prochain déploiement GAS réel**, quand le fichier doit de
toute façon être recollé dans l'éditeur.

## 6. ⚠️ Piège — la logique du stock est dupliquée dans `shared.js`

Découvert le 21/09/2026 en corrigeant le cumul du jour de retour.
`periodePretMateriel`, `findOrdinateursConflicts`, `getPretsMateriel`,
`totauxParJourMateriel` et `totalJourParConseiller` existent **deux fois** :
dans `logic.js` (celui que testent les suites Node) et dans `shared.js`
(celui que les pages chargent réellement — `logic.js` n'est pas référencé par
`index.html`/`admin.html`, seulement par les tests).

**Corriger `logic.js` seul laisse l'application sur l'ancien calcul, avec
toutes les suites au vert.** C'est le pire cas possible : le test confirme un
correctif qui n'atteint jamais l'utilisateur. Vérifier systématiquement les
deux copies sur tout changement touchant le matériel.

NextStep n'a pas cette duplication : son `shared.js` consomme `logic.js`,
chargé avant lui dans les deux pages. C'est un argument de plus pour la piste
`gas-client.js` du §6 de NextStep — le même principe appliqué à la couche
matériel.

## 7. Vérifications terrain en attente (PWA, 19/09/2026)

Deux points livrés le 19/09 mais jamais vérifiés en dehors des tests
automatisés (qui ne peuvent pas les couvrir) :

- **Installabilité PWA** : confirmer sur un Android réel que "Installer
  l'application" apparaît bien pour `index.html` et `admin.html` (manifest +
  icônes + service worker déployés — voir `MD-LIB/pwa-service-worker.md`).
- **Lisibilité des couleurs de la Frise du parc** : les barres de
  `FriseMateriel` (`shared.js`) sont colorées par conum depuis le 19/09 —
  pas de vérification visuelle du contraste texte/fond pour chaque
  conseiller existant.

---

## ⚖️ AG-008 tranché le 23/09/2026 — mon mécanisme ne tenait pas, le gaspillage si

J'avais proposé de remettre en cause l'amendement 2 d'AG-007 (`keepAlive`
prépare N+1 dès septembre) au motif qu'il **double** le travail d'une fonction
qu'AG-004 sait arrêtée par la plateforme à 8 min 00 s. **Réfuté, vérifié par
moi-même :**

- **Le volume de lectures est inchangé pour NextStep.** `keepAlive` v10.11.3 —
  celui qui tournait pendant les trois incidents — relisait la feuille à
  **chaque** passage, sans jamais sauter (`git show a942c75`, l. 1038-1046) :
  288 lectures/jour. Aujourd'hui : déclencheur 300 s, TTL cache 600 s
  (`gas/GAS_NEXTSTEP.js:305`), donc une passe sur deux relit, et N et N+1
  expirent ensemble → 144 × 2 = **288 lectures/jour. Le même chiffre.**
  (Doublement réel pour NEWGEN, 144 → 288 ; mais NEWGEN lisait déjà deux
  années avant v11.12.)
- **La durée n'est pas le mécanisme.** Une lecture saine prend 1-3 s ; passer
  de 2 à 4 s ne rapproche pas de 8 min. Et les messages sont des erreurs de
  **stockage**, pas « Exceeded maximum execution time ».
- **Le risque est plafonné depuis AG-004.** `keepAlive` ne prend plus le verrou
  de script : un passage bloqué coûte un cache froid et un mail, **plus aucune
  écriture refusée**. C'est ce qui rend le sujet secondaire.

**Ce qui restait vrai, et qui est corrigé (v10.22.0 / v11.41, NON DÉPLOYÉ)** :
N+1 était préparée **tout le temps à partir de septembre, même si personne ne
la cochait** — le cas de la plupart des postes, la plupart des jours. Elle ne
l'est plus que si un `getAll?years=` l'a demandée dans les 6 h (drapeau
`CacheService`, `_marquerAnneeDemandee` / `_anneeDemandee`). Pire défaillance :
le drapeau disparaît et un poste paie une lecture froide — le comportement
d'avant v11.39. Jamais pire.

**Bloc sorti d'`AGORA.md`** : il n'y avait plus rien à trancher — proposition
réfutée, amendement implémenté, et ce qui reste est une **mesure**, pas une
décision. Le verdict allant contre son auteur, le fermer n'était pas un
conflit d'intérêts.

**Mesure qui reste à faire, 30 secondes** : ouvrir un `keepAlive` du 23/09
après-midi dans les Exécutions — il journalise déjà la durée de chaque
préparation (`Logger.log('keepAlive : cache <année> réchauffé en <ms>')`). Un
nouveau mail « Summary of failures » après le 23/09 serait l'indice attendu.

⚠️ **Piste écartée, ne pas la rouvrir** : « les grosses réponses se perdent
davantage ». Aucun gradient dans les relevés du 22/09 — `getAll` (~110 Ko)
42 % contre `getConfig` 67 %, et `logLogin` (minuscule) 100 %.

## 🔒 AG-002 tranché le 23/09/2026 — la journée entière reste la règle sur un prêt multi-jours

**Décision de l'utilisateur : laisser tel quel**, aucune fausse alerte
constatée sur le terrain.

**Comportement à ne pas « corriger » sans raison** (`logic.js` et `shared.js`,
`occupeCreneauMateriel`) :
- prêt d'**un seul jour** → seule la demi-journée de l'atelier est réservée
  (Cynthia le matin et Eva l'après-midi ne se gênent pas) ;
- prêt sur **plusieurs jours** → journées entières, du prélèvement à la veille
  du retour (le retour se fait le matin, il ne réserve rien).

**La raison est une donnée qui n'existe pas, pas un choix de design.**
`date_prelevement_materiel` et `date_retour_materiel` sont des **dates sans
heure** ; le champ `ampm` appartient à l'atelier, pas au prélèvement. Sur un
prêt multi-jours, **rien dans la saisie ne dit si le matériel part le matin ou
l'après-midi**. Affiner supposerait d'ajouter deux champs au formulaire, pour
toute l'équipe — chantier réel, non justifié à ce jour.

**Le sens du compromis est volontaire** : on sur-réserve plutôt que de
sous-réserver. Une alerte de trop coûte une vérification ; une alerte
manquante coûte un conseiller qui arrive sans matériel.

**Quand rouvrir** : si une alerte de conflit se déclenche sur un créneau où le
matériel était en réalité libre. Pas avant.

## Points à ne pas défaire

- **`keepAlive` ne prend jamais le verrou de script** (AG-004, 22/09/2026).
  La plateforme peut le bloquer 8 min (mails des 19-20/09). S'il tenait le
  verrou, les écritures seraient refusées pendant tout ce temps. Le drapeau
  `CacheService` qui le remplace n'est pas atomique, et c'est voulu : au pire,
  deux lectures en double.
- ⚠️ **Hypothèse non vérifiée, antérieure à AG-004** : une lecture complète
  (`keepAlive` ou `getAll` sur cache froid) qui se termine juste après une
  écriture remet en cache des données d'avant l'écriture, pour 10 min au
  plus. Le verrou de `keepAlive` ne fermait ce cas qu'en partie : `doGet`
  n'en a jamais pris pour lire. À surveiller si un atelier enregistré
  « disparaît » puis revient.

- **Les lectures sont doublées, pas sérialisées** (tranché le 22/09/2026,
  mesure de 249 salves, McNemar χ² = 10,32). La file d'attente laissait 18 %
  des connexions échouer et 10 % dépasser 60 s ; le doublage tombe à 4 % et
  aucune. Le parallélisme coûte bien 7,5 points de pertes supplémentaires —
  l'hypothèse de NextStep n'était pas fausse — mais le doublon en rattrapait
  **42 % au régime du 22/09 matin (30-38 % de pertes)**, ce qui l'efface
  largement. ⚠️ **Ce 42 % n'est pas une constante** (AG-006) : le taux de
  sauvetage suit ≈ 1 − pertes ambiantes. Le soir du 22/09, à 75 % de pertes,
  il tombe mécaniquement à 25 % sans que la stratégie ait changé. **Le lire
  toujours avec le taux de pertes du même relevé.** Ne pas revenir à la
  sérialisation sans une mesure au moins équivalente — et noter qu'à taux de
  pertes élevé la file coûte *plus* cher, pas moins : chaque appel mort y
  bloque les suivants 12 s.
- **Les pertes ne dépendent pas de l'heure.** Sur une journée complète et un
  journal cloisonné, elles sont réparties de 07h à 17h sans pic de midi.
  L'idée que l'infrastructure Apps Script saturerait aux heures ouvrées, née
  d'un relevé partiel le 21/09, est **réfutée** — ne pas la réintroduire.

Chacun a coûté cher à établir et est verrouillé par un test :

- **Plafonds : 12 s lecture, 12 s écriture, 25 s `saveMany`.** Les rallonger
  ne récupère aucune réponse perdue, ça allonge l'écran d'attente. Verrouillé
  par `reseau.test.js`.
- **Aucun appel GAS superflu au démarrage ni après une écriture.** Verrouillé
  par `appels.test.js`.
- **Les écritures ne sont jamais doublées** (deux `appendRow` concurrents =
  atelier en double). La couche réseau le décide seule, via
  `GAS_ACTIONS_ECRITURE` — ne pas repasser ça en option d'appelant.
- **`sw.js` ne met rien en cache et n'intercepte rien.** Voir la section 4 du
  `CLAUDE.md`.
- **Le banc n'a pas besoin d'un troisième bras « parallèle non doublé ».** Il
  existe déjà : dans le bras doublage, le premier appel de chaque lecture part
  seul, le doublon n'arrivant qu'à 7 s. Il suffit de ne pas jeter le sort de
  chaque appel à l'agrégation, ce que `banc/index.html` enregistre depuis le
  21/09/2026. Un vrai troisième bras coûterait un tiers des salves de chaque
  bras, donc la question principale.
- **Le compteur d'alternance du banc suit `salves.length`, jamais 0.** Les
  salves survivent au rechargement : repartir de zéro faisait retomber
  l'alternance sur `file` à chaque reprise (20 salves sur 20 dans le pire cas),
  et lui faisait hériter de toutes les salves d'après-interruption. Le bouton
  Vider le remet à zéro — les deux vont ensemble.
- **Le banc ne réessaie que sur `RETRYABLE_HTTP` + timeout/coupure réseau.**
  Un 403 **et une réponse non-JSON** (les deux formes d'un quota Apps Script
  épuisé, cf. `shared.js:798-801`) font rendre la main, comme en production.
  Ne pas remettre un `reessayable = true` inconditionnel : le `catch` de fin
  de chaîne écrasait la décision prise en amont et rendait le filtrage
  inopérant — le piège est de ne corriger que le `then`.
- **Ne jamais mélanger deux backends dans un journal de banc ; mélanger deux
  jours est au contraire souhaitable.** Les stratégies alternant salve après
  salve, chacune subit les mêmes fenêtres de panne : cumuler deux journées est
  le moyen le moins cher d'atteindre une série concluante.
- **`periodePretMateriel` retombe sur la date de l'atelier**, des deux côtés,
  quand les dates de prélèvement/retour ne sont pas saisies (22/09/2026,
  confirmé par l'utilisateur : le matériel est pris et rendu le jour même).
  Remplace le repli veille/lendemain ouvrés du 19/09, qui était une hypothèse
  et étendait chaque atelier sans dates à trois jours, fabriquant des
  chevauchements que personne n'avait sur le terrain.
- **L'occupation se compte à la demi-journée** (AM/PM) : deux ateliers le même
  jour, l'un le matin l'autre l'après-midi, ne se disputent pas le matériel.
  Deux limites à ne pas rogner : la finesse ne vaut que pour un prêt d'**une
  seule journée** (au-delà, le matériel dort ailleurs et reste immobilisé en
  continu), et une demi-journée **inconnue réserve la journée entière** — une
  alerte de trop coûte moins cher qu'un conflit matériel passé sous silence.
