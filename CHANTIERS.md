# Chantiers en cours — ATELIERS_NEWGEN

État au **27/10/2026**. Tient aussi les restes communs à NextStep (même API,
même base depuis la bascule du 25/09/2026).
Fichier transitoire : à mettre à jour à chaque avancée, à supprimer quand tout
est soldé. Ce n'est pas de la documentation permanente (cf.
`MD-LIB/hygiene-instructions.md`).

**Ménages des 26/09, 02/10, 04/10, 06/10, 07/10, 16/10 et 26/10/2026** :
époque GAS, récit de la parité, livraisons, audit Codex et ses corrections,
récit de la faille PHP2Shell et des essais AG-027 à AG-029, décisions déjà
verrouillées par les tests retirés — `git log -p CHANTIERS.md`.
Contradiction : `AGORA.md` (section 8 du `CLAUDE.md`) — **aucun bloc ouvert au
27/10/2026** (AG-035 sorti ce jour).

---

## En production depuis le 25/09/2026

NextStep (équipe) et NEWGEN (utilisateur) : **même API PHP chez Alwaysdata**
(compte `ateliers-numeriques`), **même base MySQL** (AG-009, bascule du 25/09).

- **Google supprimé** (classeurs 29/09, Apps Script 01/10). **Import
  verrouillé** : le lever = requête SQL délibérée (phpMyAdmin), sinon un
  second import écraserait les saisies.
- Tâches planifiées : rappels d'ateliers en retard **08:00** chaque jour
  (`api/lib/rappels.php`, interrupteur `rappels_actifs`) ; **bilan mensuel** à
  la superviseure le 1er du mois à 08:00 (`api/lib/bilan.php`).
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
  Depuis le 27/10, chaque push de code sur `main` de NEWGEN republie aussi le
  bac à sable (`deploy-sandbox.yml`) ; un push NextStep seul : le lancer à la main.

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

**Nouveautés** (règle 18 du `CLAUDE.md`) : prochain `id` **36** ; jamais
`admin: true` (26/10) ; jamais 12, 16, 19, 24, 25, 26 ni 29 (publiés puis
retirés). Les entrées des 03-04/10 sont datées du 05/10 à la demande de
l'utilisateur.

**Actions de l'utilisateur**

1. **Faille PHP2Shell (21/10/2026)** — API rouverte le 21/10 à 21 h 05 ;
   aucun indice identifié, sans pouvoir exclure une compromission (AG-026).
   Reste :
   - en parler au directeur : remettre le registre de sécurité V0.7 et
     l'annexe A **V0.3** (`ateliers-backups/documents/`, 22/10), après avoir
     **validé la V0.3** (place laissée aux IA, ligne « relecture humaine du
     code à proposer à la direction ») ; la V0.2 (8 pages) reste archivée
     comme dossier de preuves ;
   - à arbitrer : requête-témoin pour une vraie fraîcheur des journaux
     (évolution laissée de côté par AG-029).
2. **Auditer GDINV2 pour la XSS** (même origine web que NEWGEN, 94 insertions
   `innerHTML` alimentées par un Excel importé) — dans son dépôt, avec Codex.
   NEWGEN et NextStep : rien d'exploitable (AG-030, AG-031, registre V0.8 §10).
3. **Rendez-vous DSI (préparé le 06/10)** : réponses dans
   `ateliers-backups/documents/sources/preparation-rdv-dsi.md` ; à remettre :
   note avant/après V0.1 et registres V0.6. Avant : vérifier les deux « ⚠️ »
   du document (compte transférable, alerte d'intrusion).
4. **01/11/2026** : premier envoi réel du bilan mensuel — le vérifier dans le
   journal Admin (« bilanMensuel »).
5. **Depuis le PC** : coller `MD-LIB/outils/script-demarrage.sh` dans le
   Setup script de l'environnement (menu de l'environnement → Edit). Il
   installe le contrôle automatique du déploiement (`MD-LIB/deploiement.md`) ;
   d'ici là, le lancer à la main après chaque push.

**Pour Claude**

0. **Bac à sable NextStep** (AG-033, en ligne le 27/10) : à éprouver — pages
   NextStep dans les contrôles de `coupure-urgence.yml` puis coupure réelle ;
   requêtes vers `api-sandbox` vues au navigateur ; avis fictif par le QR.
