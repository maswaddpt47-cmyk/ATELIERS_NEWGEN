# Chantiers en cours — ATELIERS_NEWGEN

État au **01/10/2026**. Tient aussi les restes communs à NextStep (même API,
même base depuis la bascule du 25/09/2026).
Fichier transitoire : à mettre à jour à chaque avancée, à supprimer quand tout
est soldé. Ce n'est pas de la documentation permanente (cf.
`MD-LIB/hygiene-instructions.md`).

**Ménages du 26/09/2026** : époque GAS (avant `49e4013`) puis récit du
chantier parité et des livraisons de la journée retirés — `git log -p
CHANTIERS.md`. Contradiction d'une proposition par une autre session :
`AGORA.md` (section 8 du `CLAUDE.md`) — **aucun bloc ouvert au 27/09/2026**
(AG-015 sorti le 27/09, `b73e86d`).

---

## En production depuis le 25/09/2026

NextStep (équipe) et NEWGEN (utilisateur) parlent à la **même API PHP chez
Alwaysdata**, compte `ateliers-numeriques`, **même base MySQL**. Refonte
décidée le 23/09 (AG-009), bascule faite le 25/09 au matin, chantier clos
par l'utilisateur le 25/09.

- **Base Google supprimée** (classeurs, confirmé par l'utilisateur le
  29/09/2026). GAS NextStep et NEWGEN coupés (accès « Seulement moi »), déclencheurs
  supprimés ; code des scripts en ligne archivé (`gas/README.md` des deux
  dépôts : NEWGEN `3da2257`, NextStep `b8b96c7`). **Import verrouillé** : le lever = requête SQL délibérée
  (phpMyAdmin), sinon un second import écraserait les saisies.
- Vitesse mesurée avant bascule (`banc/cibles.html`, 23-24/09) : GAS perdait
  9,5 % (nuit) à 26,5 % (jour) des appels, Alwaysdata **0**, médiane 0,3 s.
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
- **Vérification terrain de NextStep faite par l'utilisateur le 26/09/2026**
  (« tout fonctionne ») : Agenda (volet modifiable), connexion, saisie.

## Reste ouvert (état du 01/10/2026, par priorité)

Faits le 01/10/2026 (détail dans `git log`) : lot 1 UX ; XSS des
info-bulles/popups corrigée et cache des ateliers retiré de NEWGEN ;
`admin_v14.html` retiré de NextStep, RGPD-17 étendu à toutes les pages ;
GDINV2 et SMS-mail suivis dans leurs propres CHANTIERS.

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

**Pour Claude**

4. **Registre de sécurité, prochaine version** : porter au §9 la décision
   du 01/10 (un conseiller peut intervenir sur les ateliers des autres —
   risque accepté, filets corbeille 30 j et journal ; **ne pas le
   « corriger »**) ; noter les corrections du 01/10 (XSS, cache, RGPD-17).
