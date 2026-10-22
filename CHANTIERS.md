# Chantiers en cours — ATELIERS_NEWGEN

État au **16/10/2026**. Tient aussi les restes communs à NextStep (même API,
même base depuis la bascule du 25/09/2026).
Fichier transitoire : à mettre à jour à chaque avancée, à supprimer quand tout
est soldé. Ce n'est pas de la documentation permanente (cf.
`MD-LIB/hygiene-instructions.md`).

**Ménages des 26/09, 02/10, 04/10, 06/10, 07/10 et 16/10/2026** : époque GAS,
récit de la parité, livraisons, audit Codex et ses corrections, et décisions
déjà verrouillées par les tests retirés — `git log -p CHANTIERS.md`.
Contradiction : `AGORA.md` (section 8 du `CLAUDE.md`) — **aucun bloc ouvert au
16/10/2026**.

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
  déconnexion ; adresses mail rendues aux seuls admin/superviseur ; aucun
  script ni feuille de style externe (`vendor/`, RGPD-17 en CI) ; deux
  services appelés par le navigateur des agents : API Géo de l'État et fonds
  de carte OSM France ; HTTPS forcé ; 2FA GitHub et Alwaysdata ; journal 12 mois.
- **Contrôles du code (depuis le 06/10/2026), NEWGEN et NextStep** : CodeQL
  (« Default »), `semgrep.yml` (non bloquant), Dependabot (ne voit pas
  `vendor/`), secret scanning, push protection ; alertes triées avec motif dans
  Security → Code scanning. **Audit Codex** (OpenAI) le 06/10 : 14 + 3 points,
  rapports vérifiés dans `ateliers-backups/documents/sources/` ; règles
  MD-LIB `agora.md` §12 (blocs de sécurité → Codex, audit trimestriel).
- **Documents à diffusion restreinte**, `ateliers-backups/documents/` :
  registres de sécurité et RGPD **V0.6** (06/10) — V0.x tant que non validé,
  **V1 à la validation** (DPO/DSI), chaque mise à jour monte d'un cran ;
  note DSI avant/après, procédure de restauration ; anciennes versions dans
  `documents/archives/`, rendu par `documents/sources/rendre.js`. Prochaine
  révision : audit du 01/01/2027 (ACME Alwaysdata à vérifier).
