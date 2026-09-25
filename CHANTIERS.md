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

## 🚀 24/09/2026 — Bascule prête, fixée au 25/09/2026 à 15 h 30 (reprendre ici)

**Mail envoyé à l'équipe le 24/09/2026** (date et heure annoncées : 25/09 à 15 h 30).

**NextStep officielle** : branche `claude/architecture-refonte-migration-wqlanb`
de `ateliers-cd47_NextStep`, poussée, **non fusionnée** (son `deploy.yml` ne
part que de `main` : la production reste sur le GAS). Code du labo sans ses
particularités (espace de noms `nextstep` gardé, pas de bandeau), 33 tests
e2e verts dont « plus aucun appel au GAS ». **Verrou d'import** : case
« Import de la bascule : verrouiller ensuite » sur `api/import.php`, posée
dans la même transaction que l'import ; la page affiche l'état (dernier
import, verrouillé ou non). Le lever = requête SQL délibérée (phpMyAdmin).

**Ordre du jour J** — dans cet ordre, la maintenance **avant** l'export,
sinon une saisie faite entre les deux serait perdue :
1. GAS NextStep en maintenance : c'est l'interrupteur « maintenance » de
   l'Admin NextStep actuelle (écrit dans la feuille Config du classeur).
2. Export xlsx du classeur (Fichier → Télécharger → Microsoft Excel).
3. `api/import.php` : Analyser, puis Importer **avec la case de verrou**.
4. Fusion de la branche NextStep dans `main` (déploiement 2-3 min).
5. Vérification par l'utilisateur **dans Admin** (un atelier de test créé
   puis supprimé). La maintenance a voyagé avec l'import (`config`) : Index
   affiche encore l'écran de maintenance, Admin passe outre (rôle admin).
   Puis **lever la maintenance dans la nouvelle Admin** et contrôler Index.
   Le mail à l'équipe est déjà parti le 24/09.

⚠️ La maintenance GAS bloque le **chargement** (`getAll`), pas les
**écritures** (`GAS_NEXTSTEP.js:435`, `actionSaveEntry` ne la teste pas) :
une page ouverte avant 15 h 30 peut encore enregistrer dans le classeur
après l'export, et cette saisie serait perdue. D'où la consigne à l'équipe
de fermer l'appli à 15 h 30.
6. Désactiver le déclencheur GAS `envoyerAlertesRetard` (Apps Script →
   Déclencheurs) : il lirait un classeur figé. Rappels à porter plus tard.

**Retour arrière** si la vérification échoue : ne pas fusionner (ou
revert du merge sur `main`), retirer la maintenance GAS. Aucune donnée
perdue tant que personne n'a saisi dans la nouvelle version.

