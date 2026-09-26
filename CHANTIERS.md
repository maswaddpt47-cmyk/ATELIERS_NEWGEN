# Chantiers en cours — ATELIERS_NEWGEN

État au **26/09/2026**. Tient aussi les restes communs à NextStep (même API,
même base depuis la bascule du 25/09/2026).
Fichier transitoire : à mettre à jour à chaque avancée, à supprimer quand tout
est soldé. Ce n'est pas de la documentation permanente (cf.
`MD-LIB/hygiene-instructions.md`).

**Ménage du 26/09/2026** : tout ce qui décrivait l'époque GAS (banc, doublage,
file d'attente, relevés de pertes, AG-001 à AG-008, préparation de la
bascule) a été retiré. Texte complet : `git log -p CHANTIERS.md`, avant
`49e4013`. Contradiction d'une proposition par une autre session :
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

## 🔧 Chantier parité NEWGEN/NextStep (AG-015, tranché le 26/09/2026)

Décision de l'utilisateur : amendements de la session B, dans cet ordre.
- **Lot 0 — fait le 26/09/2026.** NEWGEN charge `logic.js` dans ses pages et
  les 16 copies de `shared.js` sont supprimées (`normalizeMat`/`matIncludes`
  déplacées dans `utils.js`, comme NextStep). `logic.js` a repris les 4
  versions qui tournaient (`findMobileClassConflicts`,
  `findOrdinateursConflicts`, `getPretsMateriel`, `filterMaterielsVisibles`).
  Seul écart de comportement : `matIncludes` accepte l'ancien format « a|b »
  (l'API renvoie des tableaux ; une chaîne donnait « aucun conflit » en
  silence). Suites Node et navigateur vertes.
  Constat de départ : Aujourd'hui les pages exécutent ces copies (déjà
  identiques à NextStep), tandis que `logic.test.js` teste un `logic.js` qui a
  divergé : les tests ne testent pas ce qui tourne. Garde-fou : un nom
  déclaré deux fois casse le chargement, `smoke.spec.js` doit rester vert.
- **Lot 1 — outillé le 26/09/2026.** `scripts/parite.js` + liste
  `scripts/parite-ecarts.json`, workflow `parite.yml` (push + 05:17 UTC,
  jamais bloquant). Échoue sur : écart non listé, écart listé qui a bougé
  (empreinte), écart listé disparu. `--maj` réécrit la liste, tout ce qui a
  bougé repasse en `à trancher` — **déviation annoncée** : troisième statut,
  pour ne pas trancher à la place de l'utilisateur.
  Premier relevé : **80 écarts**, dont 1 `voulu` (`APP_NS`), 2 `à aligner`
  (`App` d'index — `logAccesIndex` au démarrage, NEWGEN `app.js:454` ;
  `GAS_ACTIONS_ECRITURE`), **77 `à trancher`**. Signalé en plus : `trunc`
  (NextStep) et `TableCommunes` (les deux) déclarés deux fois, la seconde
  écrase la première en silence.