- Plus de PWA (AG-012) : `sw.js` de désinstallation publié sans date de fin.
- **Bac à sable** (AG-019) : https://ateliers-numeriques.alwaysdata.net/sandbox/
  — comptes « Démo … » ; « Démo Admin » garde le mot de passe du secret
  `SANDBOX_MDP_DEMO`, les autres comptes ont été changés depuis l'Admin du bac
  à sable le 08/10 (le secret ne sert qu'au premier remplissage), fausses
  données seulement (`sandbox_seed.php` : ateliers, avis et fiches fictifs,
  quatre ateliers de test des conflits des 05-06/10). **Gardé pour les
  essais** (utilisateur, 04/10). Circuit de toute nouveauté : branche
  `sandbox` → test par l'utilisateur → fusion dans `main` → production NEWGEN
  et NextStep → `main` reporté dans `sandbox`.

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

- **Ne pas uniformiser la charte graphique** (utilisateur, 26/09) : chaque
  appli garde son design ; seuls le fonctionnement et les choix validés
  passent de l'une à l'autre.
- **`VueHistorique` : chacun garde son design** (26/09) ; le fonctionnement
  reste commun (`PanneauAtelier`, tri, tuiles, filtres).

## Reste ouvert

**Nouveautés** (règle 18 du `CLAUDE.md`) : prochain `id` **35** (`admin: true` = annonce réservée à l'Admin, depuis le 04/10) ; jamais 12,
16, 19, 24, 25, 26 ni 29 (publiés puis retirés). Les entrées des 03-04/10
sont datées du 05/10 à la demande de l'utilisateur.

**Actions de l'utilisateur**

00. **Faille PHP2Shell (21/10/2026)** — API coupée puis **rouverte le 21/10 à
   21 h 05** avec le bouton de coupure (AG-027, essai réel complet : runs 1 à
   10). Examen de la période : aucun indice identifié, sans pouvoir exclure une
   compromission (AG-026). Mails d'échec du contrôle horaire reçus ;
   `COMPTES_EMPREINTE` enregistrée ; équipe avertie de la réouverture
   (déclaré par l'utilisateur, 21/10). Reste :
   1. ~~WoocLight~~ examiné le 22/10 à 00 h 11 (`PAPPERBOARD/integrite.yml`) :
      vert, voir le `CHANTIERS.md` de PAPPERBOARD ;
   2. en parler au directeur : remettre le registre de sécurité V0.7 et
      l'annexe A V0.3 (`ateliers-backups/documents/`, 22/10) ;
   3. **AG-028, veilleur du contrôle d'intégrité** : la programmation horaire
      d'`integrite-api.yml` est irrégulière (1 passage programmé du 21/10
      17 h 15 au 22/10 00 h 27, mesuré). Veilleur amendé selon Codex et essayé
      sur la branche `ag-028-veilleur` (`c078aee`), **pas sur `main`**. Avant
      la mise en service : l'utilisateur confirme-t-il les mails « Run failed:
      Veilleur intégrité » du 22/10 vers 00 h 53 et 01 h 00 ? Mise en service :
      retirer le déclencheur `push` d'essai et la condition `if` du job, passer
      sur `main`, lancer un veilleur à la main (`passages` 5, `intervalle` 60).
      Le workflow est actif sur GitHub (réactivé après les essais).
   4. **AG-029 appliqué le 22/10** (`ecb6544`) : le contrôle ne compte plus le
      jour en cours (cause du mail rouge de 3 h 33, run 39706363950).
      Essais réels sur `main` : défaut, vert (run 24) ; `depuis` = aujourd'hui,
      vert avec « présence quotidienne non évaluée » (run 25). Reste : la
      requête-témoin pour une vraie fraîcheur des journaux, évolution à arbitrer.
   5. **Annexe A et registre V0.7 (22/10)** : annexe courte **V0.3** rédigée
      (3 pages, `ateliers-backups`, `docs: annexe A V0.3`) sur proposition de
      Codex corrigée par Claude ; V0.2 (8 pages) archivée et gardée comme
      dossier de preuves. Les six corrections du registre V0.7 sont faites.
      **À valider par l'utilisateur** : la V0.3 (place laissée aux IA, ligne
      « relecture humaine du code à proposer à la direction »). **À décider** :
      deux lacunes du registre (rubrique détection et réponse en §6, audits du
      21-22/10 en §8.5).
0. **Rendez-vous DSI (préparé le 06/10)** : réponses aux questions attendues
   dans `ateliers-backups/documents/sources/preparation-rdv-dsi.md` ; à
   remettre : note avant/après V0.1 et registres V0.6. Reste à faire avant :
   vérifier les deux « ⚠️ » du document (compte transférable, alerte
   d'intrusion).

2. **01/11/2026** : premier envoi réel du bilan mensuel — le vérifier dans le
   journal Admin (« bilanMensuel »).
3. **Avant le 01/11/2026 : prévenir l'équipe** que le rappel du matin listera
   aussi les fiches bilan vides (ateliers « Réalisé » à partir du 01/11,
   `RAPPELS_FICHE_DEPUIS` dans `api/lib/rappels.php`) ; annonce Nouveautés à
   proposer à l'utilisateur à ce moment-là.
4. **Depuis le PC** : coller `MD-LIB/outils/script-demarrage.sh` dans le
   Setup script de l'environnement (menu de l'environnement → Edit). Ce script installe le contrôle
   automatique du déploiement (`MD-LIB/deploiement.md`) ; d'ici là, le lancer
   à la main après chaque push.

**Pour Claude**

1. **Planning en essai** (depuis le 03/10, à côté de l'Agenda) : **le 20/10**,
   relire avec l'utilisateur les compteurs d'onglets (Admin → Connexions) ;
   il veut retirer l'Agenda (le Calendrier est coupé matin / après-midi comme
   lui depuis le 13/10). Reste aussi à trancher si le Calendrier garde sa
   place à côté de la vue Mois (affichée par défaut). Fait remonté le 07/10 :
   sur téléphone, seuls lundi et mardi tiennent à l'écran, le reste se trouve
   en faisant défiler — l'utilisateur a cru un atelier absent.
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

7. **Thématique « TBD »** : visible telle quelle dans les statistiques tant
   qu'elle n'est pas remplacée — à surveiller.

**Audit trimestriel** : routine `trig_01J6ZMsLHKbgXAQsRYgQL16q` (6 dépôts,
prochaine le 01/01/2027), créée dans l'interface : **Claude ne peut pas la
modifier** (champ « Instructions » seulement, par l'utilisateur) ; ses commits
vont sur des branches `claude/…` à fusionner dans `main` — à vérifier au 01/01.
**Rappels de l'audit Codex** (lancé à la main), push et mail à 08:52 :
`trig_012uCnnzo6MZhyWNYr6JAFqv` le 2 janvier, `trig_01XC6RefYHqVbN2Wcjz3kkXo`
le 1er avril, juillet et octobre. Copilot écarté le 07/10 (abonnement payant
requis) ; Gemini en réserve.

## À ne pas réapprendre — Alwaysdata et déploiement

- **Correctif PHP d'Alwaysdata : redémarrer le site** (21/10/2026, faille
  « PHP2Shell ») : `php -v` en SSH donnait 8.4.27 (corrigée) alors que l'API
  tournait encore en 8.4.26. Web → Sites → Redémarrer a suffi. Contrôle de la
  version servie : `diagnostic-api.yml` (section « php (web) »). Blocage
  d'urgence : `echo 'Require all denied' > www/.htaccess` (403), `rm` pour
  rouvrir. **SSH par clé seule depuis le 21/10/2026** (mot de passe
  désactivé par l'utilisateur, déclaré) : les workflows (`deploy-api`,
  `deploy-sandbox`, `diagnostic-api`, `copie.yml` d'ateliers-backups) passent
  par clé. Pour une intervention à la main : réactiver le mot de passe, puis le
  désactiver (procédure de restauration V0.3, section 3).
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

- **Plus de démarche auprès de la DPD ni des Archives départementales
  (16/10/2026)** : ne proposer ni mail, ni tableau de gestion, ni visa
  d'élimination. Les écarts du registre RGPD V0.6 (n° 1, 2, 3, 5, 6, 7, 9 à
  11) restent ouverts dans le registre ; s'ils doivent avancer, ce sera par
  une autre voie choisie par l'utilisateur.
- **Risques acceptés, audit du 01/10 (décidé le 02/10/2026)** :
  `StrictHostKeyChecking=accept-new` dans les workflows ; blocage de 15 min
  après 5 échecs par compte, sans compter l'IP ; SheetJS 0.18.5 gardé (ses
  failles ne jouent qu'à la lecture d'un fichier, l'appli ne fait qu'écrire —
  **rouvrir** si un import `.xlsx` apparaît côté navigateur).