1. **Planning en essai** (depuis le 03/10, à côté de l'Agenda) : relecture
   des compteurs d'onglets (Admin → Connexions) prévue le 20/10, **pas encore
   faite** ; l'utilisateur veut retirer l'Agenda (le Calendrier est coupé
   matin / après-midi comme lui depuis le 13/10). Reste à trancher si le
   Calendrier garde sa place à côté de la vue Mois (par défaut). Fait du
   07/10 : sur téléphone, seuls lundi et mardi tiennent à l'écran, le reste
   se trouve en faisant défiler — l'utilisateur a cru un atelier absent.
2. **Mention d'information RGPD des agents** : texte proposé le 03/10, **mis
   en pause par l'utilisateur** — ne pas relancer sans sa demande.
3. **Supports par atelier (pptx, docx…) — mis de côté le 04/10** : supports
   sur un **réseau interne** ; une page https ne peut pas ouvrir un lien
   `file://` (hypothèse à vérifier sur un poste CD47) : il faudrait
   SharePoint/OneDrive ou un dépôt chez Alwaysdata. Bloc AGORA au choix du
   stockage (critère 1).
4. **Réimport Outlook** : un rendez-vous simple importé **avant** le 03/10 et
   déplacé depuis n'est pas reconnu (apparaît nouveau + supprimé). Options 2
   (calendrier publié) et 3 (Microsoft 365) écartées pour l'instant : DPO / DSI.
5. **Thématique « TBD »** (sans urgence) : visible dans les statistiques.

**Audit trimestriel** : routine `trig_01J6ZMsLHKbgXAQsRYgQL16q` (6 dépôts,
prochaine le 01/01/2027), créée dans l'interface : **Claude ne peut pas la
modifier** (champ « Instructions » seulement, par l'utilisateur) ; ses commits
vont sur des branches `claude/…` à fusionner dans `main` — à vérifier au 01/01.
**Rappels de l'audit Codex** (lancé à la main), push et mail à 08:52 :
`trig_012uCnnzo6MZhyWNYr6JAFqv` le 2 janvier, `trig_01XC6RefYHqVbN2Wcjz3kkXo`
le 1er avril, juillet et octobre. Copilot écarté (payant), Gemini en réserve.

## À ne pas réapprendre — Alwaysdata et déploiement

- **Coupure d'urgence** (AG-027) : Actions → `coupure-urgence.yml`, « couper »
  / « rouvrir », cible `tout` ou `bac-a-sable`, depuis le téléphone (9 à 13 s,
  essai réel du 21/10). Secours SSH : `echo 'Require all denied' > www/.htaccess`
  (403), `rm` pour rouvrir.
- **Veilleur du contrôle d'intégrité** (AG-028, en service depuis le 23/10) :
  un contrôle par heure, tuile « Contrôle d'intégrité » dans Admin →
  Sauvegardes. Arrêt durable : Actions → Veilleur intégrité → Disable
  workflow. **Jamais vus en vrai** : la relance par le filet, la pastille
  rouge « ! ». Le jour en cours n'est pas compté (AG-029).
- **Correctif PHP d'Alwaysdata : redémarrer le site** (Web → Sites →
  Redémarrer ; 21/10, `php -v` en SSH disait 8.4.27, l'API tournait en
  8.4.26). Version servie : `diagnostic-api.yml` (« php (web) »).
- **SSH par clé seule depuis le 21/10/2026** : les workflows passent par clé ;
  pour une intervention à la main, réactiver le mot de passe puis le
  désactiver (procédure de restauration V0.3, section 3).
- Un secret GitHub ne part au serveur qu'au **déploiement suivant** : après
  toute modification, relancer « Déploiement API Alwaysdata ».
- Utilisateur MySQL **sensible à la casse** (`…_michel`). Hôtes
  `mysql-<compte>.alwaysdata.net`, `ssh-<compte>.alwaysdata.net`, site dans
  `~/www/` ; `api/lib/` fermé par `.htaccess` (403 vérifié à chaque déploiement).
- Identifiants : Secrets GitHub, écrits par `deploy-api.yml` dans
  `~/config-api.php` (hors `~/www/`, droits 600). Claude ne les voit jamais et
  ne joint pas Alwaysdata : son œil sur le serveur est le workflow manuel
  **« Diagnostic API Alwaysdata »** (bac à sable : journal de `deploy-sandbox.yml`).
- **Dates « du jour » : `date('Y-m-d')` côté PHP (heure de Paris), jamais
  `CURDATE()`** (MySQL peut être en UTC, `08e4f8b`). `NOW()` restant ailleurs
  (corbeille, journal, sessions) : même piège possible, non traité.
- **Poste pro de l'utilisateur : pas d'invite de commandes** : tout passe par
  le **terminal web** d'Alwaysdata (Accès distant → SSH → « par le Web »,
  identifiant `ateliers-numeriques`). Une commande courte à la fois, **sans
  `~`**, majuscules exactes. Déchiffrer une copie hors site exige un PC perso.
