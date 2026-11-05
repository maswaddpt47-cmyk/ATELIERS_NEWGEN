# Chantiers en cours — ATELIERS_NEWGEN

État au **05/11/2026, 10:55 (heure de Paris)**, bases `main` : NEWGEN `78ca17c`,
NextStep `7b021cd`. Dernière livraison fonctionnelle : NEWGEN `78ca17c` / NextStep `7b021cd`
(tests, déploiements et parité verts).
Tient aussi les restes communs à NextStep (même API, même base).

**Codex** : AG-041 à AG-046 confirmés (dernier `85b5d7b`), pas de nouvelle revue de ce lot.
**Autorisations ChatGPT non résolues** : le réglage GitHub « Autoriser toutes les actions »
est bien affiché, mais la capture de l'utilisateur montre encore une confirmation
`create_tree`. Cause non établie ; ne pas présenter ces confirmations comme supprimées.
Anciens brouillons Codex locaux non publiés : repartir de `main` à jour.

**À reprendre en premier** : rien d'ouvert au 05/11/2026. Essais réels (compte TS, connexion avec
code, mail d'un ticket), relecture des registres (sécurité V0.10, RGPD V0.7) et page TS : vérifiés par
l'utilisateur le 05/11/2026 (déclaré). **Adresse de la page TS toujours non diffusée** (écart RGPD n° 14 :
avis du DPO avant diffusion, dans le registre).

Fichier transitoire, à mettre à jour à chaque avancée et à supprimer une fois
soldé ; pas de la documentation permanente (`MD-LIB/hygiene-instructions.md`).

**Ménages (26/09 → 04/11/2026)** : récit dans `git log -p CHANTIERS.md`.
Contradiction : `AGORA.md` — aucun bloc ouvert au 05/11/2026 (AG-047 à AG-051 clos).

---

## En production depuis le 25/09/2026

NextStep (équipe) et NEWGEN (utilisateur) : **même API PHP chez Alwaysdata**
(compte `ateliers-numeriques`), **même base MySQL** (AG-009, bascule du 25/09).

- **Google supprimé** (classeurs 29/09, Apps Script 01/10). **Import
  verrouillé** : le lever = requête SQL délibérée (phpMyAdmin), sinon un
  second import écraserait les saisies.
- Tâches planifiées : rappels d'ateliers en retard **08:00** chaque jour
  (`api/lib/rappels.php`, interrupteur `rappels_actifs`) ; **bilan mensuel** à
  la superviseure le **5** du mois à 08:00 (`api/lib/bilan.php`, cron `0 8 5 * *`, décalé du 1er le 03/11/2026).
- Sauvegardes : natives Alwaysdata (**3 jours**) ; copie de nuit 03:00 dans
  `~/sauvegardes/` (30 j, restauration prouvée par
  `api-tests/sauvegarde.test.php`) ; copie **chiffrée** hors site 04:15
  (`ateliers-backups`, branche `copies` sans historique, `age`, 90 j,
  rattrapages 11:47 et 17:47). Corbeille et onglet Sauvegardes dans l'Admin
  (AG-014) ; **pas** de bouton de restauration complète (une session volée
  effacerait tout).
- Sécurité : jeton exigé en lecture, dans le corps POST (AG-011) ; mails
  rendus aux seuls admin/superviseur ; aucun script externe (`vendor/`,
  RGPD-17 en CI) ; seuls services tiers : API Géo et fonds OSM France ; 2FA
  GitHub et Alwaysdata ; journal 12 mois. Contrôles du code : CodeQL,
  `semgrep.yml` (non bloquant), Dependabot (ne voit pas `vendor/`), secret
  scanning, push protection.
- **Documents à diffusion restreinte**, `ateliers-backups/documents/` :
  registres sécurité et RGPD — V0.x tant que non validés, **V1 à la
  validation** (DPO/DSI), un cran par mise à jour ; archives dans
  `documents/archives/`, rendu par `documents/sources/rendre.js`. Prochaine
  révision : audit du 01/01/2027 (ACME Alwaysdata à vérifier).
