# Chantiers en cours — ATELIERS_NEWGEN

État au **26/09/2026**. Tient aussi les restes communs à NextStep (même API,
même base depuis la bascule du 25/09/2026).
Fichier transitoire : à mettre à jour à chaque avancée, à supprimer quand tout
est soldé. Ce n'est pas de la documentation permanente (cf.
`MD-LIB/hygiene-instructions.md`).

**Ménages du 26/09/2026** : époque GAS (avant `49e4013`) puis récit du
chantier parité et des livraisons de la journée retirés — `git log -p
CHANTIERS.md`. Contradiction d'une proposition par une autre session :
`AGORA.md` (section 8 du `CLAUDE.md`) — AG-015 tranché le 26/09/2026.

---

## En production depuis le 25/09/2026

NextStep (équipe) et NEWGEN (utilisateur) parlent à la **même API PHP chez
Alwaysdata**, compte `ateliers-numeriques`, **même base MySQL**. Refonte
décidée le 23/09 (AG-009), bascule faite le 25/09 au matin, chantier clos
par l'utilisateur le 25/09.

- GAS NextStep et NEWGEN coupés (accès « Seulement moi »), déclencheurs
  supprimés. **Import verrouillé** : le lever = requête SQL délibérée
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
  le dépôt privé `ateliers-backups` (`age`, 90 j, déchiffrement testé par
  l'utilisateur ; rattrapages 11:47 et 17:47 car GitHub ne tient pas l'heure).
  Corbeille et onglet Sauvegardes dans l'Admin (AG-014) ; **pas** de bouton de
  restauration complète (une session volée effacerait tout). Procédure de
  restauration : PDF hors dépôt.
- Sécurité : jeton exigé en lecture, dans le corps POST (AG-011) ; jeton
  annulé à la déconnexion (`logout`) ; adresses mail rendues aux seuls
  admin/superviseur ; aucune ressource externe (`vendor/`, test RGPD-17 en
  CI) ; HTTPS forcé ; 2FA GitHub et Alwaysdata ; journal conservé 12 mois ;
  registre de sécurité v1.0 (hors dépôt).
- Plus de PWA (AG-012) : `sw.js` de désinstallation publié sans date de fin.

## 🔧 Parité NEWGEN/NextStep (AG-015, tranché le 26/09/2026)

**État au 26/09/2026 : 4 écarts, dont 3 `voulu`** (`APP_NS`, `App`,
`injectCSS` — navigation et design propres à chaque appli). Le récit des lots
(80 → 4 écarts) est dans `git log`. Outils : `node scripts/parite.js
../ateliers-cd47_NextStep` (constat), `--maj` (réécrit
`scripts/parite-ecarts.json` ; ce qui a bougé repasse en `à trancher`) ;
workflow `parite.yml`, jamais bloquant. **Avant de pousser** un changement
dans un écart `voulu` : `--maj`, puis remettre son statut à `voulu` à la main
(oublié le 26/09 → mail d'échec de `parite.yml`). **Ordre de push : NextStep d'abord,
puis NEWGEN**, sinon `parite.yml` compare au vieux NextStep.

- **Garde-fou (utilisateur, 26/09/2026) : ne pas uniformiser la charte
  graphique.** Chaque appli garde son design ; seuls le fonctionnement et les
  choix explicitement validés passent de l'une à l'autre.
- **Question en attente — `VueHistorique`** : NEWGEN en design v2, NextStep
  dans l'ancien (filtres rendus repliables le 26/09, en-tête au modèle de
  NEWGEN avec bouton « Effacer » — **validé par l'utilisateur le 26/09**). Aligner, ou passer en `voulu` ? Recommandé : `voulu`.
- **Vérification terrain de NextStep faite par l'utilisateur le 26/09/2026**
  (« tout fonctionne ») : Agenda (volet modifiable), connexion, saisie.

## Reste ouvert

- 📅 **30/09/2026 — relève du journal** (rappel planifié) : journal Admin
  NextStep depuis la bascule. Point de comparaison : labo du 24-25/09, 0/27
  perdus, médiane 0,3 s, p90 0,6 s, un seul utilisateur. Regarder `getAll` et
  les heures de pointe.
- **Avant le 01/10/2026** : mettre à jour la consigne de l'audit trimestriel
  — `MD-LIB/rgpd-securite.md` et la routine planifiée.
- **Fin octobre 2026** : classeurs Google (NextStep et ancien NEWGEN) et GAS,
  gardés figés et partagés avec l'utilisateur seul comme point de
  comparaison, puis suppression groupée — après avis des Archives
  départementales si elles le demandent (archives publiques). **Proposition
  du 26/09, à confirmer.**
- **Avant fin décembre 2026 — décision de l'utilisateur** : réécrire
  l'historique d'`ateliers-backups` (sinon une copie de plus de 90 jours
  reste lisible dans l'historique git ; bloqué par le garde-fou de session le
  25/09).
- `manifest-*.json` à retirer, une fois les dernières installations PWA
  désinstallées (seul l'utilisateur en avait).
- Déploiement par clé SSH au lieu du mot de passe (secret
  `ALWAYSDATA_SSH_PASSWORD`).
- Restes sans effet de l'époque GAS : `gas/` (archive), `banc/`, noms
  `GAS_*`/`gasAppel`/`__gasLog` (désignent la couche d'appel). À retirer si
  besoin, sans urgence.

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
