# Chantiers en cours — ATELIERS_NEWGEN

État au **25/11/2026, 18:15** (passation), bases `main` : NEWGEN `9efefbc`, NextStep `976461c`, MD-LIB `7dfba7a`. **En ligne** : API avec le fil des tickets (déployée 17:01 ✅), pages NextStep ✅ (17:05). **Pages NEWGEN : NON vérifiées** — 3 déploiements échoués (« No artifacts named github-pages » : 17:00 push, 17:02, 17:04) ; un 4ᵉ (run `45662013050`, lancé 17:11) a un résultat **inconnu** : à lire en premier, puis `workflow_dispatch` du workflow entier si échec (l'API est rétrocompatible : anciennes pages NEWGEN et nouvelle API cohabitent, seul l'écran du fil manque). Alerte « copie trop vieille » : pas encore une surveillance (règle 36), passages à recompter. Tables `lectures` et `tickets_messages` à inscrire au registre RGPD (ateliers-backups).

**Fil de discussion des tickets (25/11, demande de l'utilisateur ; sans bloc AGORA ni annonce, ses décisions du 25/11)** : l'Admin et l'auteur ajoutent des commentaires sans changer le statut, les autres lisent ; table `tickets_messages`, action `commenterTicket`, message facultatif au changement de statut, « 💬 à répondre » côté Admin, mails, purge et anonymisation avec le ticket (RGPD-19). Testé : suite PHP, 8 mutations serveur et 6 client rejetées. **À constater par l'utilisateur** : commenter un ticket dans l'Admin sans changer le statut, puis ouvrir le même ticket côté auteur (message, pastille, mail). Piège : `ADD COLUMN IF NOT EXISTS` passe sous MariaDB (local) mais pas sous le MySQL de la CI — SQL portable (`SHOW COLUMNS`).

**Règles nouvelles à approuver (passation 25/11, règle 32 — non écrites, l'utilisateur tranche)** : (a) *absence* : quand l'utilisateur est en déplacement, rien ne part en production ; préparer sur branche, tester, consigner la commande de publication (sa consigne du 25/11) → `collaboration.md` ; (b) *SQL portable* : un test ou une migration n'emploie que du SQL commun à MariaDB (local) et MySQL (CI/Alwaysdata) ; piège `ADD COLUMN IF NOT EXISTS` → ce `CLAUDE.md` § 5 ; (c) *bloc AGORA, critère 1* : un ajout de colonne ou de table à chaud, sans calcul et défaisable, n'ouvre pas de bloc (AG-069 retiré, fil des tickets sans bloc : ses décisions du 25/11) → § 8.

**Structures partenaires co-animatrices (25/11, AG-069, demande de l'utilisateur)** : liste « Structures partenaires » dans Admin → Listes
(aucun compte) ; proposées dans « Co-animateur » (groupe à part), colonne séparée `structure_partenaire`, **hors de toute statistique** (choix de
l'utilisateur : l'atelier reste compté pour le conseiller qui l'anime). NEWGEN et NextStep. Validé à l'écran par l'utilisateur, annonce 42 publiée (texte approuvé). Bloc AG-069
**retiré par l'utilisateur** (pas de calcul, colonne défaisable), comme AG-068. **Volet NextStep corrigé (25/11)** : choisir un conseiller dans « Conseiller » ou « Co-animateur » changeait le filtre de
conseiller et fermait le volet (écouteur `change` d'`app.js`, qui exclut désormais `.side-panel`) ; test `e2e/structures.spec.js`.

**25/11** : (1) **diagnostic temporaire de la cloche** dans l'Admin (ligne sous le menu, numéros seulement : par année,
dernier vu au chargement → plus grand chargé, « départ » = année sans mémoire, « sans serveur » = mémoire serveur absente au
calcul) — à lire par l'utilisateur au prochain atelier ajouté par une collègue, puis **retirer** ; hypothèses non vérifiées :
année sans mémoire (2027), mémoire serveur arrivée après le calcul, ou simples mises à jour. (2) Journal : `creerAtelier` /
`majAtelier` au lieu de `saveEntry` (ancien, ambigu) ; Admin : « Création », « Mise à jour », « Enregistrement », « Suppression ».
(3) Nouveauté 41 complétée (renommer un lien ✏️ ; pas de nouvelle pastille pour qui l'a déjà lue). (4) Critère 7 : un compteur
d'alerte n'ouvre pas de bloc (consignes des deux projets). NEWGEN `94a4491`, NextStep `cd87937`, ✅ 09:55.

**Bandeau « en attente de mise à jour » (23/11, décision de l'utilisateur)** : Index = ses seuls ateliers (animés ou
co-animés), « N de vos ateliers… », quel que soit le filtre ; Admin = filtre conseiller, bandeau cliquable qui n'affiche que ces
ateliers. NEWGEN `8c31f49`, NextStep `5023f6c`/`ca0dded`. Bloc AG-068 ouvert puis **retiré : jugé disproportionné par
l'utilisateur** (compteur d'alerte). Publication NextStep : incident GitHub (« No artifacts named github-pages ») ; relancer
une tâche échouée crée un doublon d'artefact — relancer le workflow entier (`workflow_dispatch`), fait, ✅ 15:36.

**Liens Partage47 renommables (23/11, demande de l'utilisateur)** : bouton ✏️ dans le volet (Reporting), action `lienAtelier` op `renommer` (titre seul, à sa place ; même nom = « déjà » ; test PHP, mutation rejetée). NEWGEN `11ee692`, NextStep `2d04d11`. **Validé par l'utilisateur le 23/11** ; annonce Nouveautés à proposer.

**AG-067 (23/11, signalé par l'utilisateur)** : pastille Tickets de l'Admin recalculée sur la liste chargée (restait à 1 sans
ticket « Nouveau ») ; clic « 🔔 nouveaux ateliers » depuis un autre onglet que l'Historique appliqué au montage (ouvrait
l'Historique filtré sur le conseiller connecté). Poussé : NextStep `fa277d5`, NEWGEN `a1c1c07`/`7c93366` ; tests Node et
navigateur verts dans les deux. **Revue Codex (23/11, `ab6f459`) : 4 écarts, corrigés le 23/11** (NEWGEN `b1828a2`, NextStep `4260323`) : total global des « Nouveau » renvoyé par l'API à chaque lecture/écriture des Tickets, ancien `getAll` sans effet sur la pastille, cloche consommée une seule fois au chargement de l'Historique. **Reste** : essai de l'utilisateur au prochain atelier créé par une collègue (diagnostic de la cloche, plus haut), puis clore AG-067.

**Rappels par mail (22/11/2026)** : chaque atelier renvoie vers son volet NextStep après connexion, sur « Détail » pour un statut à mettre à jour ou « Reporting » pour une fiche bilan. Le lien peut charger l'année de l'atelier dans cet onglet. Le choix d'années mémorisé reste celui de l'utilisateur. Les deux clients comprennent les liens. Le bouton générique reste disponible dans les mails.

**Versions du bac à sable (22/11/2026)** : les deux `version.json` indiquent le SHA de leur checkout en plus des versions de scripts. Le workflow « Déploiement du bac à sable » compare les SHA servis aux SHA attendus après la copie et rapporte le résultat pour chaque application dans le résumé GitHub Actions.

**AG-066 (21/11)** : la mémoire « déjà lu » des Nouveautés et des tickets passe côté serveur (demande de l'utilisateur : un « hard reset »
rallumait toutes les annonces). Table `lectures`, action `marquerLu`, champ `lu` dans `getAll` ; valeur jamais diminuée ; le
`localStorage` reste un secours, **propre à chaque personne** (la plus haute des deux valeurs). Revue Codex du 21/11 (`AGORA.md`) : trois
écarts corrigés le jour même (secours par personne, acquittement des tickets après chargement réussi, dates validées et bornées), voir
« Suite — auteur ». Testé : suite PHP, `e2e/lu-serveur.spec.js` (4 parcours) sur les deux applis, mutations rejetées. **À faire** : mention
de la table `lectures` au registre RGPD (ateliers-backups, hors de cette session) ; annonce Nouveautés non décidée (sujets et texte à faire
approuver) ; AG-066 à clore par l'utilisateur. **À savoir** : après la mise en ligne, les pastilles se rallument une fois pour chacun
(anciennes clés locales non reprises) jusqu'à la première ouverture de Nouveautés. **Extension (21/11, demande de l'utilisateur)** : la pastille des
nouveaux ateliers garde aussi sa mémoire au serveur, **une par année** (`ateliers_index_AAAA` / `ateliers_admin_AAAA`), **partagée entre
NEWGEN et NextStep** par page (confirmé par l'utilisateur le 21/11). Revue Codex (4 écarts) corrigée : années séparées, numéros jamais
réutilisés après purge (`meta.n_max`), acquittement en échec repris au chargement suivant, rien envoyé à une API d'avant. **Pas couvert** :
les numéros purgés avant le 21/11 restent inconnus de `n_max`.
**Purge (22/11, demande de l'utilisateur)** : à chaque connexion, mémoire `lectures` effacée sans compte actif ou sans connexion réussie
depuis 12 mois (`lectures_purger`, AG-066). Reste à inscrire au registre RGPD avec la table (ateliers-backups).
À constater
par l'utilisateur : nettoyer les données du site sur son téléphone, se reconnecter, vérifier qu'aucune annonce ne repasse en « Nouveau ».
Toutes les livraisons du 20/11 sont en production (déploiements ✅) ; rien en attente dans le bac à sable ni sur la branche de session.
En attente de l'utilisateur : essai de la pastille retenue (atelier créé par une collègue, puis rechargement) ; registre RGPD V0.8 à présenter au DPO (écart n° 15).
Reprise : actualiser les deux `main` (`fetch` + `merge --ff-only`), relire les consignes et ce fichier ; aucun brouillon à
réappliquer. Revues closes jusqu'à AG-065 (AG-064 retiré) : ne pas les relancer d'office. **Autorisations ChatGPT non résolues** :
confirmation `create_tree` encore vue malgré « Autoriser toutes les actions », cause non établie.
**AG-065 en production le 20/11** (NEWGEN `4ec17dd`, NextStep `4f5c8d5`, déploiements ✅) : liens Partage47 (5 max, action
`lienAtelier`), volet en onglets Détail / Reporting (QR en tête, liens, fiche bilan remplissable avant « Réalisé »), QR visible sur
téléphone (barre du bas sous le volet ouvert ; ne pas remonter le volet au-dessus de 4000). AG-065 clos le 20/11 ;
annonce 41 publiée, 23 et 28 mises à jour (textes approuvés). Piège : un test ne clique jamais un nombre seul (MD-LIB règle 75).
Pastille des nouveaux ateliers (20/11, au serveur depuis le 21/11 : AG-066) : mémoire par page et par personne (plus grand `_n` vu) ; 1re visite = point de départ.
Anomalies : liste « Validées » retirée (20/11) ; l'action `annulerAnomalie` reste dans l'API, sans bouton.
Liens `/public.php` refusés (20/11, navigateur et API) : ils ne s'ouvrent pas, même connecté ; lien interne (`/index.php/apps/files/…`) ou de partage (`/s/…`) seulement. Registre RGPD V0.8 corrigée sur place (ateliers-backups) : liens Partage47 inscrits, écart n° 15 à présenter au DPO.
**AG-063 clos le 19/11** (avis en graphes, période ou atelier ; non annoncé) : règles dans « Points à ne pas défaire ».
**Impression des graphes (19/11)** : depuis la vue en graphes, « Imprimer / PDF » sort les graphes affichés (SVG, 2 pages A4
sur un essai fictif) ; liste des ateliers avec l'orienteur. Pas de bloc AGORA : mêmes chiffres, aucun calcul nouveau
(décision de l'utilisateur ; précision ajoutée au critère 7 le 19/11 avec son accord). **Validé à l'écran par
l'utilisateur le 19/11** (impression des graphes).
Le 17/11 : coupure d'urgence éprouvée sur la page NextStep du bac à sable ; annonce AGORA « publiée dans <dépôt> » et
relecteur de l'autre éditeur dans les consignes.

**Clôture Codex — 21/11 (règle 32)** : aucune nouvelle règle convenue non écrite ; règles 73 à 75 de MD-LIB relues,
déjà inscrites. Trois `main` actualisés, sans reprise de brouillons ni réaudit des blocs clos. Vérification AG-066 :
3 tests unitaires ciblés et 2 parcours navigateur existants passent sur NEWGEN avec API fictive ; deux défauts client
rejoués sur les fonctions réelles, validateur de date relu. Pas de test SQL, donnée réelle ou déploiement vérifié
par Codex ce tour. Aucun code modifié : réponse dans AGORA, passations mises à jour séparément.
Prochaine action : l'auteur corrige AG-066 et livre le portage NextStep ; conserver le suivi du bilan mensuel
ci-dessous et la limite de l'annulation d'AG-062. Ne pas reprendre les scripts temporaires hors Git.

**AG-061 et AG-062 clos le 16/11** par l'utilisateur ; bandeau « nouvelle version » **non annoncé** (sa décision du 16/11).
**AG-062 (16/11)** : 168 durées vides sur 339 remplies à 1 h 30 en base (`durees-vides.yml`, recompté 0), à la demande de
l'utilisateur, qui remplace sa décision du 05/10. Annulable par le mode `annuler` ; table `durees_vides_20261116` à
supprimer après le 16/12/2026 si rien n'est annulé. La Corbeille n'est pas touchée.
**Tous les conseillers** : aucun filtre par conseiller (ceux de l'utilisateur l'étaient depuis le 05/10, les 168 sont ceux
de l'équipe) ; recompté le 16/11 à 20:50 et 20:54 : 0 sur 339. Bac à sable (base fictive) non touché. Une page ouverte
avant 20:31 montre l'ancien état : recharger.
**Écart de Codex corrigé (16/11)** : un onglet d'avant 20:31 renvoyait la durée vide (statut groupé) et l'API effaçait le
90 ; depuis, une durée vide envoyée garde la valeur en base (`ecriture.php`, test AG-062). « annuler » remettrait à vide une
durée ressaisie à 90 depuis : contrôler avant.
**Bandeau « nouvelle version » (16/11)** : `version.json` écrit au déploiement (Pages et bac à sable), lu hors cache par
`shared.js` ; **jamais vu en vrai** : à constater sur un téléphone (page ouverte avant un déploiement, bandeau après).

**Avant le 05/12 — audit Codex du bilan mensuel (règle 33)** : consigne `ateliers-backups/documents/sources/consigne-audit-codex-bilan.md`
(« pull » à Codex dans ateliers-backups). Le 4 de chaque mois à 09:17, « Bilan mensuel — envoi de test » et « … —
recomptage indépendant » partent seuls (Actions d'ateliers-backups, secret `BILAN_ADRESSE_TEST`) ; essai d'octobre (15/11) : 0 écart. Rappel programmé
le 04/12 à 09:45 pour le tableau affiché / recompté / écart. Octobre : 1 réalisé à 13 présents pour 4 inscrits (probable
faute de saisie, signalé). Fuseau vérifié le 15/11 : MySQL et PHP en heure de Paris.
**Bilan mensuel, défauts 3 et 4 de Codex (15/11)** : rubriques lues sans état commun (`bilan.php:121-155`) ; deux
lancements simultanés enverraient deux fois (`bilan.php:275-285`). Risque faible tant que l'envoi part une fois à 08:00.

Fichier transitoire (`MD-LIB/hygiene-instructions.md`). Contradiction : `AGORA.md`.

---

## En production depuis le 25/09/2026

NextStep (équipe) et NEWGEN (utilisateur) : **même API PHP chez Alwaysdata** (compte `ateliers-numeriques`), **même
base MySQL** (AG-009).

- **Google supprimé** (29/09 et 01/10). **Import verrouillé** : le lever = requête SQL délibérée, sinon un second import
  écraserait les saisies.
- Tâches planifiées : rappels de retard **08:00** chaque jour (`rappels.php`, interrupteur `rappels_actifs`) ; **bilan
  mensuel** à la superviseure le **5** à 08:00 (`bilan.php`, cron `0 8 5 * *`, déclaré par l'utilisateur, non vérifié).
- Sauvegardes : natives (**3 jours**) ; copie de nuit 03:00 (`~/sauvegardes/`, 30 j, restauration prouvée par
  `sauvegarde.test.php`) ; copie **chiffrée** hors site 04:15 (`ateliers-backups`, branche `copies`, `age`, 90 j,
  rattrapages 11:47 et 17:47). Corbeille et onglet Sauvegardes (AG-014) ; **pas** de restauration complète par bouton.
- Sécurité : jeton exigé en lecture, dans le corps POST (AG-011) ; mails rendus aux seuls admin/superviseur ; aucun script
  externe (`vendor/`, RGPD-17) ; seuls tiers : API Géo et fonds OSM France ; 2FA GitHub et Alwaysdata ; journal 12 mois ;
  CodeQL, `semgrep.yml` (non bloquant), Dependabot (aveugle à `vendor/`), secret scanning, push protection.
- **Documents à diffusion restreinte** (`ateliers-backups/documents/`) : registres sécurité et RGPD en V0.x jusqu'à
  validation DPO/DSI (V1), un cran par mise à jour ; rendu par `documents/sources/rendre.js`. Prochaine révision : audit du 01/01/2027 (ACME Alwaysdata à vérifier).
- **Bac à sable** (AG-019, AG-033) : `…alwaysdata.net/sandbox/` et `sandbox/nextstep/`, comptes « Démo … » (secret
  `SANDBOX_MDP_DEMO`), fausses données. Republié à chaque push de code sur `main` de NEWGEN. **Livraison directe sur
  `main` sauf demande du bac à sable.**

## 🔧 Parité NEWGEN/NextStep (AG-015)

**5 écarts, tous `voulu`** (`APP_NS`, `App`, `injectCSS`, `VueHistorique`… : navigation et design propres à chaque appli).
`node scripts/parite.js ../ateliers-cd47_NextStep` (constat), `--maj` (réécrit `parite-ecarts.json`, ce qui a bougé
repasse en `à trancher` : remettre `voulu` à la main). **Ordre de push : NextStep, puis NEWGEN** ; `pre-push` refuse NEWGEN
si la parité n'est pas à jour ou si NextStep a des commits non poussés. **Ne pas uniformiser la charte graphique** (26/09).

## Reste ouvert

**Nouveautés** : prochain `id` **41** (non annoncés : bandeau « nouvelle version », 16/11 ; avis en graphes, 19/11) ; jamais `admin: true` ; jamais 12, 16, 19, 24, 25, 26 ni 29 (publiés puis retirés).

1. **Bac à sable NextStep** (AG-033) : coupure d'urgence éprouvée le 17/11 ; restent à voir au navigateur les
   requêtes vers `api-sandbox` et un avis fictif par le QR.
2. **Supports par atelier (pptx, docx) — mis de côté le 04/10** : supports sur un réseau interne, qu'une page https
   n'ouvre pas en `file://` (hypothèse) ; il faudrait SharePoint/OneDrive ou Alwaysdata. Bloc AGORA au choix (critère 1).
   **Fait nouveau 19/11** : l'utilisateur demande d'attacher des fichiers (CSV d'un diagnostic PIX, résultats WoocLight).
   Options exposées, en attente de son choix : (a) lien vers OneDrive/SharePoint du CD47, aucun fichier chez nous ;
   (b) dépôt de fichiers chez Alwaysdata (données nominatives probables d'un export PIX : registre RGPD, durée, purge,
   contrôle d'accès, quota, sauvegardes ; blocs AGORA critères 1 et 6, Codex sécurité) ; (c) import du CSV dans le
   navigateur pour n'enregistrer que des chiffres agrégés par atelier (règle 57 ; SheetJS 0.18.5 à rouvrir si .xlsx).
3. **Réimport Outlook** : un rendez-vous simple importé avant le 03/10 puis déplacé n'est pas reconnu (nouveau + supprimé).
   Calendrier publié et Microsoft 365 écartés pour l'instant (DPO / DSI).
4. **Thématique « TBD »** (sans urgence) : visible dans les statistiques.

**Audit trimestriel** : routine `trig_01J6ZMsLHKbgXAQsRYgQL16q` (6 dépôts, prochaine le 01/01/2027), **non modifiable par
Claude** ; ses commits vont sur des branches `claude/…` à fusionner. **Rappels de l'audit Codex** à 08:52 :
`trig_012uCnnzo6MZhyWNYr6JAFqv` (2 janvier), `trig_01XC6RefYHqVbN2Wcjz3kkXo` (1er avril, juillet, octobre).

## À ne pas réapprendre — Alwaysdata et déploiement

- **Coupure d'urgence** (AG-027) : `coupure-urgence.yml`, cible `tout` ou `bac-a-sable` (9 à 13 s) ; secours SSH :
  `echo 'Require all denied' > www/.htaccess`.
- **Veilleur d'intégrité** (AG-028, AG-039/040) : un contrôle par heure, tuile dans Admin → Sauvegardes ; arrêt : Disable
  workflow. Après la boucle du 31/10-02/11 (3 603 échecs en 34 h) : relève espacée d'un `intervalle`, alerte sur le dernier
  succès, requête-témoin GET `logLogin` (absente = IMPOSSIBLE) ; jour en cours non compté (AG-029) ; `veilleur.test.js` et `integrite.test.js` bloquants. Éprouvé en réel le
  04/11. Un passage par veilleur (24 relèves par jour), laissé tel quel. Jamais vus en vrai : relance par le filet, pastille « ! ». La tuile lit les runs `status=success`
  de GitHub (peut omettre un run récent, hypothèse non vérifiée).
- **Alerte « copie trop vieille »** (24/11/2026, demande de l'utilisateur) : `copies-fraicheur.yml`, 3 passages par jour
  (≈05:23, 11:23, 17:23 Paris en hiver), mêmes règles que les tuiles Admin (nuit 02:00-04:59, chiffrée, seuil 26 h) via
  `scripts/fraicheur-copies.sh` ; échec rouge = mail GitHub. Lecture seule (noms de fichiers + date du marqueur). Test :
  `fraicheur.test.js` (bloquant, deploy.yml), 5 mutations tuées. **À éprouver en réel par l'utilisateur** : Actions →
  « Fraîcheur des copies » → Run workflow avec `test_alerte` (le mail arrive-t-il ?), puis un passage normal (vert attendu).
  Arrêt : Disable workflow. Jamais vu en vrai : le chemin SSH réel et le format du marqueur `.derniere-copie-chiffree`.
- **Correctif PHP : redémarrer le site** (Web → Sites ; version servie : `diagnostic-api.yml`). **SSH par clé seule**. Un
  secret GitHub ne part qu'au déploiement suivant.
- Utilisateur MySQL **sensible à la casse** ; `api/lib/` fermé par `.htaccess` (403 vérifié à chaque déploiement).
- Identifiants : Secrets GitHub, écrits par `deploy-api.yml` dans `~/config-api.php` (hors `www`, 600). Claude ne joint pas Alwaysdata : ses yeux sont les
  workflows manuels (`diagnostic-api.yml`, `durees-vides.yml`), qui n'affichent que des nombres.
- **Date du jour : `date('Y-m-d')` en PHP, jamais `CURDATE()`** ; `NOW()` ailleurs (corbeille, journal, sessions) : même
  piège possible, non traité (fuseau Paris vérifié le 15/11).
- **Poste pro de l'utilisateur sans invite de commandes** : terminal web d'Alwaysdata, une commande courte, sans `~`.
- **Tests PHP en session** : si MariaDB refuse (`Connection refused`), `service mariadb start`, root `testmdp`.

## Décisions de l'utilisateur à ne pas « corriger »

- **Origine web partagée avec GDINV2 : séparation abandonnée le 04/11** (« ne plus me la rappeler »), sauf fait nouveau.
- **Planning et Calendrier (04/11)** : on garde l'existant ; sur téléphone, seuls lundi et mardi tiennent.
- **Fiches bilan** : interrupteur « 📋 Fiches bilan vides » rallumé le 28/10, ateliers du 01/11/2026 et après ; la fenêtre
  au passage en « Réalisé » n'annonce le mail que s'il partira (`rappelFichePrevu`).
- **Bilan mensuel le 5** : « planifiés en <mois> » garde tout le mois (bloc critère 7 si cela gêne).
- **Écartés** : PWA (AG-012), nouveau modèle de données, PHPWord/TCPDF.
- **Plus de démarche DPD ni Archives départementales (16/10)** ; écarts du registre RGPD V0.7 ouverts dans le registre ;
  mention d'information des agents classée sans suite le 04/11.
- **Risques acceptés (02/10)** : `StrictHostKeyChecking=accept-new` ; blocage 15 min après 5 échecs par compte, sans IP ;
  SheetJS 0.18.5 (**rouvrir** si un import `.xlsx` apparaît côté navigateur).
- **Mots de passe** : l'actuel exigé pour changer le sien, sauf provisoire (AG-023) ; l'Admin envoie un lien par mail
  (`envoyerLienReinit`).
  Provisoire imposé par l'API : pas fait, par choix.
- **Interrupteur « login »** = accès à l'Admin seulement ; couper un agent = supprimer son compte ; noms à accès Admin
  lisibles sans connexion : accepté. Le superviseur garde ses pouvoirs et n'apparaît pas à la connexion d'Index.
- **Déconnexion d'Index après 30 min** d'inactivité, jamais pendant une saisie.
- **Interfaces** : `PanneauAtelier` commun ; filtre public à choix multiples ; « Effacer » vide tout ; frise à une ligne
  par conseiller ; « ⚠️ ordi » clignotant ; chevron « › » de NEWGEN (écart voulu).
- **Avis des stagiaires** : anonymes, sans âge ni recontact ; un par appareil, plafond = présents (à défaut inscrits) ;
  réservés à l'animateur et au co-animateur ; sous **3 avis**, le nombre seul (`AVIS_SEUIL`) ; export CSV formules neutralisées.
- **Côté conseillers** : « Mes bilans » limité aux ateliers animés ou co-animés, masquable ; pas de satisfaction par conseiller.

## ⚠️ Pièges connus

- **« Récupérable 30 jours dans la Corbeille »** (Nouveautés 9 et 15, suppression multiple) : vrai tant que la Corbeille
  est ouverte (Admin → Visibilité). Si on la referme, revoir ces textes.
- **Page HTML en cache sur téléphone** : `index.html` n'a pas de `?v=`. Le bandeau du 16/11 propose de recharger ; avant
  d'accuser un bug après livraison, faire recharger.
- **« Ordinateurs prêtés » vidé au premier enregistrement** (23/09, non reproduit). Hypothèse : molette sur le champ
  numérique. Si ça revient : demander s'il affiche vide ou « 0 ».
- **Plafond d'avis** : sans mise à jour des présents, le 5ᵉ stagiaire est refusé (4 inscrits par défaut).

## Points à ne pas défaire

Déjà dans `CLAUDE.md` §4-5 et verrouillés par les tests : `_id` client, écritures jamais doublées, plafonds, aucun appel
superflu, `sw.js` de désinscription.

- **Chiffres (AG-032, 25/10)** : participations = présences cumulées sur les ateliers à présents saisis, couverture
  affichée ; taux seulement sur les réalisés à inscrits > 0, sinon « — » ; un zéro saisi est un vrai zéro ; durée absente
  = 1 h 30 ; avis en % de tous les avis avec « Sans réponse » ; année non chargée = aucun chiffre. Pas de seuil ni
  d'objectif sans décision métier.
- **Bilans (AG-035, 037/038)** : onglet « 📈 Bilans » Mois / Trimestre, `bilanPeriode` commun écran, impression et mail ;
  « À venir » sans estimation ; co-animé compté pour les deux ; sujets d'avis multiples, `bilanAvis` exige `format=2`.
- **Journal de connexion (AG-036)** : connexions Admin réussies écartées avant la limite ; `getUsageOnglets` non journalisé.
- **Saisie (25/10)** : formulaire, cycle et préparatifs Outlook gardés en mémoire entre onglets, jamais stockés ni envoyés ;
  identifiants d'une écriture non confirmée stables à la reprise.
- **Graphiques (24/10)** : Liquid Glass ; activité janvier → décembre, années côte à côte, couleurs réservées aux statuts ;
  l'habillage ne touche ni valeurs, ni échelles, ni filtres, ni échappement. Démonstration : jamais sans `bac_a_sable=true`.
- **Tickets (AG-041 à 046)** : modifiables par toute l'équipe tant que « Nouveau » ; jeton TS limité ; code par mail sauf
  première connexion (jamais pour un « oubli », ni au-delà de 30 jours) ; rattrapages uniques (`ts_rattrapage_unique`) ;
  ancien état comparé en `BINARY` ; chaque « pas de
  mail » dit sa raison.
- **Corbeille** : un conseiller ne voit que ses ateliers (AG-034) ; l'Admin voit tout, sans suivre le sélecteur ; purge réservée admin/superviseur (AG-024).
- **Matériel (AG-002, AG-022)** : seul le stock d'ordinateurs compte, à l'heure près ; un conseiller compte une fois, au max
  de ses quantités (5 + 2 + 2 = 5, repère « ↳ 2 ») ; prêt sur plusieurs jours = journées entières. Rouvrir seulement sur
  une alerte d'un créneau réellement libre.
- **Ouvert à tous (AG-047/048, AG-053)** : `Oui` / `Non` obligatoire à la création (colonne posée dans
  `api_action_protegee`, avant toute transaction) ; filtre de l'Historique sur tuiles et
  compteurs, pas le filtre de statut ; page TS : l'Admin choisit (`ts_ouvert:Oui` / `ts_ouvert:Non`, `ts_filtre_ouvert` n'est plus lu).
- **Page TS (AG-049, AG-050)** : « accès désactivé » seulement avec le bon mot de passe ; provisoire envoyé par mail,
  affiché seulement si le mail échoue, jamais relancé (`GAS_SANS_REPRISE`).
- **Admin** : sections repliables (`admin-replie`, `data-ouvert`), fermées à l'arrivée, ouvertes mémorisées (`admin_sections_ouvertes`).
- **Planning, vue Mois (AG-051)** : une demi-journée ne montre que ses ateliers actifs (`ateliersDemiCase`).
- **Statut groupé (05/11, un `saveMany`)** : « Annulé » propose la liste à supprimer dans Outlook (`listeSuppressionOutlook`), pas un .ics d'annulation (essai raté :
  Outlook a créé « Annulé : … » à côté). Ne pas le remettre sans essai concluant.
- **Volet Détail (05/11)** : commune, lieu, orienteur, conseiller, co-animateur modifiables ; champ vidé = valeur gardée.
- **Heure de Paris côté navigateur** : copie de nuit de 02:00 à 04:59 (AG-052, `derniereCopieNuit`, `ageHeuresParis`) ; « Planifié, date
  passée » sur `jourParis()` (AG-061). Jamais le fuseau de l'appareil.
- **Anomalies (AG-044, AG-061)** : une validation sort l'anomalie tant que la valeur ne change pas ; durée absente =
  empreinte `(vide)`. Aucun autre calcul n'en tient compte.
- **Avis en graphes (AG-063, 19/11)** : même population que le tableau « Avis par atelier » (tous statuts) ; % sur tous les
  avis, « Sans réponse » à part ; radar = réponses favorables (4-5 étoiles, « Adapté », « Oui », « Oui ») sur les avis qui
  ont répondu, non tracé si une question est vide ; vue restreinte : masquage sous 3 avis sur le plus petit compte, lecture
  cohérente (`avis_masque_restreint`) ; calcul `resumeAvisAteliers`, total = `detail.total`. Écran et impression partagent
  `optionsGraphesAvis` et `textesGraphesAvis` : ne pas recréer un second jeu d'options pour l'un des deux.
- **Durée (AG-017, AG-062)** : absente ou vide dans un envoi = la valeur en base reste ; aucun écran ne vide une durée.
- **`check-chantiers.sh`** (copie de référence ici) : alerte au-delà de 300 lignes **ou 30 Ko** (MD-LIB 8ter, 16/11).
- **Stockage navigateur préfixé** (`lsKey`, `APP_NS`) : origine `github.io` partagée. Couche réseau client gardée (4G terrain).

## Pistes d'amélioration

Règle 22. Ne pas reproposer une piste écartée sans fait nouveau. **En attente** : 25/11 — **échecs intermittents du déploiement GitHub Pages** (« No artifacts named github-pages », étape `deploy-pages`). Relevé NEWGEN : 7 échecs sur les 37 derniers déploiements (15/11 → 25/11), en rafales (20/11 matin, 21/11 matin, 24/11 soir, 25/11 14:40-15:03), jamais liés à un commit. **Fait établi** : dans le run en échec `45640473832`, l'artefact existe (créé à 15:03:25) alors que `deploy-pages` l'a cherché à 15:03:26 et en a trouvé 0. Mesuré au journal du run `45660434815` : artefact finalisé à 17:04:29,01, recherché à 17:04:29,35 (0,3 s). NEWGEN a échoué 3 fois de suite à la publication du 25/11 17:00 (NextStep, mêmes minutes : succès du premier coup). **Hypothèse non vérifiée** : course côté GitHub (artefact visible avec un léger retard), cohérente avec un échec par rafales ; page d'état de GitHub inaccessible depuis cette session. **Proposé** : étape « attendre l'artefact » (interrogation de l'API jusqu'à 60 s) entre l'envoi et `deploy-pages`, dans `deploy.yml` des deux dépôts ; bénéfice : plus de relance manuelle ; charge : un petit script et un essai ; à mesurer sur les prochains déploiements. Relancer le **workflow entier** (`workflow_dispatch`), pas la seule tâche. Purge de `lectures` faite le 22/11, voir AG-066. Coupure d'urgence éprouvée sur NextStep du bac à sable le 17/11 (06:21-06:22, runs `44023528386` / `44023579101` : 3 URL en 403 puis 200).

**Écartées** (date — piste — raison)
- 25/10 — tableaux thématiques × communes et partenaires × conseillers — alourdissent la page, jugés inutiles.
- 03/10 — abonnement agenda pour les partenaires ; tri des onglets par usage ; aide « Premiers pas » — « pour l'instant ».
- 10/10 — heure pré-remplie au clic sur l'Agenda — gardée sans correction (horaires variés).
- 10/10 — progression début → fin de cycle dans les avis — « pour l'instant », à reprendre quand un cycle sera bouclé.
- 12/10 — synchroniser l'import Outlook PC ↔ téléphone — « trop compliqué » : passerait par le serveur (donnée personnelle).
- 12/10 — envoi du .ics par mail ou bouton de partage — « reste manuel » : une seule étape gagnée.