🔒 **Audit de sécurité du 24/09/2026 (soir)** — corrigé côté API :
`setPassword` exige le mot de passe actuel ; journal au nom de la personne
connectée + actions d'administration journalisées ; erreurs PHP jamais
affichées ; `mailtest.php` refusé hors HTTPS ; `ping.php` retiré (et
effacé du serveur par le déploiement). **Laissé tel quel sur décision de
l'utilisateur** : le rôle superviseur garde ses pouvoirs quasi admin (via
Listes : rôles, mails). **HTTPS forcé : vérifié par l'utilisateur le 24/09 (http:// redirigé vers https://)**. **Double authentification Alwaysdata activée le 24/09** (application
TOTP). **Sauvegardes** : quotidiennes et automatiques d'après la doc
Alwaysdata, conservation selon l'offre (3 jours en Free, jusqu'à 30 en
payant) — liste encore vide le 24/09 au soir (base remplie le jour même) :
**vérifier le 25/09 avant 15 h 30 qu'une date du 25/09 apparaît**
(Avancé → Restauration de sauvegardes, déplier la liste SANS valider).
**Offre Free confirmée par l'utilisateur le 24/09 → 3 jours seulement.**
**Copie de nuit en place le 24/09** (demande de l'utilisateur, avant la
bascule) : `api/lib/sauvegarde.php` → `~/sauvegardes/ateliers-*.sql.gz`
(600, hors `www/`), 30 jours gardés, lancée aussi à chaque déploiement ;
`api-tests/sauvegarde.test.php` prouve qu'une copie se **restaure**.
Ne protège pas contre une perte
du compte : **copie chiffrée dans le dépôt privé
`maswaddpt47-cmyk/ateliers-backups`** (créé le 24/09, workflow `copie.yml`
à 04:15 : récupère la copie de nuit, la chiffre avec `age`, la range ;
échoue si la copie de nuit a plus de 26 h). **En attente de l'utilisateur** :
secrets `ALWAYSDATA_COMPTE`/`ALWAYSDATA_SSH_PASSWORD` dans ce dépôt, paire de
clés `age` générée par lui (clé privée hors ligne, jamais vue par Claude),
clé publique à déposer dans `cle-publique.txt`. Tâche planifiée Alwaysdata
(03:00, `php /home/ateliers-numeriques/www/api/lib/sauvegarde.php`, mail
d'erreur) : validée le 24/09 à 21:40. **Vérifié le 25/09 à 07:44
(diagnostic)** : tâche lancée à 03:00:51, code de sortie 0, copie
`ateliers-2026-09-25_030044.sql.gz` (25 Ko). **Sauvegarde Alwaysdata : liste
encore vide le 25/09 à 07:41** (`~/admin/backup` vide ou lien) — cause non
vérifiée ; à revoir le 26/09, sinon question au support Alwaysdata.
2FA GitHub activée et clé d'import confirmée par l'utilisateur le 25/09. **À vérifier par l'utilisateur** : double authentification
GitHub, clé d'import aléatoire ≥ 20 caractères. **Après la bascule** :
ligne `admin_password` du classeur à supprimer, durée d'archive du
classeur à fixer, export xlsx du 25/09 à supprimer ; consigne de l'audit
trimestriel à mettre à jour (elle parle encore des failles GAS). Hérités,
plus tard : jeton non révoqué à la déconnexion, SRI absent sur cdnjs,
déploiement SSH par mot de passe.

📅 **Relève du journal le 30/09/2026** (demande de l'utilisateur ; rappel
planifié dans la session) : journal Admin NextStep depuis la bascule, à
comparer au labo du 24-25/09 (0/27 perdus, médiane 0,3 s, p90 0,6 s — un
seul utilisateur) ; regarder `getAll` et les heures de pointe.

📝 **Après la bascule (demande de l'utilisateur, 25/09)** : le résumé du
journal écrit « N appels GAS » en dur (`utils.js:171`,
`labo-nextstep/utils.js:209`, et le `utils.js` de NextStep) alors que les
appels vont à l'API — remplacer par « appels serveur » dans les trois, test
`utils.test.js:413/435` mis à jour dans le même commit. Constaté sur le
journal du labo du 25/09 : `demanderReinit` (qui n'existe que dans l'API)
y figure, médiane 0,3 s, 0 perte sur 27.

⚠️ **NEWGEN par défaut reste sur le GAS** après la bascule : l'utilisateur
doit passer par `?backend=php` en attendant que NEWGEN bascule aussi par
défaut (à faire juste après, sur son go).

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

**Plan initial — validé le 23/09/2026, amendé par AG-009 (ci-dessous, qui prime)** :
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
  (**corrigé le 24/09/2026** : seul l'utilisateur l'avait installé en PWA ;
  PWA retirée, AG-012).
  Rediriger vers l'URL NEWGEN casse les PWA installées (AGORA critère 6).
  **Piste recommandée** : publier le code convergé **à l'URL NextStep**
  (dépôt NextStep ou redirection de domaine), l'URL NEWGEN restant le labo.
  Idem pour les clés `localStorage` (préférences des conseillers) à
  conserver.

**✅ Décidé par l'utilisateur le 23/09/2026** (« oui pour tout ») :
1. Hébergeur : **Alwaysdata**, compte au nom de l'utilisateur.
2. Bascule **à l'URL de NextStep** (favoris et clés `localStorage` de l'équipe préservés — la PWA ne compte plus, AG-012) ;
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
- ~~Manifests et icônes de NextStep conservés à la bascule~~ — **remplacé
  le 24/09/2026 par AG-012** : plus de PWA ; à la bascule, NextStep reçoit
  le `sw.js` de désinstallation, garde `icons/` (favicon).
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
- **Étape 0 — déployée le 23/09/2026 à 23:58** (run `35925555123` : `ping.php` répond en ligne ; hôte `ssh-<compte>.alwaysdata.net` et dossier `~/www/` **confirmés**). Compte : `ateliers-numeriques`. **Reste : lancer la mesure une journée.** Détail :
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
  ✅ Mot de passe SSH et secrets `ALWAYSDATA_COMPTE` /
  `ALWAYSDATA_SSH_PASSWORD` créés par l'utilisateur le 23/09/2026.
  **Utilisateur, 24/09/2026** : ouvrir
  `https://maswaddpt47-cmyk.github.io/ATELIERS_NEWGEN/banc/cibles.html` sur
  un poste du bureau, compte `ateliers-numeriques`, Démarrer ; vérifier que
  la 1re paire montre Alwaysdata « livré » (sinon CORS à corriger — en-tête
  vérifié en local seulement, l'environnement de Claude ne joint pas
  Alwaysdata) ; laisser tourner la journée, coller le résumé.
  **Fait établi le 23/09/2026 (utilisateur)** : la série du banc du
  22/09/2026 (§1, 30-38 % de pertes GAS) a été faite **depuis le domicile**,
  hors réseau du CD47. Le relevé NextStep du même jour (45 %) vient de
  l'usage réel de l'équipe. Pertes GAS constatées sur deux réseaux
  différents : le réseau du Département **ne peut pas être la seule cause**.
  Mesure au **bureau** toujours nécessaire : conditions de l'équipe, et
  vérifier que le filtrage CD47 ne bloque pas `alwaysdata.net` (sinon
  sujet DSI avant toute bascule). Mesure au domicile = complément.
  **Lecture** : Alwaysdata ≈ 0 % de pertes quand GAS en perd 30 %+ avec
  McNemar > 3,84 → la perte vient de GAS, la refonte la supprime. Pertes
  comparables → réseau des postes, on garde reprises et doublage.
- **24/09/2026 — utilisateur** : export xlsx du classeur NextStep
  (4 feuilles), **hors dépôt** (données personnelles). Par Google Sheets :
  *Fichier → Télécharger → Microsoft Excel*. **Pas** l'export XLSX de
  l'appli (`shared.js:2151-2156`) : ateliers filtrés de l'année affichée
  seulement, 15 colonnes sans `_id`/`ampm`/prêt de matériel, dates
  reformatées, ni Config ni Comptes ni journal.
  **Décidé le 23/09/2026 : pas d'export dans l'appli.** Il vivrait dans le
  GAS de production (redéploiement manuel) et devrait renvoyer la colonne
  `Hash` — une action qui divulgue les mots de passe, dont certains en
  clair. Le script d'import lit le .xlsx de Sheets, rejouable le jour J.
- Puis Claude, selon le résultat de l'étape 0 : schéma SQL + import → API
  PHP → test de contrat serveur → `shared.js` en POST derrière un
  interrupteur.
  **Précision 23/09/2026** : schéma + import ne dépendent **pas** du
  résultat de l'étape 0 (elle ne décide que du retrait des reprises et du
  doublage). Ils attendent les en-têtes réels du classeur.