- **Bac à sable** (AG-019) : https://ateliers-numeriques.alwaysdata.net/sandbox/,
  NextStep dans `sandbox/nextstep/` (AG-033) — comptes « Démo … » (« Démo
  Admin » : secret `SANDBOX_MDP_DEMO`), fausses données (`sandbox_seed.php`).
  **Depuis le 25/10 : directement sur `main` sauf demande du bac à sable.**
  Un push de code sur `main` de NEWGEN le republie (`deploy-sandbox.yml`, 27/10).

## 🔧 Parité NEWGEN/NextStep (AG-015)

**5 écarts, tous `voulu`** (dont `APP_NS`, `App`, `injectCSS`,
`VueHistorique` — navigation et design propres à chaque appli). Outils : `node scripts/parite.js
../ateliers-cd47_NextStep` (constat), `--maj` (réécrit
`scripts/parite-ecarts.json` ; ce qui a bougé repasse en `à trancher`) ;
workflow `parite.yml`, jamais bloquant. **Avant de pousser** un changement
dans un écart `voulu` : `--maj`, puis remettre son statut à `voulu` à la main.
**Ordre de push : NextStep d'abord, puis NEWGEN** (`parite.yml` refait
l'essai 10 et 20 min plus tard avant d'alerter). `scripts/githooks/pre-push`
(installé par le hook de session) refuse un push de NEWGEN si la parité n'est
pas à jour ou si NextStep a des commits non poussés.

- **Ne pas uniformiser la charte graphique** (26/09) : chaque appli garde son
  design, `VueHistorique` compris ; seuls le fonctionnement et les choix
  validés passent de l'une à l'autre.

## Reste ouvert

### Chiffres métier — contrôle restant

- Audit des chiffres métier sur export réel toujours non réalisé ; les
  vérifications des livraisons des 25 et 26/10/2026 (AG-032) utilisent des
  données fictives. Fréquence réelle des cas d'AG-032 (présents non saisis,
  durée vide, avis sans réponse) jamais mesurée : comptage en lecture sur la
  base possible.

