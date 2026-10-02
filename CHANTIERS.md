# Chantiers en cours — ATELIERS_NEWGEN

État au **02/10/2026**. Tient aussi les restes communs à NextStep (même API,
même base depuis la bascule du 25/09/2026).
Fichier transitoire : à mettre à jour à chaque avancée, à supprimer quand tout
est soldé. Ce n'est pas de la documentation permanente (cf.
`MD-LIB/hygiene-instructions.md`).

**Ménages du 26/09 et du 02/10/2026** : époque GAS, récit de la parité,
livraisons et décisions déjà verrouillées par les tests retirés — `git log -p
CHANTIERS.md`. Contradiction d'une proposition par une autre session :
`AGORA.md` (section 8 du `CLAUDE.md`) — **aucun bloc ouvert au 27/09/2026**
(AG-015 sorti le 27/09, `b73e86d`).

---

## En production depuis le 25/09/2026

NextStep (équipe) et NEWGEN (utilisateur) parlent à la **même API PHP chez
Alwaysdata**, compte `ateliers-numeriques`, **même base MySQL**. Refonte
décidée le 23/09 (AG-009), bascule faite le 25/09 au matin, chantier clos
par l'utilisateur le 25/09.

- **Google entièrement supprimé** : classeurs (29/09), projets Apps Script
  (01/10, par l'utilisateur). Code archivé : NEWGEN `3da2257:gas/`, NextStep
  `b8b96c7:gas/`. **Import verrouillé** : le lever = requête SQL délibérée
  (phpMyAdmin), sinon un second import écraserait les saisies.
- Rappels d'ateliers en retard : tâche planifiée **08:00** chaque jour
  (`api/lib/rappels.php`), interrupteur individuel `rappels_actifs` respecté.
- « Mot de passe oublié » par mail (`api/lib/reinit.php`, AG-013), reçu en
  boîte de réception sur `@lotetgaronne.fr` et Gmail.
- Sauvegardes, trois niveaux : natives Alwaysdata (**3 jours**, offre Free) ;
  copie de nuit 03:00 dans `~/sauvegardes/` (30 j, restauration prouvée par
  `api-tests/sauvegarde.test.php`) ; copie **chiffrée** hors site 04:15 dans
  le dépôt privé `ateliers-backups`, **branche `copies` sans historique**
  depuis le 30/09 (`age`, 90 j réels, déchiffrement testé par
  l'utilisateur ; rattrapages 11:47 et 17:47 car GitHub ne tient pas l'heure).
  Corbeille et onglet Sauvegardes dans l'Admin (AG-014) ; **pas** de bouton de
  restauration complète (une session volée effacerait tout). Procédure de
  restauration v3.2 : `ateliers-backups/documents/`.
- Sécurité : jeton exigé en lecture, dans le corps POST (AG-011) ; jeton
  annulé à la déconnexion (`logout`) ; adresses mail rendues aux seuls
  admin/superviseur ; aucune ressource externe (`vendor/`, test RGPD-17 en
  CI) ; HTTPS forcé ; 2FA GitHub et Alwaysdata ; journal conservé 12 mois ;
  documents à diffusion restreinte, **rangés dans le dépôt privé
  `ateliers-backups/documents/`** (sources et v1.0 archivées) : registre de sécurité
  v3.2, registre RGPD v1.3 (art. 30) et procédure de restauration v3.2
  (30/09/2026). Leurs écarts RGPD ouverts —
  compte personnel sans DPA, durée de conservation des ateliers non fixée,
  mention d'information des agents, procédure de sortie — sont à porter au
  DPO par l'utilisateur.
- Plus de PWA (AG-012) : `sw.js` de désinstallation publié sans date de fin.

## 🔧 Parité NEWGEN/NextStep (AG-015, tranché le 26/09/2026)

**État au 26/09/2026 : 4 écarts, tous `voulu`** (`APP_NS`, `App`,
`injectCSS`, `VueHistorique` — navigation et design propres à chaque appli). Le récit des lots
(80 → 4 écarts) est dans `git log`. Outils : `node scripts/parite.js
../ateliers-cd47_NextStep` (constat), `--maj` (réécrit
`scripts/parite-ecarts.json` ; ce qui a bougé repasse en `à trancher`) ;
workflow `parite.yml`, jamais bloquant. **Avant de pousser** un changement
dans un écart `voulu` : `--maj`, puis remettre son statut à `voulu` à la main
(oublié le 26/09 et le 28/09 → mails d'échec de `parite.yml`). **Ordre de
push : NextStep d'abord, puis NEWGEN.** Depuis le 28/09, `scripts/githooks/pre-push`
(installé par le hook de session) **refuse** un push de NEWGEN sur `main` si
la parité n'est pas à jour, si NextStep a des commits non poussés ou s'il est
en retard ; NextStep absent à côté → simple avertissement.

- **Garde-fou (utilisateur, 26/09/2026) : ne pas uniformiser la charte
  graphique.** Chaque appli garde son design ; seuls le fonctionnement et les
  choix explicitement validés passent de l'une à l'autre.
- **`VueHistorique` : chacun garde son design** (décision de l'utilisateur,
  26/09/2026, passé en `voulu`) — NEWGEN en v2, NextStep avec ses filtres
  repliables au modèle de NEWGEN. Le fonctionnement reste commun
  (`PanneauAtelier`, tri, tuiles, filtres) : toute évolution fonctionnelle de
  l'Historique se fait dans les deux.

## Reste ouvert (état du 02/10/2026, par priorité)

Faits les 01-02/10/2026 (détail dans `git log`) : lot 1 UX ; XSS des
info-bulles/popups et cache des ateliers corrigés ; RGPD-17 étendu ;
saisie par cycle : ⧉ dupliquer une séance, encart « Périodicité » (hebdo,
mensuel « 2e mardi », fériés sautés, thématique « TBD » par défaut), import
Outlook (.ics) ; rubrique **Nouveautés** (règle 18 de `CLAUDE.md` : une
entrée par changement visible, **seulement après test de l'utilisateur**).
Import Outlook **validé par l'utilisateur le 02/10** sur un vrai export
Outlook 16 du PC pro (séries hebdo, `TZID`, `SUMMARY;LANGUAGE=fr`) ; thématique
toujours « TBD », orienteur saisi à la main (son choix) ; Nouveautés `id` 7.
Périodicité **testée et validée par l'utilisateur le 02/10**, Nouveautés
`id` 8 ; titres de groupe vides masqués dans la
barre latérale NextStep. GDINV2 et SMS-mail : leurs propres CHANTIERS.

**Actions de l'utilisateur**

1. **Relecture finale des registres** (sécurité v3.2, RGPD v1.3, procédure
   v3.2 ; `ateliers-backups/documents/`) puis, avant transmission DSI/DPO,
   vérifier sur pièce : §7 Alwaysdata (« à confirmer »), références
   CNIL/ANSSI (recoupées par sources secondaires), GitHub sur la liste DPF.
2. **Écarts RGPD à porter au DPO** : compte Alwaysdata personnel sans DPA,
   durée de conservation des ateliers non fixée, mention d'information des
   agents, procédure de sortie.
3. **Lot 2 UX, à valider avec un ou deux conseillers** : accueil (bandeau
   « à mettre à jour » cliquable avant les chiffres, tuiles compactes sur
   téléphone, « Planifiés 100 % » qui compte les ateliers en retard),
   boutons techniques des filtres (XLSX, ICS, Sync), ordre des champs
   (thématique en bas), icônes ↩ 🚪 de l'en-tête téléphone. Lot 1 (en ligne
   le 01/10) : à regarder sur téléphone.

5. **Mail d'échec Apps Script** (01/10) : échec du 30/09, d'avant la
   suppression. Échec daté ≥ 01/10 → `script.google.com/home/triggers` + corbeille Drive.

**Pour Claude**

6. **Registre de sécurité, après v3.3** (v3.3 et RGPD v1.4 faits le 02/10,
   `ateliers-backups/documents/`) — **attendre le go de l'utilisateur** :
   §8.5 ne cite que l'audit du 24/09 et la routine trimestrielle ; y porter
   les audits faits en session à la demande de Claude. Relevés dans `git log`
   au 02/10 : 24/09 (avant production, `ca04a64`) ; 01/10 en session
   (`d680fb4` : XSS, cache navigateur, accès aux ateliers des autres) ;
   01/10 routine (`68bb701` : `admin_v14.html`, GDINV2, mineurs ; ACME
   Alwaysdata non vérifiable, site bloqué depuis la session). Revérifier
   la liste avant d'écrire.

**Sans urgence**

7. **Usage des onglets** (depuis le 29/09) : regarder Admin → Connexions
   dans quelques semaines pour décider des onglets à simplifier (lot 2).
8. **Pistes Outlook discutées, non lancées** (02/10) : durée des ateliers
   exportés en `.ics` fixée à 1 h (`exportICS`, `shared.js`) — 2 h ou
   réglable à trancher ; import des disponibilités (« Disponibilité
   uniquement ») pour griser les créneaux occupés dans l'Agenda, lu dans le
   navigateur seulement — à proposer si l'import des ateliers ne suffit pas.
9. **Thématique « TBD »** : visible telle quelle dans les statistiques
   tant qu'elle n'est pas remplacée — à surveiller.

**Audit trimestriel** : routine `trig_01J6ZMsLHKbgXAQsRYgQL16q` (6 dépôts,
sans connecteur, prochaine exécution le 01/01/2027). Créée dans
l'interface : **Claude ne peut pas la modifier**, seul le champ
« Instructions » est modifiable, par l'utilisateur. Notification push ;
e-mail probable via les réglages du compte, non vérifié. Ses commits vont
sur des branches `claude/…` : la consigne demande de les fusionner dans
`main`, à vérifier au 01/01.

Hors liste, choix assumé : les noms `GAS_*`/`gasAppel`/`__gasLog` (~120
occurrences, verrouillées par les tests réseau) restent, ils désignent la
couche d'appel.

## À ne pas réapprendre — Alwaysdata et déploiement

- Un secret GitHub ne part au serveur qu'au **déploiement suivant** : après
  toute modification de secret, relancer « Déploiement API Alwaysdata ».
- L'utilisateur MySQL est **sensible à la casse** (`…_michel`, pas
  `…_Michel`). Hôte `mysql-<compte>.alwaysdata.net`, SSH
  `ssh-<compte>.alwaysdata.net`, site servi depuis `~/www/`.
- Identifiants MySQL et clé d'import : Secrets GitHub, écrits par
  `deploy-api.yml` dans `~/config-api.php` (hors `~/www/`, droits 600).
  Claude ne les voit jamais et ne joint pas Alwaysdata depuis son
  environnement : le workflow manuel **« Diagnostic API Alwaysdata »** est son
  œil sur le serveur (version PHP, extensions, config, codes HTTP).
- `api/lib/` est fermé par `.htaccess` (HTTP 403 vérifié).
- **Dates « du jour » : toujours `date('Y-m-d')` côté PHP (heure de Paris,
  `base.php`), jamais `CURDATE()`** : le serveur MySQL peut être en UTC ; entre
  minuit et 2 h à Paris les deux diffèrent (déploiement du 30/09 à 00:09 bloqué
  par `rappels.test.php`, corrigé `08e4f8b`). `NOW()` restant ailleurs
  (corbeille, journal, sessions) : même piège possible, non traité.
- **Poste pro de l'utilisateur : pas d'invite de commandes** (29/09). Toute
  opération serveur passe par le **terminal web** d'Alwaysdata (Accès distant
  → SSH → « par le Web », identifiant `ateliers-numeriques`, mot de passe
  SSH). Donner une commande courte à la fois, **sans `~`** (tapé `-` au
  clavier) et avec les majuscules exactes (`-N` devenu `-n` le 29/09).
  Déchiffrer une copie hors site exige un PC personnel.

