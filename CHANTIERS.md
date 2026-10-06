# Chantiers en cours — ATELIERS_NEWGEN

État au **06/10/2026**. Tient aussi les restes communs à NextStep (même API,
même base depuis la bascule du 25/09/2026).
Fichier transitoire : à mettre à jour à chaque avancée, à supprimer quand tout
est soldé. Ce n'est pas de la documentation permanente (cf.
`MD-LIB/hygiene-instructions.md`).

**Ménages des 26/09, 02/10, 04/10 et 06/10/2026** : époque GAS, récit de la
parité, livraisons des 01-06/10 (durée, AM/PM, Planning, réimport Outlook,
fiche bilan, avis par QR, bilans, « Mes bilans », conflits à l'heure près,
pistes livrées) et décisions déjà verrouillées par les tests retirés —
`git log -p CHANTIERS.md`. Contradiction d'une proposition par une autre
session : `AGORA.md` (section 8 du `CLAUDE.md`) — **aucun bloc ouvert au
06/10/2026** (AG-023 tranché ce jour).

---

## En production depuis le 25/09/2026

NextStep (équipe) et NEWGEN (utilisateur) parlent à la **même API PHP chez
Alwaysdata**, compte `ateliers-numeriques`, **même base MySQL**. Refonte
décidée le 23/09 (AG-009), bascule faite le 25/09 au matin.