**Nouveautés** (règle 18 du `CLAUDE.md`) : prochain `id` **38** ; jamais `admin:
true` (26/10) ; jamais 12, 16, 19, 24, 25, 26 ni 29 (publiés puis retirés).
Entrées des 03-04/10 datées du 05/10 (demande de l'utilisateur).

**Actions de l'utilisateur**

1. **GDINV2** : dossier DSI V3 (six défauts), synthèse et consigne V0.2 prêtes, **pas encore
   remises** (`GDINV2-BACKUP/dsi/GDIN-DSI_2026-10-29/`) ; suivi dans `GDINV2/CHANTIERS.md`.

**Pour Claude**

0. **Bac à sable NextStep** (AG-033, en ligne le 27/10) : à éprouver — pages
   NextStep dans les contrôles de `coupure-urgence.yml` puis coupure réelle ;
   requêtes vers `api-sandbox` vues au navigateur ; avis fictif par le QR.
1. **Supports par atelier (pptx, docx…) — mis de côté le 04/10** : supports
   sur un **réseau interne** ; une page https ne peut pas ouvrir un lien
   `file://` (hypothèse à vérifier sur un poste CD47) : il faudrait
   SharePoint/OneDrive ou un dépôt chez Alwaysdata. Bloc AGORA au choix du
   stockage (critère 1).
2. **Réimport Outlook** : un rendez-vous simple importé **avant** le 03/10 et
   déplacé depuis n'est pas reconnu (apparaît nouveau + supprimé). Options 2
   (calendrier publié) et 3 (Microsoft 365) écartées pour l'instant : DPO / DSI.
3. **Thématique « TBD »** (sans urgence) : visible dans les statistiques.

**Audit trimestriel** : routine `trig_01J6ZMsLHKbgXAQsRYgQL16q` (6 dépôts,
prochaine le 01/01/2027), créée dans l'interface : **Claude ne peut pas la
modifier** (champ « Instructions » seulement, par l'utilisateur) ; ses commits
vont sur des branches `claude/…` à fusionner dans `main` — à vérifier au 01/01.
**Rappels de l'audit Codex** (lancé à la main), push et mail à 08:52 :
`trig_012uCnnzo6MZhyWNYr6JAFqv` le 2 janvier, `trig_01XC6RefYHqVbN2Wcjz3kkXo`
le 1er avril, juillet et octobre. Copilot écarté (payant), Gemini en réserve.

## À ne pas réapprendre — Alwaysdata et déploiement

- **Coupure d'urgence** (AG-027) : Actions → `coupure-urgence.yml`, cible `tout` ou
  `bac-a-sable` (9 à 13 s) ; secours SSH : `echo 'Require all denied' > www/.htaccess`.
- **Veilleur du contrôle d'intégrité** (AG-028) : un contrôle par heure, tuile dans Admin →
  Sauvegardes ; arrêt durable : Disable workflow. **Jamais vus en vrai** : relance par le filet,
  pastille « ! ». Le jour en cours n'est pas compté (AG-029).
  **Boucle du 31/10-02/11** (3 603 échecs en 34 h) : un jour sans requête faisait échouer le
  contrôle, et chaque échec relançait un veilleur aussitôt. Corrigé (AG-039/040) : relève après
  échec avec `attente` = `intervalle`, jamais moins d'`intervalle` depuis le dernier lancement,
  contrôle en cours suivi, état relu après chaque attente, alerte sur le dernier **succès** ;
  requête-témoin dans `integrite-api.yml` (contrat de Codex : GET `logLogin`, marque neuve
  cherchée dans les journaux **bruts**, témoin absent = IMPOSSIBLE). Tests :
  `veilleur.test.js` (14), `integrite.test.js` (12), bloquants en CI. **Éprouvé en réel le
  04/11/2026** (AG-040 clos) : échec provoqué → 60 min d'attente → reprise réussie (n° 3894).
  Tuile : lit la liste `status=success` de GitHub, qui peut omettre un run récent (hypothèse
  non vérifiée) ; « Veilleur en service » tant qu'un run est en file (n° 3654, ancien code, inerte).
- **Correctif PHP : redémarrer le site** (Web → Sites ; version servie : `diagnostic-api.yml`).
  **SSH par clé seule** depuis le 21/10 (mot de passe à réactiver puis couper pour une
  intervention à la main). Un secret GitHub ne part qu'au **déploiement suivant**.
- Utilisateur MySQL **sensible à la casse** (`…_michel`) ; site dans `~/www/`, `api/lib/` fermé
  par `.htaccess` (403 vérifié à chaque déploiement).
- Identifiants : Secrets GitHub, écrits par `deploy-api.yml` dans
  `~/config-api.php` (hors `~/www/`, droits 600). Claude ne les voit jamais et
  ne joint pas Alwaysdata : son œil sur le serveur est le workflow manuel
  **« Diagnostic API Alwaysdata »** (bac à sable : journal de `deploy-sandbox.yml`).
- **Dates « du jour » : `date('Y-m-d')` côté PHP, jamais `CURDATE()`** (MySQL peut être en UTC) ;
  `NOW()` restant ailleurs (corbeille, journal, sessions) : même piège possible, non traité.
- **Poste pro de l'utilisateur : pas d'invite de commandes** : terminal web d'Alwaysdata (SSH « par
  le Web »), une commande courte à la fois, sans `~` ; déchiffrer une copie exige un PC perso.
- **Tests PHP en session** : MariaDB s'arrête parfois (`Connection refused`) ;
  `service mariadb start`, mot de passe root `testmdp` (script de démarrage « Micky »).

## Décisions de l'utilisateur à ne pas « corriger »

- **Origine web partagée avec GDINV2 : séparation ABANDONNÉE le 04/11/2026** (utilisateur :
  « on laisse tomber, ne plus me la rappeler »). Essai : Alwaysdata refuse les sous-adresses de
  `alwaysdata.net` (« ce domaine ne vous appartient pas ») ; second compte, adresse de la DSI ou
  domaine acheté écartés. Risque accepté (registre de sécurité V0.10 §9). **Ne plus le proposer**,
  sauf fait nouveau (faille démontrée sur l'origine, adresse fournie par le Département).
- **Planning et Calendrier (04/11/2026)** : on garde l'existant (Calendrier à côté de la vue
  Mois), sujet sorti des chantiers. Sur téléphone, seuls lundi et mardi tiennent à l'écran.
- **Fiches bilan** : interrupteur « 📋 Fiches bilan vides » (Admin → Listes →
  Conseillers) éteint le 27/10, **rallumé le 28/10/2026**, équipe prévenue par
  une démonstration le même jour ; ateliers datés du 01/11/2026 ou après seulement. Fenêtre au passage en « Réalisé » (volet,
  formulaire) : n'annonce le mail que s'il partira (`rappelFichePrevu`).
- **Bilan mensuel le 5 du mois** (03/11/2026, cron `0 8 5 * *` déclaré par l'utilisateur,
  non vérifié) : premier envoi le 05/12 ; « planifiés en <mois> » garde tout le mois (le 5, peut
  compter des ateliers du 1er au 4 restés « Planifié ») — bloc AGORA critère 7 si cela gêne.
- **Écarté du CR « option 1 »** : PWA (AG-012), nouveau modèle de données,
  PHPWord/TCPDF.
- **Plus de démarche auprès de la DPD ni des Archives départementales
  (16/10/2026)** : ni mail, ni tableau de gestion, ni visa d'élimination. Les
  écarts du registre RGPD (V0.7 : n° 1, 2, 3, 5, 6, 7, 9 à 14) restent ouverts
  dans le registre. **Mention d'information des agents (écart n° 3) : classée sans suite
  le 04/11/2026** (utilisateur) — ne pas la reproposer sans fait nouveau.
- **Risques acceptés (audit du 01/10, décidé le 02/10)** :
  `StrictHostKeyChecking=accept-new` ; blocage de 15 min après 5 échecs par
  compte, sans compter l'IP ; SheetJS 0.18.5 (failles à la lecture seulement,
  l'appli n'écrit que — **rouvrir** si un import `.xlsx` apparaît côté navigateur).