## Décisions de l'utilisateur à ne pas « corriger »

- **Risques acceptés, audit du 01/10 (décidé le 02/10/2026)** :
  `StrictHostKeyChecking=accept-new` dans les workflows (clé d'hôte
  Alwaysdata non épinglée : il faudrait détourner la connexion pendant un
  déploiement) ; blocage de 15 min après 5 échecs par compte, sans compter
  l'IP (un tiers peut bloquer un compte 15 min, équipe petite, journal
  conservé).
  SheetJS 0.18.5 gardé (`vendor/xlsx-0.18.5/` et `xlsxstyle.js`, fondé
  sur la même version) : ses failles connues ne jouent qu'à la lecture d'un
  fichier, et l'appli ne fait qu'écrire (aucun `XLSX.read` ni
  `sheet_to_json` dans les deux dépôts, vérifié le 02/10). **Rouvrir** si un
  import `.xlsx` apparaît côté navigateur.

- **Interrupteur « login »** de Listes → Conseillers = accès à l'**Admin**
  seulement ; Index reste ouvert. Couper complètement un agent = supprimer
  son compte. Noms à accès Admin lisibles sans connexion (sans rôle) : écart
  de confidentialité accepté (24/09).
- Le rôle **superviseur** garde ses pouvoirs quasi admin (24/09) et **n'apparaît
  pas dans la liste de connexion d'Index** (26/09 : il travaille depuis
  l'Admin) — filtré par l'API (`action_get_comptes`, testé) et par les pages.
  **Validé par l'utilisateur le 26/09/2026.**
