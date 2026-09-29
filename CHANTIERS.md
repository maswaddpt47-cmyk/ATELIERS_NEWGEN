# Chantiers en cours — ATELIERS_NEWGEN

État au **29/09/2026**. Tient aussi les restes communs à NextStep (même API,
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
  le dépôt privé `ateliers-backups` (`age`, 90 j, déchiffrement testé par
  l'utilisateur ; rattrapages 11:47 et 17:47 car GitHub ne tient pas l'heure).
  Corbeille et onglet Sauvegardes dans l'Admin (AG-014) ; **pas** de bouton de
  restauration complète (une session volée effacerait tout). Procédure de
  restauration v3.0 : `ateliers-backups/documents/`.
- Sécurité : jeton exigé en lecture, dans le corps POST (AG-011) ; jeton
  annulé à la déconnexion (`logout`) ; adresses mail rendues aux seuls
  admin/superviseur ; aucune ressource externe (`vendor/`, test RGPD-17 en
  CI) ; HTTPS forcé ; 2FA GitHub et Alwaysdata ; journal conservé 12 mois ;
  documents à diffusion restreinte, **rangés dans le dépôt privé
  `ateliers-backups/documents/`** (sources et v1.0 archivées) : registre de sécurité
  v3.0, registre RGPD v1.1 (art. 30) et procédure de restauration v3.0
  (29/09/2026, fusion des v1.0 du 25/09 et des v2.0, corrigée sur le code). Leurs écarts RGPD ouverts —
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

## Reste ouvert

- 📅 **30/09/2026 — relève du journal** (rappel planifié) : journal Admin
  NextStep depuis la bascule. Point de comparaison : labo du 24-25/09, 0/27
  perdus, médiane 0,3 s, p90 0,6 s, un seul utilisateur. Regarder `getAll` et
  les heures de pointe.
- **Avant le 01/10/2026** : mettre à jour la consigne de l'audit trimestriel
  — `MD-LIB/rgpd-securite.md` et la routine planifiée. L'audit du 01/10 doit
  aussi reprendre le §9 du registre de sécurité v3.0 : faille ACME publiée
  chez Alwaysdata (effet sur un sous-domaine `alwaysdata.net` ?), DPA et
  certification d'Alwaysdata à vérifier sur pièce.
- **Avant fin décembre 2026 — décision de l'utilisateur** : réécrire
  l'historique d'`ateliers-backups` (sinon une copie de plus de 90 jours
  reste lisible dans l'historique git ; bloqué par le garde-fou de session le
  25/09).
- **AGORA — vérifier chaque « amendé »** (ajouté le 27/09/2026, `AGORA.md`
  section « Sincérité ») : à chaque bloc tranché en « amendé », noter en une
  ligne ce que l'amendement a changé (code, décision ou chiffre). S'il n'a
  rien changé, c'était un « confirmé » : le signaler à l'utilisateur. Le
  total sous le tableau sert d'alerte, pas d'objectif — ne pas chercher un
  « confirmé » pour casser la série. État recompté le 27/09/2026 sur
  l'historique git : 15 blocs, 12 amendés, 0 confirmé, 0 contredit,
  3 sans réponse (AG-002, AG-010, AG-013) ; le verdict est désormais porté
  sur chaque ligne du tableau. Section « Sincérité » présente dans les quatre
  `AGORA.md` (NEWGEN, GDINV2, SMS-mail, sms-mail-multi) — suivi dans
  `MD-LIB/CLAUDE.md`.
- **Registres — corrections en attente (lecture de l'utilisateur en cours,
  tout appliquer en une fois)** : sources dans
  `ateliers-backups/documents/sources/`, régénérer par `rendre.js`.
  1. Registre de sécurité v3.0, §1, ligne « Copies de la base », colonne
     données personnelles : remplacer par « Oui — en clair sur Alwaysdata
     (accès réservé au compte, protégé par double authentification) ;
     chiffrées sur GitHub, lisibles uniquement avec la clé privée du
     responsable » (29/09/2026).
  2. Registre de sécurité, nouvelle section « 2.4 Les bancs d'essai »
     (29/09/2026), tableau : 21/09 protocole corrigé (AG-001) ; 22/09 banc
     des stratégies, domicile, 249 salves, pertes GAS 30-38 %, échecs file
     18,4 % contre doublage 4,0 % (McNemar 10,32) ; 22/09 relevé réel
     NextStep, 44 appels, 45 % perdus, 221 s d'attente morte ; 22/09 soir
     compteur de sauvetages, 75 % perdus, doublage 25 % sauvés ; nuit 23-24/09
     GAS/Alwaysdata par paires, domicile, 158 paires, 9,5 % contre 0 (13,07) ;
     24/09 jour, PC pro + VPN CD47, 68 paires, 26,5 % perdus + 9 > 12 s = 40 %
     contre 0 (16,06) ; 24-25/09 labo NextStep sur Alwaysdata, 0/27 perdus,
     médiane 0,3 s ; 26/09 réseau filaire du bureau bon. Plus une ligne qui
     explique le test de McNemar (seuil 3,84 = 95 % de certitude). Sources :
     `git show ce7c1e9^:CHANTIERS.md` (§1, étape 0, relevés du 22/09).
  3. **Page 2/12 vide** (signalé le 29/09) : la couverture déborde de
     quelques mm, le saut de page envoie la suite en page 3. Resserrer la
     couverture ; contrôle après rendu = texte de chaque page non vide
     (pymupdf), pas seulement la planche d'aperçu, qui l'a laissé passer.
  4. **§2.2 « Sécurité » à réécrire** (utilisateur, 29/09) : la liste des
     failles de l'ancienne appli se retourne contre le responsable (elle a
     tourné des mois ainsi). Tourner en « limites de la plateforme Google et
     durcissements successifs → ce que la nouvelle architecture apporte » ;
     formulation à faire valider par l'utilisateur avant rendu.
  5. **§3 Freins, ligne « Aucune compétence PHP »** et §9 risque « code écrit
     par une IA » : la DSI tient à la continuité de service et la direction a
     une équipe de développement. Présenter PHP/MySQL comme technologie
     standard reprenable par cette équipe, code commenté et testé ; proposer
     une relecture par elle. **Validé (29/09)** avec la formulation au §9 :
     « Une relecture du code par l'équipe de développement de la direction
     peut être proposée » (option, pas engagement). §2.2 et §3 : textes
     proposés en session le 29/09, retenus.
  6. **Mentions de Claude : reformuler, NE PAS effacer** (utilisateur,
     29/09 — remplace la consigne « neutraliser » donnée plus tôt ; la DSI
     sait que Claude construit le code sous la supervision de l'utilisateur).
     Dire explicitement : code conçu et écrit par Claude (assistant IA
     d'Anthropic) **sous la supervision du responsable, qui valide chaque
     évolution et arbitre toutes les décisions**. Retirer seulement les
     tournures défavorables : « Aucune compétence PHP dans l'équipe » (§3),
     « sans relecture humaine » (§9). Procédure l.70 : « demande à Claude »
     peut rester. RGPD l.18 : « rédaction assistée par Claude » peut rester.
  7. **AGORA dans la prise de décision** : quelques lignes au §4 et un
     4e niveau au §8 — « revue contradictoire écrite de chaque décision
     structurante par une seconde session de Claude, indépendante de celle
     qui propose ; **la décision finale revient au responsable, qui
     arbitre** ». Exemples : AG-009 (mesure exigée avant de coder → preuve
     statistique), AG-011, AG-012, AG-014. Pas le bilan chiffré des
     verdicts, ni le détail de ce qui l'a rendue nécessaire (utilisateur :
     rester bref). Paragraphe validé pour le §4 : « La revue contradictoire
     (AGORA) a été instaurée le 21/09/2026 à l'initiative du responsable.
     Toute décision structurante proposée par Claude est déposée dans le
     dépôt et soumise à une seconde session indépendante, qui lit le code et
     ne peut contester ou amender qu'avec une preuve. Le responsable
     arbitre. »
- `manifest-*.json` à retirer, une fois les dernières installations PWA
  désinstallées (seul l'utilisateur en avait).
- **Clé SSH de déploiement en service (29/09/2026)** : secret
  `ALWAYSDATA_SSH_KEY` dans NEWGEN et `ateliers-backups`, clé créée sur le
  serveur puis effacée. Vérifié : déploiement API et copie chiffrée passés
  « par clé » le 29/09. Reste : retirer `ALWAYSDATA_SSH_PASSWORD` des deux
  dépôts quand l'utilisateur le décide (le mot de passe SSH sert encore au
  terminal web).
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