- **Mots de passe (06/10)** : l'actuel est exigé pour changer le sien, sauf
  provisoire (AG-023, option 1) ; l'Admin envoie un lien par mail
  (`envoyerLienReinit`), le provisoire n'est qu'un secours. **Pas fait, par
  choix** : provisoire imposé par l'API (audit Codex n° 6).
- **Interrupteur « login »** (Listes → Conseillers) = accès à l'**Admin**
  seulement ; couper un agent = supprimer son compte. Noms à accès Admin
  lisibles sans connexion : accepté (24/09). Le **superviseur** garde ses
  pouvoirs quasi admin et n'apparaît pas dans la liste de connexion d'Index.
- **Déconnexion d'Index après 30 min** d'inactivité, jamais pendant une
  saisie ; la mémoire de saisie entre onglets (25/10) ne change pas le délai.
- **Interfaces** : volet `PanneauAtelier` commun ; filtre public de l'Historique à choix
  multiples ; « Effacer » vide tout ; frise à une ligne par conseiller ; « ⚠️ ordi » clignotant ;
  chevron « › » de NEWGEN (écart voulu).
- **Avis des stagiaires (04-06/10)** : anonymes, sans âge ni recontact sans le DPO ; un avis par
  appareil, plafond = présents (à défaut inscrits) ; réservés à l'animateur et au co-animateur (un
  agent peut se déclarer co-animateur, écart n° 11 gardé) ; sous **3 avis** un conseiller ne voit
  que leur nombre (`AVIS_SEUIL`) ; suppression par l'Admin ; export CSV remarques comprises (hors
  purge à 24 mois, formules neutralisées).
- **Côté conseillers (04/10)** : « Mes bilans » limité aux ateliers animés ou co-animés, masquable
  (Admin → Visibilité) ; pas de satisfaction par conseiller (question RH).

## ⚠️ Pièges connus

- **Textes « récupérable 30 jours dans la Corbeille »** (Nouveautés 9 et 15,
  confirmation de suppression multiple) : vrais tant que la Corbeille est
  ouverte dans Admin → Visibilité. Si on la referme, les revoir.
- **Page HTML en cache sur téléphone** : `index.html` n'est pas versionné ;
  tant que l'ancien reste en cache, il charge les anciens scripts. Avant de
  chercher un bug après livraison : faire recharger ou effacer les données du
  site.
- **« Ordinateurs prêtés » vidé au premier enregistrement** (signalé le
  23/09, non reproduit, cause inconnue). Hypothèse non vérifiée : molette de
  la souris sur le champ numérique encore actif. Si ça revient : demander si
  le champ affiche vide ou « 0 ».