- **Déconnexion automatique d'Index après 30 min** d'inactivité, jamais
  pendant la saisie d'un atelier (aucun brouillon n'est gardé — à revoir si
  on en ajoute un).
- **Deux interfaces, une base** : l'équipe garde NextStep, l'utilisateur
  travaille sur NEWGEN (24/09) ; toute modification d'interface se fait sur
  les deux (`CLAUDE.md` règle 18). NEWGEN n'est plus un labo.

- **Interfaces, décidé le 26/09/2026** (NEWGEN + NextStep). Testés, donc
  pas détaillés ici : tri de l'Historique (`comparerHistorique`), AM/PM
  pré-rempli mais modifiable (`ampmDepuisHoraire`), tuiles et présents/inscrits
  **des seuls réalisés** (`kpiHistorique`), Classe mobile du volet
  (`matierePanneau`). Non testés, à ne pas défaire :
  - volet latéral commun `PanneauAtelier` (Historique, Calendrier, Agenda),
    alerte de conflit non bloquante ;
  - filtre public de l'Historique à choix multiples, « Tout afficher » (pas
    « Tous publics », confondu avec la catégorie) ; le Calendrier garde un
    choix unique ;
  - « Effacer » vide tout, statut compris ; « Voir tous » remet Planifié ;
  - liste de connexion Admin (« Changer ») : comptes à accès Admin seulement
    (`actif !== 'NON'`), sur les deux sites.