- **Google entièrement supprimé** : classeurs (29/09), projets Apps Script
  (01/10, par l'utilisateur). Code archivé : NEWGEN `3da2257:gas/`, NextStep
  `b8b96c7:gas/`. **Import verrouillé** : le lever = requête SQL délibérée
  (phpMyAdmin), sinon un second import écraserait les saisies.
- Tâches planifiées Alwaysdata : rappels d'ateliers en retard **08:00** chaque
  jour (`api/lib/rappels.php`, interrupteur `rappels_actifs`) ; **bilan
  mensuel** à la superviseure le 1er du mois à 08:00 (`api/lib/bilan.php`,
  `0 8 1 * *`).
- « Mot de passe oublié » par mail (`api/lib/reinit.php`, AG-013).
- Sauvegardes, trois niveaux : natives Alwaysdata (**3 jours**, offre Free) ;
  copie de nuit 03:00 dans `~/sauvegardes/` (30 j, restauration prouvée par
  `api-tests/sauvegarde.test.php`) ; copie **chiffrée** hors site 04:15 dans
  le dépôt privé `ateliers-backups`, **branche `copies` sans historique**
  (`age`, 90 j ; rattrapages 11:47 et 17:47 car GitHub ne tient pas l'heure).
  Corbeille et onglet Sauvegardes dans l'Admin (AG-014) ; **pas** de bouton de
  restauration complète (une session volée effacerait tout).
- Sécurité : jeton exigé en lecture, dans le corps POST (AG-011), annulé à la
  déconnexion ; adresses mail rendues aux seuls admin/superviseur ; aucune
  script ni feuille de style externe (`vendor/`,
  RGPD-17 en CI) ; deux services appelés par le navigateur des agents, API Géo
  de l'État et fonds de carte OSM France (registres V0.4, 06/10) ; HTTPS forcé ; 2FA GitHub et
  Alwaysdata ; journal 12 mois.
- **Analyse automatique du code (depuis le 06/10/2026), NEWGEN et NextStep** :
  CodeQL (« Default »), `semgrep.yml` (non bloquant), Dependabot, secret
  scanning, push protection ; résultats dans Security → Code scanning. Alertes
  du 06/10 triées et rejetées avec motif dans l'onglet (jeton de session en
  `sessionStorage` = « Won't fix », choix d'AG-011). Dependabot ne voit pas
  `vendor/`.
- **Documents à diffusion restreinte**, dépôt privé
  `ateliers-backups/documents/` : registre de sécurité et registre RGPD
  (art. 30) **V0.1** (06/10) — V0.x tant que non validé, **V1 à la
  validation** (DPO/DSI), puis V1.1… (guide d'archivage du CD47). Aussi : note
  DSI avant/après (PDF + Word), procédure de restauration ; anciennes versions
  dans `documents/archives/`, rendu par `documents/sources/rendre.js`. Prochaine révision : audit du 01/01/2027
  (ACME Alwaysdata à vérifier auprès de l'hébergeur).
- Plus de PWA (AG-012) : `sw.js` de désinstallation publié sans date de fin.
- **Bac à sable** (AG-019) : https://ateliers-numeriques.alwaysdata.net/sandbox/
  — comptes « Démo … », mot de passe = secret `SANDBOX_MDP_DEMO`, fausses
  données seulement (`sandbox_seed.php` : ateliers, avis et fiches fictifs,
  quatre ateliers de test des conflits des 05-06/10). **Gardé pour les
  essais** (utilisateur, 04/10). Circuit de toute nouveauté : branche
  `sandbox` → test par l'utilisateur → fusion dans `main` → production NEWGEN
  et NextStep → `main` reporté dans `sandbox`.

## 🔧 Parité NEWGEN/NextStep (AG-015)

**4 écarts, tous `voulu`** (`APP_NS`, `App`, `injectCSS`, `VueHistorique` —
navigation et design propres à chaque appli). Outils : `node scripts/parite.js
../ateliers-cd47_NextStep` (constat), `--maj` (réécrit
`scripts/parite-ecarts.json` ; ce qui a bougé repasse en `à trancher`) ;
workflow `parite.yml`, jamais bloquant. **Avant de pousser** un changement
dans un écart `voulu` : `--maj`, puis remettre son statut à `voulu` à la main.
**Ordre de push : NextStep d'abord, puis NEWGEN** — non respecté le 04/10
(quatre mails d'échec) ; depuis `71797cc`, `parite.yml` refait l'essai 10 et
20 min plus tard sur un push avant d'alerter. `scripts/githooks/pre-push`
(installé par le hook de session) refuse un push de NEWGEN si la parité n'est
pas à jour ou si NextStep a des commits non poussés.

- **Ne pas uniformiser la charte graphique** (utilisateur, 26/09) : chaque
  appli garde son design ; seuls le fonctionnement et les choix validés
  passent de l'une à l'autre.
- **`VueHistorique` : chacun garde son design** (26/09) ; le fonctionnement
  reste commun (`PanneauAtelier`, tri, tuiles, filtres).

## Reste ouvert

**Nouveautés** (règle 18 du `CLAUDE.md`) : prochain `id` **33** (`admin: true` = annonce réservée à l'Admin, depuis le 04/10) ; jamais 12,
16, 19, 24, 25, 26 ni 29 (publiés puis retirés). Les entrées des 03-04/10
sont datées du 05/10 à la demande de l'utilisateur.

**Actions de l'utilisateur**

0. **Rendez-vous DSI (préparé le 06/10)** : réponses aux questions attendues
   dans `ateliers-backups/documents/sources/preparation-rdv-dsi.md` ; à
   remettre : note avant/après V0.1 et registres V0.2. Restent à faire
   avant : mail de validation à la DPO (écart n° 7), premier jet du tableau
   de gestion pour les Archives départementales (écart n° 9), vérifier les
   deux « ⚠️ » du document (compte transférable, alerte d'intrusion).

1. **Écarts RGPD à porter à la DPO** (mail pas encore envoyé au 06/10) :
   compte Alwaysdata personnel sans DPA, durée de conservation des ateliers
   non fixée, mention d'information des agents, procédure de sortie ; **avis
   des stagiaires (T6) mis en service avant son avis** (qualification, base
   légale, remarques libres ; mention complétée le 06/10, `e150c69`).
2. **À tester sur PC** : réimport Outlook (rendez-vous déplacé → « Mettre à
   jour », supprimé → « Annulé » proposé décoché ; export .ics impossible sur
   téléphone) et infobulle du Planning (survol souris).
3. **01/11/2026** : premier envoi réel du bilan mensuel — le vérifier dans le
   journal Admin (« bilanMensuel »).

**Pour Claude**

0. **Nouveautés du 06/10 à ajouter après test de l'utilisateur** (règle 18) :
   seuil de 3 avis pour les conseillers ; bouton Admin « Envoyer un lien par
   mail » (provisoire en secours). En production depuis `2652c5e` / `bc4da1a`.

1. **Planning en essai** (depuis le 03/10, à côté de l'Agenda) : vers le
   **20/10**, relire les compteurs d'onglets (Admin → Connexions) avec
   l'utilisateur avant de retirer l'Agenda ; le mensuel (Calendrier gardé ou
   carte de charge) se tranche ensuite.
2. **Mention d'information RGPD des agents** : texte proposé le 03/10, **mis
   en pause par l'utilisateur** — ne pas relancer sans sa demande.
3. **Supports par atelier (pptx, docx…) — mis de côté le 04/10** : les
   supports sont sur un **réseau interne**. Une page en https ne peut pas
   ouvrir un lien `file://` vers un lecteur réseau (bloqué par les
   navigateurs, hypothèse à vérifier sur un poste CD47) : il faudrait des
   liens SharePoint/OneDrive ou un dépôt chez Alwaysdata. Bloc AGORA au choix
   du stockage (critère 1).
4. **Réimport Outlook** : un rendez-vous simple importé **avant** le 03/10 et
   déplacé depuis n'est pas reconnu (apparaît nouveau + supprimé). Options 2
   (calendrier publié) et 3 (Microsoft 365) écartées pour l'instant : DPO / DSI.
5. Écarté du CR « option 1 » : PWA (AG-012), nouveau modèle de données,
   PHPWord/TCPDF.

**Sans urgence**

6. **Import des disponibilités Outlook** (« Disponibilité uniquement ») pour
   griser les créneaux occupés dans l'Agenda, lu dans le navigateur
   seulement — à proposer si l'import des ateliers ne suffit pas.
7. **Thématique « TBD »** : visible telle quelle dans les statistiques tant
   qu'elle n'est pas remplacée — à surveiller.

**Audit trimestriel** : routine `trig_01J6ZMsLHKbgXAQsRYgQL16q` (6 dépôts,
sans connecteur, prochaine exécution le 01/01/2027). Créée dans
l'interface : **Claude ne peut pas la modifier**, seul le champ
« Instructions » est modifiable, par l'utilisateur. Ses commits vont sur des
branches `claude/…` : la consigne demande de les fusionner dans `main`, à
vérifier au 01/01.

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
  œil sur le serveur ; pour le bac à sable, le journal de `deploy-sandbox.yml`
  (sortie de `sandbox_seed.php`).
- `api/lib/` est fermé par `.htaccess` (HTTP 403 vérifié à chaque déploiement).
- **Dates « du jour » : toujours `date('Y-m-d')` côté PHP (heure de Paris,
  `base.php`), jamais `CURDATE()`** : le serveur MySQL peut être en UTC ; entre
  minuit et 2 h à Paris les deux diffèrent (`08e4f8b`). `NOW()` restant
  ailleurs (corbeille, journal, sessions) : même piège possible, non traité.
- **Poste pro de l'utilisateur : pas d'invite de commandes** (29/09). Toute
  opération serveur passe par le **terminal web** d'Alwaysdata (Accès distant
  → SSH → « par le Web », identifiant `ateliers-numeriques`, mot de passe
  SSH). Donner une commande courte à la fois, **sans `~`** (tapé `-` au
  clavier) et avec les majuscules exactes. Déchiffrer une copie hors site
  exige un PC personnel.
- **Tests PHP en session** : MariaDB s'arrête parfois entre deux commandes
  (`Connection refused`) ; `service mariadb start`, mot de passe root `root`.

## Décisions de l'utilisateur à ne pas « corriger »

- **Risques acceptés, audit du 01/10 (décidé le 02/10/2026)** :
  `StrictHostKeyChecking=accept-new` dans les workflows ; blocage de 15 min
  après 5 échecs par compte, sans compter l'IP ; SheetJS 0.18.5 gardé (ses
  failles ne jouent qu'à la lecture d'un fichier, l'appli ne fait qu'écrire —
  **rouvrir** si un import `.xlsx` apparaît côté navigateur).
- **Mot de passe (AG-023, 06/10/2026) : option 1 seule.** L'actuel est exigé
  pour changer le sien, sauf mot de passe provisoire ; Index l'envoie au
  changement imposé. **Pas fait, choix de l'utilisateur** : mot de passe
  provisoire imposé par l'API et écran dans les deux Admin (audit Codex n° 6).
- **Interrupteur « login »** de Listes → Conseillers = accès à l'**Admin**
  seulement ; Index reste ouvert. Couper complètement un agent = supprimer
  son compte. Noms à accès Admin lisibles sans connexion : écart accepté (24/09).
- Le rôle **superviseur** garde ses pouvoirs quasi admin (24/09) et **n'apparaît
  pas dans la liste de connexion d'Index** (26/09).
- **Déconnexion automatique d'Index après 30 min** d'inactivité, jamais
  pendant la saisie d'un atelier (aucun brouillon n'est gardé).
- **Deux interfaces, une base** : l'équipe garde NextStep, l'utilisateur
  travaille sur NEWGEN ; toute modification d'interface se fait sur les deux.
- **Interfaces, décidé le 26/09/2026**, non testés, à ne pas défaire : volet
  latéral commun `PanneauAtelier` (alerte de conflit non bloquante) ; filtre
  public de l'Historique à choix multiples, « Tout afficher » ; « Effacer »
  vide tout, statut compris ; liste de connexion Admin limitée aux comptes à
  accès Admin.
- **Avis, 06/10** : sous **3 avis**, un conseiller ne voit que leur nombre
  (QR, Avis par atelier, bilan) ; l'Admin voit tout (`AVIS_SEUIL`, testé).
  Un agent peut se déclarer co-animateur et voir les avis : **gardé**
  (écart n° 11). Envois simultanés (essais, plafond) : **acceptés**.
- **Aide mot de passe, 06/10** : l'Admin envoie un lien par mail
  (`envoyerLienReinit`) ; le provisoire n'est qu'un secours.
- **Avis des stagiaires (04/10)** : anonymes, sans âge ni recontact tant que
  le DPO n'a pas validé ; un avis par appareil et pas plus que de présents
  (à défaut d'inscrits) ; plus de bouton « poste partagé » (remplace
  l'amendement C d'AG-021) ; avis d'un atelier réservés à son animateur et
  son co-animateur (filtre API) ; suppression d'un avis par l'Admin seulement.
- **Vues, 05-06/10 (sans annonce)** : frise à une ligne par conseiller ;
  `ChoixDate` compact ; « ⚠️ ordi » clignotant ; « PIX » dans la fiche bilan ;
  chevron « › » de la barre du bas de NEWGEN (`majIndiceNav`, écart voulu).
- **Avis par atelier, 06/10** : colonne Partenaire ; réponses en détail
  (« 2 adapté · 2 trop rapide ») ; export CSV, remarques comprises (⚠️ hors
  purge à 24 mois ; formules neutralisées).
- **Côté conseillers (04/10)** : Stats = Synthèse et Analyse ; avis par
  atelier, bilans mensuel et trimestriel dans « Mes bilans », limité aux
  ateliers animés ou co-animés, masquable dans Admin → Visibilité. Pas de
  satisfaction par conseiller dans le bilan trimestriel (question RH).

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
- **Constantes `const` d'`utils.js` dans `logic.js`** : un `var` du même nom
  fait échouer le chargement de `logic.js` dans les pages (redéclaration),
  sans que les suites Node le voient — passer par `LOGIC_UTILS` (cas
  `BILAN_CHOIX`, attrapé par `e2e/sandbox.spec.js` le 04/10).

## Points à ne pas défaire

- Déjà dans `CLAUDE.md` §4-5 et verrouillés par les tests, non répétés ici :
  `_id` client gardé jusqu'au succès, écritures jamais doublées, plafonds,
  aucun appel superflu, `sw.js` de désinscription.
- **`periodePretMateriel` retombe sur la date de l'atelier** quand les dates
  de prélèvement/retour ne sont pas saisies (confirmé par l'utilisateur le
  22/09 : matériel pris et rendu le jour même).
- **Conflits de matériel : seul le stock d'ordinateurs compte** (décision de
  l'utilisateur du 05/10/2026, qui remplace la règle « Classe mobile unique » :
  « Classe mobile » veut dire « j'emprunte des ordinateurs du stock »). Stock
  compté à l'heure près (AG-022) : prêt d'un jour sur `[début, fin + 30 min)`,
  atelier sans horaire sur sa demi-journée sans marge ou la journée entière ;
  un conseiller compte une fois, au max de ses quantités. Verrouillé par
  `logic.test.js`. `findMobileClassConflicts` reste dans `logic.js` (testé)
  mais n'est plus appelé par les écrans.
- **Prêt sur plusieurs jours : journées entières** (AG-002, 23/09), du
  prélèvement à la veille du retour (le retour se fait le matin). Les dates
  de prêt sont sans heure : on sur-réserve plutôt que de sous-réserver.
  **Rouvrir** seulement si une alerte se déclenche sur un créneau réellement
  libre.
- **Stockage navigateur préfixé par application** (`lsKey`, `APP_NS`
  `newgen` / `nextstep`) : les deux applis partagent l'origine
  `maswaddpt47-cmyk.github.io`, et `localStorage` n'est pas cloisonné par
  chemin.
- **Couche réseau client gardée** : avec l'API elle ne sert que sur une
  vraie coupure (4G terrain).
- **Un nom de composant ou de fonction n'est déclaré qu'une fois** entre
  `shared.js`, `app.js` et `admin_app.js` : sinon le dernier chargé remplace
  l'autre en silence. `scripts/parite.js` le signale (« définition écrasée »).
- **NEWGEN charge `logic.js`** dans ses pages (lot 0 d'AG-015) : ce qui est
  testé est ce qui tourne.

## Pistes d'amélioration

Règle 22 de MD-LIB `collaboration.md` : au plus 3 pistes, à la fin d'une
fonctionnalité validée ou sur demande de revue. Une piste écartée ne se
repropose pas sans fait nouveau.

**Proposées, en attente**
- 04/10/2026 : **rappel du matin pour une fiche bilan vide** sur un atelier « Réalisé », comme le rappel existant des ateliers restés « Planifié » — le bilan trimestriel compte les ateliers « sans fiche », rien ne pousse à la remplir.
- 06/10/2026 : **progression début → fin de cycle dans les avis** : mettre côte à côte « À l'aise » et « Refaire seul » de la séance diagnostic et de la dernière séance d'un même cycle — le 05/10 (Fumel, diagnostic) donne 1/5 sur les deux, point de départ que rien ne compare aujourd'hui.

**Écartées** (date — piste — raison)
- 03/10/2026 — lien d'abonnement agenda pour les partenaires — « pour l'instant » (utilisateur).
- 03/10/2026 — tri des onglets d'après les compteurs d'usage — « pour l'instant ».
- 03/10/2026 — aide « Premiers pas » pour un nouvel arrivant — « pour l'instant ».