- **Mots de passe (06/10/2026)** : l'actuel est exigé pour changer le sien,
  sauf provisoire ; Index l'envoie au changement imposé (AG-023, option 1).
  L'Admin envoie un lien par mail (`envoyerLienReinit`), le provisoire n'est
  qu'un secours. **Pas fait, choix de l'utilisateur** : provisoire imposé par
  l'API et dans les deux Admin (audit Codex n° 6).
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
  (écart n° 11). Envois simultanés (essais, plafond) : **acceptés**. Ni le
  seuil ni le lien Admin ne sont annoncés dans les Nouveautés (décision du 06/10).
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

## Points à ne pas défaire

- Déjà dans `CLAUDE.md` §4-5 et verrouillés par les tests, non répétés ici :
  `_id` client gardé jusqu'au succès, écritures jamais doublées, plafonds,
  aucun appel superflu, `sw.js` de désinscription.
- **Suppression définitive dans la Corbeille réservée admin/superviseur**
  (AG-024, 13/10/2026) : `purgerCorbeille` ne doit jamais rejoindre la porte
  « corbeille ouverte » des conseillers (`api/lib/api.php`) ; verrouillé par
  `api-tests/api.test.php`.
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

## Pistes d'amélioration

Règle 22 de MD-LIB `collaboration.md` : au plus 3 pistes, à la fin d'une
fonctionnalité validée ou sur demande de revue. Une piste écartée ne se
repropose pas sans fait nouveau.

**Proposées, en attente** : aucune (10/10/2026).

**Écartées** (date — piste — raison)
- 03/10/2026 — lien d'abonnement agenda pour les partenaires — « pour l'instant » (utilisateur).
- 03/10/2026 — tri des onglets d'après les compteurs d'usage — « pour l'instant ».
- 03/10/2026 — aide « Premiers pas » pour un nouvel arrivant — « pour l'instant ».
- 10/10/2026 — heure pré-remplie au clic sur un créneau de l'Agenda — une heure par défaut risque d'être gardée sans correction (horaires variés).
- 10/10/2026 — progression début → fin de cycle dans les avis — « pour l'instant » : un seul diagnostic avec avis (05/10) ; à reprendre quand un cycle sera bouclé.
- 12/10/2026 — synchroniser l'import Outlook entre PC et téléphone (à la façon de la sauvegarde SpeedDial) — « trop compliqué » (utilisateur) : la voie propre passe par le serveur, donc nouvelle donnée personnelle (registres, DPO, purge à viser) et bloc AGORA ; le .ics se réimporte déjà sur le téléphone.
- 12/10/2026 — envoi du .ics par mail à chaque import (serveur) ou bouton de partage (menu de l'appareil) — « reste manuel » (utilisateur) : gain d'une seule étape, le téléphone doit toujours importer le fichier.