### 🔒 AG-002 (23/09/2026) — la journée entière reste la règle sur un prêt multi-jours

Décision de l'utilisateur : laisser tel quel, aucune fausse alerte constatée.
`occupeCreneauMateriel` (`logic.js`) :
- prêt d'**un seul jour** → seule la demi-journée de l'atelier est réservée ;
- prêt sur **plusieurs jours** → journées entières, du prélèvement à la
  veille du retour (le retour se fait le matin, il ne réserve rien).

La raison est une donnée absente : `date_prelevement_materiel` et
`date_retour_materiel` sont des dates **sans heure**, et `ampm` appartient à
l'atelier. On sur-réserve plutôt que de sous-réserver : une alerte de trop
coûte une vérification, une alerte manquante un conseiller sans matériel.
**Rouvrir** seulement si une alerte se déclenche sur un créneau réellement
libre.

## ⚠️ Pièges connus

- **Page HTML en cache sur téléphone** (26/09/2026) : `index.html` n'est pas
  versionné ; tant que l'ancien reste en cache, il charge l'ancien `utils.js`
  même si le nouveau est en ligne (Historique encore trié à l'ancienne sur le
  téléphone de l'utilisateur, 2026 comme 2027). Avant de chercher un bug
  après livraison : faire recharger ou effacer les données du site.

- **« Ordinateurs prêtés » vidé au premier enregistrement** (signalé le
  23/09, non reproduit, cause inconnue). Hypothèse non vérifiée : molette de
  la souris sur le champ numérique encore actif. Si ça revient : demander si
  le champ affiche vide ou « 0 ».

## Points à ne pas défaire

- Déjà dans `CLAUDE.md` §4-5 et verrouillés par les tests, non répétés ici :
  `_id` client gardé jusqu'au succès, écritures jamais doublées, plafonds,
  aucun appel superflu, `sw.js` de désinscription.
- **`periodePretMateriel` retombe sur la date de l'atelier** quand les dates
  de prélèvement/retour ne sont pas saisies (confirmé par l'utilisateur le
  22/09 : matériel pris et rendu le jour même).
- **L'occupation se compte à la demi-journée**, et une demi-journée inconnue
  réserve la journée entière (voir AG-002 ci-dessus).
- **Stockage navigateur préfixé par application** (`lsKey`, `APP_NS`
  `newgen` / `nextstep`) : les deux applis partagent l'origine
  `maswaddpt47-cmyk.github.io`, et `localStorage` n'est pas cloisonné par
  chemin.
- **Couche réseau client gardée** : avec l'API elle ne sert que sur une
  vraie coupure (4G terrain).
- **Un nom de composant ou de fonction n'est déclaré qu'une fois** entre
  `shared.js`, `app.js` et `admin_app.js` : sinon le dernier chargé remplace
  l'autre en silence (cas `TableCommunes`, devenu `TableCommunesDashboard`
  le 26/09). `scripts/parite.js` le signale (« définition écrasée »).
- **NEWGEN charge `logic.js`** dans ses pages (lot 0 d'AG-015) : plus aucune
  copie de la logique du matériel dans `shared.js`, ce qui est testé est ce
  qui tourne.