- **✅ Étape 0 — résultat de la nuit du 23 au 24/09/2026** (`banc/cibles.html`,
  mesuré **depuis le domicile**, confirmé par l'utilisateur le 24/09/2026) : 158 paires, 23:04 → 04:35 UTC.
  GAS NextStep **15 perdus (9,5 %)**, médiane 2,7 s, 7 livrés > 12 s ;
  Alwaysdata **0 perdu**, médiane 0,3 s, 0 > 12 s. Discordantes 15 / 0,
  **McNemar χ² = 13,07** (> 3,84). Pertes GAS dans 5 heures sur 6.
  **Lecture** : même poste, même instant — la perte vient de **GAS**, pas du
  réseau du poste. La refonte la supprime. Au plafond client de 12 s, les 7
  réponses tardives comptent aussi comme échecs : **22/158 = 13,9 %** côté
  usager, la nuit, hors charge.
  **Limites** : nuit seulement (pertes de jour relevées à 30-45 % les
  22-23/09) ; une seule nuit ; **à faire au bureau** : vérifier que le
  réseau CD47 laisse passer `alwaysdata.net` (une paire suffit : Alwaysdata
  « livré »), **bloquant pour la bascule** — sinon sujet DSI.
  **Conséquence** : le retrait des reprises/doublage (ex-étape 4) redevient
  possible **après** la bascule, pas avant — tant que NextStep reste sur GAS,
  ils servent.
- **✅ 23/09/2026 — schéma + import écrits** (`db9634d`), bloc **AG-010**
  ouvert (schéma typé, import strict). L'utilisateur a fourni l'export xlsx
  réel **dans la session** (non commité, supprimé des fichiers de travail) :
  analyse locale = **262 ateliers, 64 prêts de matériel, 5 comptes, 13 clés
  de config, 1 219 lignes de journal, 0 erreur**. Constats sur le vrai
  fichier, à ne pas réapprendre :
  - 6 onglets : deux « Copie de … » **ignorés** (jamais lus par le GAS) ;
  - `Config` **n'a pas de ligne d'en-tête** (ligne 1 = `app_version`) ;
    6 clés mortes (lues par aucun code) non importées, dont
    **`admin_password` : mot de passe en clair** ⚠️ à supprimer du classeur ;
  - les 5 comptes ont déjà une empreinte SHA-256, aucun en clair ;
  - journal entièrement au format récent ; dates/heures en cellules typées.
  Fichiers : `api/import.php` (page), `api/lib/xlsx.php`, `api/lib/import.php`,
  `api/lib/schema.sql`, `api/lib/base.php`, test `api-tests/import.test.php`
  (lancé par `deploy-api.yml` avec un MySQL jetable avant tout déploiement).
  **Décidé** : identifiants MySQL et clé d'import dans les Secrets GitHub,
  écrits par `deploy-api.yml` dans `~/config-api.php` (hors `~/www/`, droits
  600). Hôte `mysql-<compte>.alwaysdata.net` = **hypothèse non vérifiée**.
  **Déployé le 23/09/2026** (run `35931911529`, vert) : tests d'import passés
  sur le MySQL de GitHub, `import.php` en ligne (désactivée faute de
  secrets), `api/lib/` bien fermé (HTTP 403 : le `.htaccess` est appliqué).
- **Utilisateur — prochaine étape** : créer la base MySQL et son utilisateur
  dans l'admin Alwaysdata, puis 4 secrets `ALWAYSDATA_DB_NOM`,
  `ALWAYSDATA_DB_UTILISATEUR`, `ALWAYSDATA_DB_MOT_DE_PASSE`,
  `ALWAYSDATA_CLE_IMPORT` ; relancer « Déploiement API Alwaysdata » (Actions
  → Run workflow) ; ouvrir `https://ateliers-numeriques.alwaysdata.net/api/import.php`,
  Analyser puis Importer. Claude ne peut pas le faire : pas d'accès
  Alwaysdata depuis son environnement, et ne doit pas voir les identifiants.
  Cet import est un **essai** : on refera un export frais le jour J.
- **✅ 24/09/2026 — import d'essai réussi chez Alwaysdata** (utilisateur,
  export du 23/09) : base `ateliers-numeriques_ateliers` remplie. Hôte
  `mysql-<compte>.alwaysdata.net` **vérifié** (plus une hypothèse).
  À ne pas réapprendre : (1) un secret GitHub ne part au serveur qu'au
  **déploiement suivant** — après toute modification de secret, relancer
  « Déploiement API Alwaysdata » ; (2) l'utilisateur MySQL est sensible à
  la casse (`…_michel`, pas `…_Michel`) ; (3) le workflow manuel
  « Diagnostic API Alwaysdata » rapporte version PHP (8.4), extensions,
  présence de `~/config-api.php` et codes HTTP d'`import.php` sans IP —
  c'est l'œil de Claude sur le serveur. La base d'essai diverge de NextStep
  dès la première saisie de l'équipe : réimport frais le jour J.
