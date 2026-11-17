# Chantiers en cours — ATELIERS_NEWGEN

État au **17/11/2026** (relu par « pull » à 06:00), bases `main` : NEWGEN `0aee1b5`, NextStep `a73f5c0`, MD-LIB `58a61ca`.
Arrivé depuis : passations Codex (documentation seule) ; refonte de MD-LIB (16/11, règles conservées). Fait à ce pull :
annonce AGORA « publiée dans <dépôt> » et relecteur de l'autre éditeur (Claude ↔ Codex) dans `CLAUDE.md`, `AGORA.md` et
`AGENTS.md` des deux projets (MD-LIB `agora.md` §4 et §12). Reprise : actualiser les deux `main`
(`fetch` + `merge --ff-only`), relire les consignes et ce fichier ; aucun brouillon à réappliquer. Revues closes jusqu'à
AG-062 : ne pas les relancer d'office. **Autorisations ChatGPT non résolues** : confirmation `create_tree` encore vue
malgré « Autoriser toutes les actions », cause non établie.

**Passation Codex — 16/11** : aucune nouvelle règle à inscrire ; concision, contrôles proportionnés, revue unique et
**✅ TÂCHE TERMINÉE** sont déjà couverts par les consignes communes et `AGENTS.md`.
Les correctifs d'AG-061/062 publiés après les réponses de Codex sont consignés ci-dessous sans nouveau réaudit.
Aucun test produit relancé pour cette passation documentaire ; ne pas reprendre les scripts temporaires hors Git.
Le recomptage indépendant du bilan est publié dans le dépôt privé `ateliers-backups` (`9c430cb`) ; ses essais
PHP/MariaDB sur données fictives et les réserves restent dans AGORA. Les vérifications régulières sont déjà
programmées : conserver le suivi du bilan ci-dessous et la limite de l'annulation d'AG-062.

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

**Nouveautés** : prochain `id` **41** (bandeau « nouvelle version » non annoncé, 16/11) ; jamais `admin: true` ; jamais 12, 16, 19, 24, 25, 26 ni 29 (publiés puis retirés).

1. **Bac à sable NextStep** (AG-033) : à éprouver — pages NextStep dans `coupure-urgence.yml` puis coupure réelle ;
   requêtes vers `api-sandbox` vues au navigateur ; avis fictif par le QR (piste 3 du 15/11, en attente).
2. **Supports par atelier (pptx, docx) — mis de côté le 04/10** : supports sur un réseau interne, qu'une page https
   n'ouvre pas en `file://` (hypothèse) ; il faudrait SharePoint/OneDrive ou Alwaysdata. Bloc AGORA au choix (critère 1).
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
- **Durée (AG-017, AG-062)** : absente ou vide dans un envoi = la valeur en base reste ; aucun écran ne vide une durée.
- **`check-chantiers.sh`** (copie de référence ici) : alerte au-delà de 300 lignes **ou 30 Ko** (MD-LIB 8ter, 16/11).
- **Stockage navigateur préfixé** (`lsKey`, `APP_NS`) : origine `github.io` partagée. Couche réseau client gardée (4G terrain).

## Pistes d'amélioration

Règle 22. Ne pas reproposer une piste écartée sans fait nouveau. **En attente** : la 3 du 15/11 (point 1 de « Reste ouvert »).

**Écartées** (date — piste — raison)
- 25/10 — tableaux thématiques × communes et partenaires × conseillers — alourdissent la page, jugés inutiles.
- 03/10 — abonnement agenda pour les partenaires ; tri des onglets par usage ; aide « Premiers pas » — « pour l'instant ».
- 10/10 — heure pré-remplie au clic sur l'Agenda — gardée sans correction (horaires variés).
- 10/10 — progression début → fin de cycle dans les avis — « pour l'instant », à reprendre quand un cycle sera bouclé.
- 12/10 — synchroniser l'import Outlook PC ↔ téléphone — « trop compliqué » : passerait par le serveur (donnée personnelle).
- 12/10 — envoi du .ics par mail ou bouton de partage — « reste manuel » : une seule étape gagnée.