- **Plafond d'avis = présents, à défaut inscrits** : le formulaire propose 4
  inscrits par défaut ; sans mise à jour des présents, le 5ᵉ stagiaire est
  refusé (rappelé dans la fenêtre du QR et la Nouveauté 28).

## Points à ne pas défaire

Déjà dans `CLAUDE.md` §4-5 et verrouillés par les tests, non répétés ici :
`_id` client gardé jusqu'au succès, écritures jamais doublées, plafonds, aucun
appel superflu, `sw.js` de désinscription.

- **Bilan trimestriel (AG-032, validé à l'écran le 26/10/2026)** :
  participations sur les seuls ateliers à présents saisis, couverture
  affichée ; durée vide = 1 h 30, ateliers concernés comptés ; avis en % de
  tous les avis avec « Sans réponse » ; communes normalisées ; année non
  chargée = message, aucun chiffre, impression désactivée (détail : `AGORA.md`).
- **Sujets d'avis multiples (AG-035)** : % des avis ; `bilanAvis` exige `format=2`.
- **Bilans (AG-037/038)** : un onglet « 📈 Bilans » Mois / Trimestre, calcul `bilanPeriode` commun écran/impression/mail ; bilan à date + « À venir » sans estimation ; co-animé compté pour les deux ; mail : mois écoulés seulement.
- **Journal de connexion (AG-036)** : lignes Admin réussies écartées par l'API avant la limite ; superviseur visible ; `getUsageOnglets` non journalisé (purge non tracée, actée).
- **Unité et couverture (25/10)** : participations = présences cumulées,
  jamais personnes uniques. Taux seulement sur les ateliers réalisés avec
  inscrits > 0 et présence saisie ; sans base : « — » ; une présence saisie à
  zéro reste un vrai zéro. **Analyse** : un seul donut AM/PM ; pas de seuil de
  70 % ni d'objectif sans décision métier documentée.
- **Saisie (25/10)** : formulaire, cycle et préparatifs Outlook restent en
  mémoire entre onglets, jamais stockés ni envoyés en plus ; succès ou fin de
  session les efface, fermeture/rechargement aussi (avec avertissement) ; les
  identifiants d'une écriture non confirmée restent stables à la reprise.
- **Graphiques communs (24/10)** : Liquid Glass validé ; Activité mensuelle
  janvier → décembre, années côte à côte, couleurs réservées aux statuts,
  repères d'année visibles même sans données ; l'habillage ne touche ni
  valeurs, ni échelles, ni filtres, ni échappement HTML.
- **Démonstration (24/10)** : faux ateliers sur deux années, liens du bandeau ;
  aucun peuplement sans le garde-fou `bac_a_sable=true`.
- **Tickets modifiables (AG-041, confirmé par Codex, validé à l'écran le 04/11)** : toute
  l'équipe, champs saisis, tant que le statut est « Nouveau » ; ni mention, ni mail, ni journal.
- **Page TS et tickets (AG-042 à AG-046, confirmés par Codex le 04/11)** : jeton TS limité à ses
  actions ; code par mail sauf première connexion (jamais pour un « oubli », même promu, ni
  au-delà de 30 jours, ni pour un compte d'avant le 04/11) ; rattrapages uniques
  (`ts_rattrapage_unique`) ; tickets : condition sur l'ancien état en `BINARY` pour tout appel,
  « Réponse de » = répondant enregistré, chaque « pas de mail » dit sa raison.
- **Corbeille** : un conseiller ne voit que ses ateliers animés ou co-animés
  (AG-034) ; l'Admin voit tout, sans suivre le sélecteur de conseiller (choix du
  27/10) ; purge définitive réservée admin/superviseur (AG-024). Testés.
- **Matériel** : seul le stock d'ordinateurs compte (« Classe mobile » = ordinateurs du stock),
  à l'heure près (AG-022) ; un conseiller compte une fois, au max de ses quantités : les séances
  de l'après-midi puisent dans les ordinateurs du matin (5 + 2 + 2 = 5), repère « ↳ 2 » sur la
  frise ; prêt sur plusieurs jours = journées entières (AG-002) ; sans dates de prêt, date de
  l'atelier (`logic.test.js`). **Rouvrir** seulement sur une alerte d'un créneau réellement libre.
- **Ouvert à tous (AG-047, clos le 04/11/2026)** : `Oui` / `Non` obligatoire à la création (API : refus
  « rechargez » sans le champ, modification sans lui = valeur gardée, Corbeille d'avant = `Non`) ; colonne
  posée dans `api_action_protegee`, avant toute transaction ; filtre de l'Historique appliqué comme les autres
  filtres, **tuiles et compteurs compris** (AG-048, décision de l'utilisateur du 04/11/2026, confirmé par Codex) ;
  le filtre de statut reste hors des tuiles (répartition des statuts, choix de l'utilisateur du 05/11/2026) ;
  filtre TS seulement si `ts_filtre_ouvert` est allumé (absent = éteint).
- **Page TS, compte désactivé (AG-049, 05/11/2026)** : « Votre accès a été désactivé… » seulement avec le
  **bon** mot de passe ; mauvais mot de passe ou adresse inconnue = message commun (pas d'énumération).
  « Mot de passe oublié » d'un compte désactivé reste silencieux.
- **Admin → Admin (05/11/2026)** : sections repliables par clic sur le titre (`admin-replie`, attribut
  `data-ouvert`), fermées à l'arrivée, ouvertes mémorisées par navigateur (`admin_sections_ouvertes`).
- **Provisoire TS créé par l'Admin (AG-050)** : envoyé par mail à l'adresse du compte, jamais affiché sauf si le
  mail n'a pas pu partir (l'écran le dit) ; **jamais relancé** après une réponse perdue (`GAS_SANS_REPRISE`, une tentative).
- **Planning, vue Mois (AG-051, 05/11/2026)** : une demi-journée ne montre que ses ateliers actifs
  (`ateliersDemiCase`) ; annulés, non réalisés, reportés n'y apparaissent jamais, case vide s'il n'y a qu'eux
  (décision de l'utilisateur) ; ils restent dans l'Historique et la vue Semaine.
- **Statut groupé et .ics d'annulation (05/11/2026)** : barre de sélection de l'Historique, un `saveMany` ;
  « Annulé » propose un .ics `METHOD:CANCEL` aux mêmes UID (`<_id>@ateliers-newgen`) que les .ics de création.
  Prise en compte par Outlook classique **non vérifiée** au moment de la livraison (essai sur un atelier).
- **Stockage navigateur préfixé** (`lsKey`, `APP_NS` `newgen` / `nextstep`) :
  origine `maswaddpt47-cmyk.github.io` partagée, `localStorage` non cloisonné
  par chemin. **Couche réseau client gardée** : elle sert sur une vraie
  coupure (4G terrain).

## Pistes d'amélioration

Règle 20 du `CLAUDE.md`. Ne pas reproposer une piste écartée sans fait nouveau.

**Proposées, en attente** : aucune (26/10/2026 ; « un bilan par conseiller » retenu et fait).

**Écartées** (date — piste — raison)
- 25/10/2026 — Tableaux thématiques × communes et partenaires × conseillers — retirés à la demande de l'utilisateur : alourdissent la page, jugés inutiles. Ne pas les reproposer sans fait nouveau.
- 03/10/2026 — lien d'abonnement agenda pour les partenaires — « pour l'instant » (utilisateur).
- 03/10/2026 — tri des onglets d'après les compteurs d'usage — « pour l'instant ».
- 03/10/2026 — aide « Premiers pas » pour un nouvel arrivant — « pour l'instant ».
- 10/10/2026 — heure pré-remplie au clic sur un créneau de l'Agenda — une heure par défaut risque d'être gardée sans correction (horaires variés).
- 10/10/2026 — progression début → fin de cycle dans les avis — « pour l'instant » : un seul diagnostic avec avis (05/10) ; à reprendre quand un cycle sera bouclé.
- 12/10/2026 — synchroniser l'import Outlook entre PC et téléphone (à la façon de la sauvegarde SpeedDial) — « trop compliqué » (utilisateur) : la voie propre passe par le serveur, donc nouvelle donnée personnelle (registres, DPO, purge à viser) et bloc AGORA ; le .ics se réimporte déjà sur le téléphone.
- 12/10/2026 — envoi du .ics par mail à chaque import (serveur) ou bouton de partage (menu de l'appareil) — « reste manuel » (utilisateur) : gain d'une seule étape, le téléphone doit toujours importer le fichier.