- **✅ 24/09/2026 — API de lecture écrite** : `api/index.php` +
  `api/lib/api.php` — `checkPassword`, `getComptes`, `getAll` (`year`/`years`),
  `getConfig`, `getVisibility`. Test de contrat `api-tests/api.test.php`
  (35 cas, relit les champs de `contract.test.js`), lancé en CI avant
  déploiement. Vérifiée en local sur l'export réel : 247 ateliers 2026,
  15 en 2027. Choix ouverts dans **AG-011** (jeton exigé en lecture, dans le
  corps POST ; `getComptes` public réduit + maintenance ; maintenance levée
  par le rôle). ⚠️ **Le client n'est pas branché** : `shared.js`/`app.js`
  inchangés tant que l'utilisateur n'a pas tranché AG-011 (l'ordre de
  démarrage d'Index change : `getAll` après la connexion).
- **✅ 24/09/2026 — API d'écriture écrite** (`api/lib/ecriture.php`) : les
  22 actions du GAS NEWGEN ont leur équivalent. 68 cas dans
  `api-tests/api.test.php`. Écarts de sécurité assumés (déviation de la
  règle « 1:1 », signalée) : `resetPassword` rend un mot de passe provisoire
  **aléatoire** (plus `cd47`+prénom) avec `doit_changer` ; `saveLists` crée
  les comptes **sans** mot de passe ; `logLogin` ne fait plus rien
  (`checkPassword` journalise) ; `logAccesIndex` exige un jeton ; un compte
  désactivé ou changé de rôle perd ses connexions en cours. ⚠️ Conséquence
  client : le changement obligatoire d'Index (`app.js:45-70`) détecte
  aujourd'hui `cd47`+prénom ; il devra lire `doit_changer` de la réponse.
- **⚖️ AG-011 tranché le 24/09/2026 — amendé, amendements acceptés par
  l'utilisateur** (texte complet : `git log -p AGORA.md`, réponse B `6ce65f9`).
  Jeton exigé en lecture, dans le corps POST ; `getComptes` public = noms
  actifs + maintenance ; maintenance levée par le rôle. Amendements :
  (1) `logAccesIndex` journalise le conseiller **du jeton**, le nom choisi
  va dans `ref` ; (2) `getAll` rend `conseillers_inactifs`, l'appel
  `getComptes` d'`app.js:354` disparaît en mode API ; (3) toute réponse
  `auth:true` ⇒ retour à l'écran de connexion (Index et Admin), testé ;
  (4) liste de connexion Admin = tous les actifs, sans filtre de rôle.
  Aussi : Admin lit avant connexion (`admin_app.js:50-61`) — à couper en
  mode API ; passage en POST ⇒ `reseau.test.js` obligatoire. Vérifié sur
  l'export réel : `list_conseillers` = les 5 comptes, à l'identique.
- **✅ 24/09/2026 — NEWGEN branché sur l'API, derrière un interrupteur**
  (`shared.js` : `BACKEND_PHP`, `requeteServeur`). **GAS par défaut** ;
  `?backend=php` dans l'adresse bascule l'onglet (sessionStorage),
  `?backend=gas` revient. Amendements AG-011 appliqués (API `0b81ebd`,
  client ce commit). `appels.test.js` : 9 cas en mode API ; `reseau`,
  `e2e`, `sandbox`, suites Node verts. **Non vérifié en vrai** : l'appel
  réel navigateur → Alwaysdata (CORS, cookies, latence) — l'environnement
  de Claude ne joint pas Alwaysdata. ⚠️ Les écritures faites en mode API
  vont dans la base d'essai, **pas** dans le classeur : ne pas y saisir de
  vrais ateliers.
- **✅ 24/09/2026 — mesure de JOUR (mi-journée)**, PC pro, VPN CD47 : 65
  paires, 12:23 → 14:42 (Paris), 0 écartée. GAS **16 perdus (24,6 %)**, 9
  livrés > 12 s, médiane 2,7 s ; Alwaysdata **0 perdu**, 0 > 12 s, médiane
  **0,3 s**. Discordantes 16 / 0, **McNemar χ² = 14,06**. Au plafond client
  de 12 s : **25/65 = 38 %** d'échecs côté usager avec GAS, 0 % avec
  Alwaysdata. Confirme la nuit du 23-24/09 (9,5 %) et les relevés de jour
  des 22-23/09 (30-45 %) : la perte vient de GAS, pas du poste ni du réseau.
  **Résumé final (arrêt 14:49 Paris)** : 68 paires, GAS 18 perdus
  (26,5 %) + 9 > 12 s = **27/68 = 40 %** d'échecs usager, Alwaysdata 0 ;
  McNemar χ² = 16,06.
  **Arrêtée à mi-journée** (accord du 24/09/2026 : résultat déjà net, la
  soirée n'apporterait rien de décisif). **Étape 0 close.**
- **24/09/2026 — « mot de passe oublié » en libre-service : feu vert de
  l'utilisateur** (bloc AG-013 ouvert, sans réponse B pour l'instant).
  Étape 1 faite : `api/lib/mail.php` + page `api/mailtest.php` (clé
  d'import). **Utilisateur** : tester l'envoi vers son adresse pro
  `@lotetgaronne.fr` **et** une Gmail, regarder réception ET indésirables.
  Si la passerelle du Département bloque : demander à la DSI d'autoriser
  l'expéditeur. Étape 2 (parcours complet) seulement si les mails arrivent.
  Préférer les adresses pro dans Listes → Conseillers (RGPD).
  **Résultat 24/09/2026 15:05** : mail de test **reçu en boîte de réception**
  sur l'adresse pro `@lotetgaronne.fr` (pas en indésirables). Défaut
  d'encodage du nom d'expéditeur/objet (« numÃ©riques ») corrigé. Gmail non
  testé. → Étape 2 (parcours complet) lancée.
  **Gmail testé 15:08 : reçu en boîte de réception**, nom d'expéditeur
  correct après le correctif d'encodage.
  **✅ Étape 2 faite (24/09/2026)** : `api/lib/reinit.php` (`demanderReinit`,
  `reinitMotDePasse`), lien « Mot de passe oublié ? » sur les écrans de
  connexion de NEWGEN (mode API) et du labo NextStep. **Utilisateur** :
  essai réel — renseigner son adresse dans Listes → Conseillers, demander
  le lien, choisir un nouveau mot de passe. Prérequis pour l'équipe : une
  vraie adresse par conseiller (Caroline avait `email@exemple.com`).
  **✅ Validé par l'utilisateur le 24/09/2026** : « marche à merveille sur
  les 2 » (NEWGEN et labo NextStep).
- **24/09/2026 — déconnexion automatique d'Index après 30 min
  d'inactivité** (demande utilisateur ; bouton Déconnexion conservé), NEWGEN
  et labo NextStep. **Choix de conception signalé** : jamais pendant la
  saisie d'un atelier (aucun brouillon n'est gardé) — à revoir si l'on
  ajoute un brouillon de saisie.
- **✅ RGPD — journal conservé 12 mois** (décision utilisateur 24/09/2026),
  purgé à chaque connexion réussie (`API_JOURNAL_MOIS`, `api/lib/api.php`).
  Point « durée de conservation » d'AG-010 réglé.
- **24/09/2026 12:23 (Paris) — une paire du banc** relevée par l'utilisateur
  depuis son **PC professionnel, VPN et pare-feu du CD47 actifs** (pas le
  réseau filaire du bureau) : GAS livré en 5,4 s, Alwaysdata **livré** en
  1,1 s. Le filtrage du poste pro ne bloque pas `alwaysdata.net`. ⚠️ Non
  prouvé pour le réseau du bureau : si le VPN ne fait passer que le trafic
  interne (tunnel partagé, hypothèse non vérifiée), Alwaysdata est sorti
  par la box. Une paire au bureau le 25/09 reste utile (1 min).
  Une paire ne dit rien des taux de perte (McNemar non significatif).
- **Décidé par l'utilisateur le 24/09/2026** : (1) la bascule se fait bien
  **à l'adresse NextStep** (pas de redirection : elle casserait l'appli
  installée) ; (2) **plus de PWA** dans la version convergée — l'accès nomade
  se fait en ligne, par le navigateur. Retrait soumis en **AG-012**
  (critères 5 et 6) ; rien n'est fait tant que l'utilisateur n'a pas dit quand.
  **Fait établi le 24/09/2026 (utilisateur)** : **seul l'utilisateur** a
  installé les applis (NEWGEN et NextStep) ; l'équipe ne les a pas
  installées. Le risque « postes de l'équipe bloqués en mode installé »
  soulevé par la réponse B d'AG-012 ne concerne donc que ses propres
  appareils : le bandeau devient facultatif, une désinstallation manuelle
  suffit.
  **⚖️ AG-012 tranché le 24/09/2026 — amendé** (réponse B `c7add07`), feu
  vert de l'utilisateur. **Fait dans NEWGEN** : manifeste, `apple-touch-icon`
  et enregistrement du SW retirés des deux pages ; `sw.js` = désinstallation,
  publié sans date de fin ; `icons/` gardé ; `CLAUDE.md` §4 réécrit. Bandeau
  « mode installé » non fait (seul l'utilisateur a installé). **À faire** :
  NextStep le jour de la bascule ; l'utilisateur désinstalle ses icônes.
- **Décidé par l'utilisateur le 24/09/2026 — deux interfaces après la
  bascule** : l'utilisateur travaillera sur l'interface **NEWGEN**, l'équipe
  sur l'interface **NextStep**, **même API, même base** (le serveur ne sait
  pas quel écran lui parle). Conséquences à traiter au plus tard le jour J :
  (1) deux fronts à maintenir — toute évolution de l'API vérifiée sur les
  deux (`appels.test.js` + `labo-nextstep.test.js` en CI) ; (2) NEWGEN n'est
  plus un labo une fois branché sur la base de production → créer une
  **seconde base d'essai** Alwaysdata si un terrain d'essai reste utile ;
  (3) ~~les fonctions propres à NEWGEN (plusieurs années, `verifierIds`…)~~
  **corrigé le 24/09/2026** : vérifié dans le dépôt NextStep (`a95d2a6`),
  le sélecteur multi-années (`ChoixAnnees`, `years=`) et `verifierIds` y sont
  déjà, GAS v10.21.0 déployé le 23/09 (`NextStep/CHANTIERS.md:240-255`) — et
  donc aussi dans `labo-nextstep/`. L'interrupteur « accès Admin » est un
  comportement de l'API : il vaut pour les deux interfaces (seul le libellé
  du labo dit encore « login/inactif »). Aucune liste d'écarts fonctionnels
  NEWGEN/NextStep n'a été dressée : ne rien affirmer sans la faire. **D'ici la bascule** : les vrais ateliers se saisissent dans
  NextStep (classeur Google) ; NEWGEN `?backend=php` = base d'essai figée
  au 23/09, invisible pour l'équipe.