5. **Mineurs de l'audit du 01/10** : `permissions:` absent de
   `deploy-api.yml`/`diagnostic-api.yml` (jeton déjà en lecture) ;
   `StrictHostKeyChecking=accept-new` (clé d'hôte Alwaysdata non épinglée) ;
   `schema.sql` lisible ne donne qu'un avertissement au déploiement ;
   `api/import.php` et `api/mailtest.php` encore en ligne malgré l'import
   verrouillé ; blocage après 5 échecs utilisable pour bloquer un compte
   15 min ; SheetJS 0.18.5 (l'appli n'écrit que : risque faible).

**Sans urgence**

6. **Usage des onglets** (depuis le 29/09) : regarder Admin → Connexions
   dans quelques semaines pour décider des onglets à simplifier (lot 2).
7. **Relevé du journal NextStep depuis le PC pro, 9 h-17 h** : seul cas non
   mesuré (relève du 30/09 : 45 appels, 0 perdu, médiane 0,1 s).

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

- **Interfaces, décidé le 26/09/2026** (appliqué NEWGEN + NextStep) :
  - **Historique trié** par date, puis **horaire** (chronologique), puis
    orienteur seulement à heure égale (`comparerHistorique`, `utils.js`,
    testé). Le bouton ↑/↓ Date n'inverse que les dates. Première version
    (orienteur avant horaire) rejetée par l'utilisateur.
  - **AM/PM pré-rempli** d'après l'horaire (avant 12:00 = AM), **toujours
    modifiable** à la main ; recalculé si l'horaire change
    (`ampmDepuisHoraire`, testé).
  - **Tuiles de l'Historique** sur la liste filtrée **sans** le filtre de
    statut : Total, Planifiés, Réalisés, Annulés, Autres (= Reportés + Non
    réalisés, regroupés), Présents/inscrits **des seuls réalisés** ; % sur
    le total (`kpiHistorique`, testé). Pas de tuile « inscrits prévus ».
  - **Calendrier** : l'orienteur sur sa propre ligne dans la pastille.
  - **Présents et inscrits : toujours sur les seuls ateliers réalisés**,
    partout, via **`kpiHistorique`** (`utils.js`, testé) — jamais un filtre
    recopié à la main.
  - **Volet latéral commun `PanneauAtelier`** (Historique, Calendrier,
    Agenda) : date, horaire, public, Classe mobile (le nombre d'ordinateurs ne
    compte que si elle est cochée, `matierePanneau`), dates de prêt,
    thématique en auto-proposition, alerte de conflit non bloquante
    (`conflitsDeLEntree`, validée en production le 26/09). Ateliers anciens
    avec un nombre sans la case : Anomalies → « Ordinateurs sans Classe
    mobile ».
  - **Filtre public de l'Historique à choix multiples** (26/09/2026) :
    état `filtPublic` = tableau, `[]` = tous ; pastilles à cocher
    (« Tout afficher » vide la sélection — **pas** « Tous (les) publics »,
    confondu avec la catégorie « Tous publics » de la liste). Le Calendrier
    garde son filtre public à choix unique.
  - **« Effacer »** (filtres de l'Historique NEWGEN) vide tout, statut
    compris ; « Voir tous » remet le statut par défaut (Planifié).
  - **Liste de connexion Admin** (bouton « Changer », session ouverte) :
    seuls les comptes à « accès Admin » — filtre `actif !== 'NON'` côté
    page, sur les deux sites (NEWGEN testé par `e2e/appels.spec.js`) ; sans
    lui, tous les conseillers réapparaissent.

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

- **`_id` fourni par le client et gardé tant que l'envoi n'a pas réussi** :
  rejouer une écriture remplace, ne duplique pas (clé primaire). Le doublon de
  cycle du 23/09 (chaque clic tirait de nouveaux `_id`) ne peut plus se
  reproduire.
- **Les écritures ne sont jamais doublées** par la couche réseau
  (`GAS_ACTIONS_ECRITURE`, décidé par la couche, pas par l'appelant).
- **Plafonds : 12 s lecture, 12 s écriture, 25 s `saveMany`** — verrouillés
  par `e2e/reseau.spec.js`. Les rallonger n'a jamais récupéré une réponse.
- **Aucun appel superflu au démarrage ni après une écriture** : les écritures
  s'appliquent localement (`appliquerEntree`/`retirerEntree`), pas de
  rechargement pour relire. Verrouillé par `e2e/appels.spec.js`.
- **`sw.js` reste publié** et ne fait que se désinscrire ; jamais de
  désinscription depuis la page (origine partagée avec NextStep et GDINV2).
- **`periodePretMateriel` retombe sur la date de l'atelier** quand les dates
  de prélèvement/retour ne sont pas saisies (confirmé par l'utilisateur le
  22/09 : matériel pris et rendu le jour même).
- **L'occupation se compte à la demi-journée**, et une demi-journée inconnue
  réserve la journée entière (voir AG-002 ci-dessus).
- **Stockage navigateur préfixé par application** (`lsKey`, `APP_NS`
  `newgen` / `nextstep`) : les deux applis partagent l'origine
  `maswaddpt47-cmyk.github.io`, et `localStorage` n'est pas cloisonné par
  chemin.
- **Couche réseau client gardée** (plafonds, reprises, doublage des
  lectures) : avec l'API elle ne joue que sur une vraie coupure (4G terrain),
  où elle sert encore.
- **Un nom de composant ou de fonction n'est déclaré qu'une fois** entre
  `shared.js`, `app.js` et `admin_app.js` : sinon le dernier chargé remplace
  l'autre en silence (cas `TableCommunes`, devenu `TableCommunesDashboard`
  le 26/09). `scripts/parite.js` le signale (« définition écrasée »).
- **NEWGEN charge `logic.js`** dans ses pages (lot 0 d'AG-015) : plus aucune
  copie de la logique du matériel dans `shared.js`, ce qui est testé est ce
  qui tourne.