- **Tests PHP en session** : MariaDB s'arrête parfois (`Connection refused`) ;
  `service mariadb start`, mot de passe root `root`.

## Décisions de l'utilisateur à ne pas « corriger »

- **Fiches bilan** : interrupteur « 📋 Fiches bilan vides » (Admin → Listes →
  Conseillers) éteint le 27/10, **rallumé le 28/10/2026** par l'utilisateur ;
  seuls les ateliers datés du 01/11/2026 ou après sont rappelés. Fenêtre au passage en « Réalisé » (volet,
  formulaire) : n'annonce le mail que s'il partira (`rappelFichePrevu`).
- **Écarté du CR « option 1 »** : PWA (AG-012), nouveau modèle de données,
  PHPWord/TCPDF.
- **Plus de démarche auprès de la DPD ni des Archives départementales
  (16/10/2026)** : ni mail, ni tableau de gestion, ni visa d'élimination. Les
  écarts du registre RGPD V0.6 (n° 1, 2, 3, 5, 6, 7, 9 à 11) restent ouverts
  dans le registre.
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
- **Interfaces (26/09)**, à ne pas défaire : volet latéral commun
  `PanneauAtelier` (alerte de conflit non bloquante) ; filtre public de
  l'Historique à choix multiples, « Tout afficher » ; « Effacer » vide tout,
  statut compris ; liste de connexion Admin limitée aux comptes à accès Admin.
- **Vues (05-06/10, sans annonce)** : frise à une ligne par conseiller ;
  `ChoixDate` compact ; « ⚠️ ordi » clignotant ; « PIX » dans la fiche bilan ;
  chevron « › » de la barre du bas de NEWGEN (`majIndiceNav`, écart voulu).
- **Avis des stagiaires (04-06/10)** : anonymes, sans âge ni recontact tant que
  le DPO n'a pas validé ; un avis par appareil, pas plus que de présents (à
  défaut d'inscrits) ; réservés à l'animateur et au co-animateur (filtre API),
  un agent peut se déclarer co-animateur (écart n° 11, gardé) ; sous **3 avis**
  un conseiller ne voit que leur nombre, l'Admin voit tout (`AVIS_SEUIL`) ;
  suppression par l'Admin seulement ; envois simultanés acceptés. Avis par
  atelier : colonne Partenaire, réponses en détail, export CSV remarques
  comprises (⚠️ hors purge à 24 mois ; formules neutralisées).
- **Côté conseillers (04/10)** : Stats = Synthèse et Analyse ; « Mes bilans »
  (avis par atelier, bilans mensuel et trimestriel) limité aux ateliers animés
  ou co-animés, masquable dans Admin → Visibilité. Pas de satisfaction par
  conseiller dans le bilan trimestriel (question RH).

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
- **Corbeille** : un conseiller ne voit que ses ateliers animés ou co-animés
  (AG-034) ; l'Admin voit tout, sans suivre le sélecteur de conseiller (choix du
  27/10) ; purge définitive réservée admin/superviseur (AG-024). Testés.
- **Matériel** : `periodePretMateriel` retombe sur la date de l'atelier sans
  dates de prêt (22/09). Seul le stock d'ordinateurs compte (05/10 : « Classe
  mobile » = ordinateurs du stock), à l'heure près (AG-022) : prêt d'un jour
  sur `[début, fin + 30 min)`, atelier sans horaire sur sa demi-journée ou la
  journée ; un conseiller compte une fois, au max de ses quantités
  (`logic.test.js`). Prêt sur plusieurs jours : journées entières, du
  prélèvement à la veille du retour (AG-002) — **rouvrir** seulement si une
  alerte sonne sur un créneau réellement libre.
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