- **Confirmé par l'utilisateur le 24/09/2026 : la bascule garde l'adresse
  NextStep** (« c'est encore mieux ») — pas de page de redirection.
  L'annonce aux conseillers passe par un message sur l'écran de connexion
  (`AnnonceNouvelleVersion`, labo NextStep, masqué après « Compris ») :
  même adresse, reconnexion avec le mot de passe habituel (vérifié : les
  empreintes du classeur sont reprises), lien « mot de passe oublié ».
  **Rien publié sur la NextStep officielle** (consigne : « ne la publie pas
  encore »). Jour J fixé au 25/09/2026 à 15 h 30 — ordre corrigé
  (maintenance d'abord) dans le bloc « Bascule prête » en tête de fichier.
- **✅ 24/09/2026 — labo NextStep en ligne** (demande utilisateur) :
  `labo-nextstep/` = copie du front NextStep `a95d2a6` branchée sur l'API,
  pour comparer les deux interfaces sur la même base d'essai. Adresse :
  `https://maswaddpt47-cmyk.github.io/ATELIERS_NEWGEN/labo-nextstep/index.html`
  (et `admin.html`). Garde-fous : `GS_URL` neutralisée, stockage préfixé
  `labo-nextstep:`, bandeau rouge « LABO », `labo-nextstep.test.js` en CI
  (échoue au moindre appel GAS). Si l'équipe choisit NextStep, cette copie
  devient la base publiée à l'URL NextStep le jour J ; sinon on supprime le
  dossier. ⚠️ Toute correction faite dans le vrai NextStep d'ici là devra
  être reportée dans la copie (elle est figée sur `a95d2a6`).
- **✅ Tranché par l'utilisateur le 24/09/2026 — pas de choix proposé à
  l'équipe** (« ne pas trop la bousculer ») : **l'équipe garde l'interface
  NextStep**, l'utilisateur travaille sur NEWGEN (entrée suivante). Le labo
  NextStep n'est donc plus un comparatif : c'est le **futur front de
  production** de l'équipe. Reste à choisir au jour J entre publier la copie
  `labo-nextstep/` à l'URL NextStep, ou reporter la même couche API dans le
  dépôt NextStep lui-même (probablement plus simple : pas de copie à
  resynchroniser). Question ci-dessous close :
- **Question ouverte (24/09/2026) — laisser l'équipe choisir entre
  l'interface NextStep et l'interface NEWGEN ?** Mesuré : l'écart n'est pas
  qu'une question de couleurs. `VueHistorique` diffère de 402 lignes entre
  les deux dépôts ; NEWGEN s'appuie sur 121 `var(--…)` et des composants
  « v2 » (filtres, bandeau KPI, cartes), NextStep sur 14. Deux interfaces
  complètes = deux codes d'écran à maintenir, ce que la refonte veut
  supprimer. Pistes proposées : thème de couleurs « NextStep » sur NEWGEN
  (léger), ou reprendre écran par écran ce que l'équipe préfère (une seule
  interface). En attente de l'utilisateur (ce que l'équipe regrette).
  Pas d'AGORA tant qu'aucune option n'est choisie.