- **Alignement en cours (26/09/2026, session 01GzrtQV) — 80 → 4 écarts (3 `voulu`).**
  L'utilisateur a délégué le choix (« choisis le meilleur des scénarios »),
  en ne posant que les vrais choix visibles. Faits, poussés, tests verts :
  `utils.js`/`logic.js` identiques (fonctions mortes retirées), couche
  d'appel identique, Bingo/Graphiques/Anomalies/Journal/Listes/thématiques.
  **Ordre de push : NextStep d'abord, puis NEWGEN** — sinon `parite.yml`
  compare au vieux NextStep et échoue (arrivé une fois le 26/09).
  Outils : `node scripts/parite.js ../ateliers-cd47_NextStep` (constat),
  `--maj` (réécrit la liste après alignement).
  **Ce bloc se met à jour après chaque paquet poussé** (demande de
  l'utilisateur, 26/09/2026) : compteur d'écarts, faits, reste.
- **Reste (16)** : `VueSaisie`, `VueHistorique`, `VueCalendrier`,
  `VueGestionOrdi`, `AttenteGAS`, `emptyRow`, CSS `injectCSS`, `App` (index
  et admin), `VueAdminV10`, `AdminLogin`, `VueLoginIndex`, `VueAccueilStatic`,
  `MaintenanceScreen`, `AnnonceNouvelleVersion`, `APP_NS` (voulu).
  Constats pour `VueSaisie` : NextStep gère l'échec partiel de `saveMany`
  (l'API n'est pas transactionnelle, `api/lib/ecriture.php:44-51`) et valide
  au blur ; NEWGEN applique en local via `onSaved(isNew, entry)`.
- **Tranché par l'utilisateur le 26/09/2026** :
  1. saisie par cycle comme NEWGEN (inscrits/présents par ligne, 4 pré-rempli) ;
  2. mode sombre ajouté à l'index NextStep ;
  3. écran d'attente de NEWGEN (bobine détaillée) ;
  4. navigation : **ne pas toucher** — barre NEWGEN, menu latéral NextStep
     (`voulu`).
  Fait : 2 (bouton 🌙 dans le menu NextStep, CSS de admin.css recopié dans
  app.css) et 3 (AttenteGAS + CSS). `App` et `injectCSS` passés en `voulu`
  (design propre à chaque appli). Fait : 1 (`VueSaisie` + `emptyRow`
  identiques, échec partiel de cycle géré). Calendrier (NEWGEN) et Gestion ordi (NextStep)
  identiques.
- Fait aussi : connexion, accueil, maintenance, AdminLogin, Admin (exports
  partenaire ICS/PDF), Agenda identiques ; `NOM_APPLI` (utils.js) porte le
  nom affiché ; NextStep rattache les variables de couleur NEWGEN à sa
  palette (fin de app.css et admin.css). `App` passé en `voulu`.
- **Question en attente (26/09/2026)** : `VueHistorique` — NEWGEN a le
  design v2 (panneau de filtres repliable, cartes et compteurs v2, bouton
  PDF), NextStep l'ancien (puces de filtre, boutons XLSX/ICS/Sync). Aligner
  NextStep sur NEWGEN, ou garder chacun (`voulu`) ? Les correctifs de
  fonctionnement sont déjà communs (panneau, mise en évidence).

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
- **Restes de l'époque GAS dans le dépôt, à trier (proposé le 26/09, rien
  décidé)** : sections 2 et 5 du `CLAUDE.md` (tests et « Backend GAS »
  décrivent encore GAS), dossiers `gas/` et `banc/`, noms `GS_URL`/`GAS_*`
  dans `shared.js`. La couche réseau client (plafonds, écritures jamais
  doublées, doublage des lectures) sert toujours pour l'API : ne pas la
  retirer sans décision, cf. « Points à ne pas défaire ».
  **Fait le 26/09/2026** : textes visibles « Google/classeur/GAS » remplacés
  (chargement, suppression, erreurs), badge « ⚠️ GAS » et ancien `VueAdmin`
  de NextStep retirés, puis **toutes les branches de l'ancien serveur**
  (`BACKEND_PHP`, `GS_URL`) supprimées sur les deux sites — comportement
  inchangé, toutes les suites vertes (NEWGEN : Node + sandbox/e2e/reseau/
  appels ; NextStep : Node + 35 Playwright). **Couche réseau gardée**
  (plafonds, reprises, doublage) : avec l'API elle ne se déclenche que sur une
  vraie coupure réseau (mobile), où elle sert encore ; la retirer ne ferait
  que simplifier le code au prix de ce filet. Restent dans le dépôt, sans
  effet : `gas/` (archive), `banc/`, les noms `GAS_*`/`gasAppel`.

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
- Le rôle **superviseur** garde ses pouvoirs quasi admin (24/09).
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
    partout (Historique, Dashboard, détail communes, conseillers, Calendrier,
    Agenda, Admin) — sinon les inscrits des ateliers planifiés ou annulés
    faussent le taux de présence. Audit du 26/09/2026 : 8 calculs corrigés
    sur NEWGEN, 10 sur NextStep, puis ramenés à **`kpiHistorique`**
    (`utils.js`, testé) : tout nouveau total de présents/inscrits passe par
    elle, pas par un filtre recopié.
  - **Panneau latéral** (Historique et Calendrier) : date, horaire, public,
    ordinateurs prêtés modifiables ; thématique en auto-proposition
    (`ComboThematique`). **Case « Classe mobile »** : le nombre d'ordinateurs
    n'apparaît et ne compte que si elle est cochée, comme dans le formulaire
    (`matierePanneau`, `utils.js`, testé). Des ateliers anciens peuvent avoir
    un nombre sans la case : ignorés du stock, **recensés dans Anomalies →
    « Ordinateurs sans Classe mobile »** (`ordiSansClasseMobile`, testé).
    Alerte non bloquante si l'atelier, tel qu'il sera
    enregistré, dépasse le stock d'ordinateurs ou partage la Classe mobile
    (`conflitsDeLEntree`, `utils.js`, testé ; vérifiée dans un navigateur
    le 26/09 sur NEWGEN ; **validée en production par l'utilisateur le
    26/09**). Dates de prélèvement et de retour dans le panneau, facultatives,
    prises en compte dans l'alerte.
  - **Filtre public de l'Historique à choix multiples** (26/09/2026) :
    état `filtPublic` = tableau, `[]` = tous ; pastilles à cocher
    (« Tout afficher » vide la sélection — **pas** « Tous (les) publics »,
    confondu avec la catégorie « Tous publics » de la liste). Le Calendrier
    garde son filtre public à choix unique.
  - Panneau latéral : « Période de prêt » affichée sous « Ordinateurs
    prêtés » (valeurs enregistrées, mise à jour après Enregistrer).
  - **« Effacer »** (filtres de l'Historique NEWGEN) vide tout, statut
    compris ; « Voir tous » remet le statut par défaut (Planifié).
  - **Liste de connexion Admin** (bouton « Changer », session ouverte) :
    seuls les comptes à « accès Admin » — filtre `actif !== 'NON'` côté
    page, sur les deux sites (NEWGEN testé par `e2e/appels.spec.js`). Retiré de
    NextStep puis remis le 26/09 : sans lui, tous les conseillers
    réapparaissent.

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