- **✅ 24/09/2026 — premier essai réel par l'utilisateur** en mode
  `?backend=php` : « ça fonctionne, il réagit d'une vitesse
  extraordinaire » (connexion + affichage, test rapide). Trajet navigateur →
  Alwaysdata (CORS, POST) donc **vérifié en vrai**. Reste le test complet
  (liste ci-dessous), à faire sur la base d'essai uniquement.
- **24/09/2026 — retours de test de l'utilisateur (mode API)** :
  ajout et suppression d'atelier OK. « Écran vide après suppression » :
  la recherche « test » restait active (0 résultat attendu), **pas** le bug
  du 23/09 — à reconfirmer par l'utilisateur. **Décision de l'utilisateur** :
  l'interrupteur « login » de Listes → Conseillers = **accès à l'Admin
  seulement**, Index reste ouvert. Appliqué en mode API uniquement ; écart
  assumé au GAS (NextStep compris), où « inactif » coupe tout. Couper
  complètement un agent = supprimer son compte.
  Liste de connexion Admin = interrupteurs activés seuls, Index = tous
  (demande utilisateur). **Vérifié par l'utilisateur le 24/09/2026 : « ça
  marche »**. Noms à accès Admin lisibles sans connexion (sans rôle) :
  écart de confidentialité accepté.
- **Utilisateur — prochaine étape** : ouvrir
  `https://maswaddpt47-cmyk.github.io/ATELIERS_NEWGEN/index.html?backend=php`,
  se connecter avec son mot de passe habituel, vérifier que ses ateliers
  (export du 23/09) s'affichent ; idem `admin.html?backend=php`.

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
