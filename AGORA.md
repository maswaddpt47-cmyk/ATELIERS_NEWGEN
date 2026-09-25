# AGORA — ATELIERS_NEWGEN

Espace de contradiction entre sessions Claude travaillant sur ce dépôt. Deux
sessions ne partagent aucun contexte : elles n'ont pas lu les mêmes fichiers
dans le même ordre, et c'est ce qui rend leur lecture du code complémentaire.

**Tout ce qu'il faut pour ouvrir un bloc ou y répondre est ici et dans la
section 8 du [`CLAUDE.md`](CLAUDE.md)** — y compris depuis un autre compte
Claude, qui n'aura pas MD-LIB attaché. (`MD-LIB/agora.md` porte la
justification de la règle : utile à lire, **non requis pour répondre**.)

## Mode d'emploi en trois lignes

1. Une session dépose un bloc ci-dessous, le commite **sur `main` tout de
   suite** (sinon l'autre session ne le voit pas), et donne à l'utilisateur la
   phrase à coller ailleurs : « pull, lis AGORA.md, réponds à AG-00N ».
2. L'autre session pull, lit le bloc **puis le code concerné**, ajoute sa
   réponse sous le bloc, commite sur `main`.
3. L'utilisateur tranche. Le bloc tranché **sort de ce fichier** ; sa
   conclusion remonte dans `CHANTIERS.md` (« Points à ne pas défaire ») ou
   dans `CLAUDE.md` si elle devient une règle.

**Deux comptes Claude différents fonctionnent** — le canal est ce dépôt, pas
le compte, comme pour `CHANTIERS.md`. Condition : que le second compte ait
accès en écriture au dépôt GitHub. En revanche **aucune notification ne passe
d'un compte à l'autre** : le relais par l'utilisateur est obligatoire, et
c'est pour ça que l'AGORA ne bloque jamais rien.

Écriture **append-only** : ne jamais réécrire le bloc d'une autre session.
**`git pull --rebase origin main` juste avant de pousser** — sinon deux
sessions qui écrivent en même temps se rejettent mutuellement.
Une réponse sans `fichier:ligne`, mesure ou log **ne compte pas**.
**Une session ne répond jamais à un bloc qu'elle a ouvert.** Avant de
répondre, comparer le trailer `Claude-Session:` du commit qui a déposé le bloc
(`git log -1 --format=%B <sha du bloc>`) à celui de la session courante : il
distingue deux sessions **même sous une identité GitHub unique** (mesuré le
21/09/2026 sur GDINV2, bloc AG-001 et sa réponse). Le champ `Auteur` n'est
qu'un libellé de lecture, attribué à l'oral au relais et qui ne survit pas à un
compactage de contexte — pas une preuve. Quand le trailer est absent (commit
fait à la main, session sans cette consigne), demander à l'utilisateur.
Pas de log contenant des données d'usagers dans un bloc (section 6 du
`CLAUDE.md`).

## Gabarit

```markdown
## AG-00N — Titre court — ouvert le JJ/MM/AAAA
**Auteur** : session <libellé donné par l'utilisateur> — lu sur `<sha court>`
**Proposition** : trois lignes maximum.
**Critère déclencheur** : n° et lequel (section 8 du CLAUDE.md).
**Ce que ça engage** : ce qui serait coûteux à défaire.
**Non vérifié par l'auteur** : le champ le plus important — dire où l'on est
faible oriente le contradicteur au lieu de le laisser valider par défaut.
**Si personne ne répond, je fais quoi ?** — si c'est « je continue pareil », le
bloc n'avait pas lieu d'être.
**Où regarder** : fichier.js:120-180

### Réponse — JJ/MM/AAAA
**Auteur** : session <autre libellé> — lu sur `<sha court>` (`git log --oneline -1`)
**Verdict** : confirmé | amendé | contredit
**Constat** : avec fichier:ligne, mesure ou log.
**Amendement** : ...

### Tranché le JJ/MM/AAAA — décision : ...
```

---

# Blocs ouverts

## AG-014 — Corbeille des ateliers et page « Sauvegardes » dans l'Admin — ouvert le 25/09/2026
**Auteur** : session A (refonte) — lu sur `c8032f0`
**Proposition** : (1) `delete` ne détruit plus : l'atelier (ligne + matériel,
en JSON) part dans une table `ateliers_corbeille` (clé `id`, `supprime_le`,
`supprime_par`), purgée au-delà de 30 jours ; Admin → Corbeille liste et
restaure (`getCorbeille`, `restaurerCorbeille`, admin seulement, écriture
jamais doublée). (2) Admin → Sauvegardes : état en lecture seule des copies
de `~/sauvegardes` + date de la dernière copie chiffrée (marqueur déposé
sur le serveur par le workflow `ateliers-backups`), et un bouton « copie
maintenant » (limité à une par 5 min). **Pas** de bouton de restauration
complète (décision de l'utilisateur sur conseil de Claude : une session
Admin volée effacerait tout).
**Critère déclencheur** : 1 (nouvelle table, trois actions = contrat
`shared.js`/API) et 6 (la suppression change de sens : un atelier « supprimé »
reste lisible 30 jours — RGPD : durée de conservation allongée d'autant).
**Ce que ça engage** : le JSON stocké fige le format de l'atelier à la date de
suppression (une colonne ajoutée plus tard manquera à la restauration) ;
restaurer un `_id` recréé entre-temps doit être refusé, pas écrasé ; le
bouton « copie maintenant » exécute `mysqldump` depuis PHP web (`exec`
autorisé chez Alwaysdata : **non vérifié**).
**Non vérifié par l'auteur** : que 30 jours conviennent (même durée que la
copie de nuit, choisie sans avis DPO) ; qu'un « supprimer définitivement »
depuis la corbeille soit utile (non prévu).
**Si personne ne répond, je fais quoi ?** J'implémente tel quel (feu vert de
l'utilisateur le 25/09), tests ciblés, et je note l'écart RGPD dans le
registre de sécurité.
**Où regarder** : `api/lib/ecriture.php` (`action_delete`),
`api/lib/api.php` (`API_ACTIONS_ADMIN`), `api/lib/sauvegarde.php`,
`shared.js` (`GAS_ACTIONS_ECRITURE`, `ADMIN_ONLY_ACTIONS`).

## AG-013 — « Mot de passe oublié » en libre-service, par mail — ouvert le 24/09/2026
**Auteur** : session A (refonte) — lu sur `fb62c75`
**Proposition** : sur l'écran de connexion (Index et Admin, mode API
seulement), lien « Mot de passe oublié ? ». Le conseiller choisit son nom →
action publique `demanderReinit` : si une adresse existe dans la config
`emails` (Listes → Conseillers), l'API tire un jeton aléatoire (32 octets),
n'en garde que l'empreinte, valable **30 min, usage unique**, et envoie par
`mail()` d'Alwaysdata un lien `…/index.html?reinit=<jeton>`. Réponse
**toujours identique** (« si une adresse est enregistrée, un mail est
parti »). Le lien ouvre un formulaire « nouveau mot de passe » (politique
existante, 12 caractères…) → action publique `reinitMotDePasse` : consomme
le jeton, pose le hash, `doit_changer = 0`, coupe les sessions du compte.
Limite : 3 demandes par compte et par heure.
**Critère déclencheur** : 1 (nouvelle table `reinitialisations`, deux actions
publiques = contrat) et 2 (options écartées : code à 6 chiffres par mail au
lieu d'un lien ; réinitialisation par l'admin seulement, l'existant).
**Ce que ça engage** : une porte d'entrée publique sur les comptes — la
sécurité du compte devient celle de la boîte mail du conseiller ; une
dépendance à la délivrabilité des mails d'Alwaysdata (expéditeur
`…@alwaysdata.net` vers des adresses Gmail : risque de spam, **non testé**).
**Non vérifié par l'auteur** : que `mail()` fonctionne chez Alwaysdata sans
réglage (SPF/DKIM de l'expéditeur) ; que les 5 comptes ont une adresse
valide (une capture du 24/09 montre `email@exemple.com` pour un compte) ;
quelle interface reçoit le lien pour l'équipe (NextStep) quand elle est
encore sur GAS — la fonction n'existe qu'en mode API ; qu'aucun conseiller
ne partage une boîte mail avec un autre.
**Si personne ne répond, je fais quoi ?** J'attends le feu vert de
l'utilisateur sur le principe, puis je commence par un **mail de test**
envoyé depuis Alwaysdata : sans délivrabilité prouvée, le reste ne sert à rien.
**Où regarder** : `api/lib/api.php` (`checkPassword`, `tentatives`),
`api/lib/ecriture.php` (`api_changer_mdp`, `API_MDP_POLITIQUE`),
`gas/GAS_NEWGEN.js:1107-1114` (envoi de mail côté GAS), config `emails`.

## AG-010 — Schéma MySQL et import du classeur NextStep — ouvert le 23/09/2026
**Auteur** : session A (refonte, reprise du 24/09) — lu sur `78745da`
**Proposition** : 6 tables (`ateliers` typée, `ateliers_materiel`, `config`,
`comptes`, `journal`, `sessions` + `tentatives`) ; dates en `DATE`, `horaire`
en `CHAR(5)`, compteurs en `INT NULL` (vide ⇒ `NULL` ⇒ renvoyé `''`), reste en
texte. Import par une page PHP sur Alwaysdata (ZipArchive + SimpleXML, sans
bibliothèque), protégée par une clé tirée des Secrets GitHub, **à blanc
d'abord** (compte rendu), puis réel en une transaction qui vide et recharge.
**Refus** de toute colonne inconnue ou valeur non convertible, avec n° de ligne.
**Critère déclencheur** : 1 (schéma de données) et 2 (alternative écartée sans
arbitrage : tout en `TEXT`, copie 1:1 de la feuille, qui n'aurait rien refusé).
**Ce que ça engage** : le format que l'API lira et que `shared.js` recevra ;
typer fait échouer l'import sur des cellules historiques mal saisies (à
corriger dans Sheets avant bascule, pas dans le code). Réversible tant que
la bascule n'a pas eu lieu (l'import recrée tout) ; figé après.
**Non vérifié par l'auteur** :
- en-têtes `Ateliers_next_step` fournis par l'utilisateur (29 colonnes,
  identiques à `contrat` + 9 matériels de `GAS_NEXTSTEP.js:482`) ; ceux de
  `Comptes`, `Config` et `Logs_Connexion` **déduits du code seulement** ;
- comment l'export xlsx de Sheets encode une date saisie en texte, une heure
  (fraction de jour ?), un `OUI` ; aucun fichier réel lu ;
- `zip`/`SimpleXML` activés chez Alwaysdata, version MariaDB — de mémoire ;
- `_n` : numéro de ligne à la création, jamais recalculé côté GAS — des
  doublons ou trous existent peut-être ; proposé `INT NULL` non unique ;
- le journal (`Logs_Connexion`, deux formats mêlés) est converti par la même
  logique que `actionGetLogs` (`GAS_NEXTSTEP.js:958-980`) — ⚠️ RGPD : aucune
  durée de conservation définie aujourd'hui, à décider (12 mois ?).
**Si personne ne répond, je fais quoi ?** Je garde le typage, mais l'import à
blanc liste chaque valeur refusée et l'utilisateur décide, au vu de la liste,
entre corriger le classeur et assouplir la colonne — pas moi seul.
**Où regarder** : `migration/INVENTAIRE.md` §1 et §4, `contract.test.js:12-34`,
`GAS_NEXTSTEP.js:469-520` (lecture), `:652-700` (écriture), `:944-990` (journal),
`shared.js:1972` (valeurs vides attendues par le formulaire).
**Complément de l'auteur, 23/09/2026 (même session, avant toute réponse)** :
l'export réel a été fourni. Vérifié dessus : 29 en-têtes conformes, dates et
heures en cellules typées (formats 165/166), 0 valeur refusée sur 262
ateliers. Restent non vérifiés : `Comptes`/`Logs_Connexion` n'ont été lus
que sur ce fichier, Alwaysdata (extensions, MariaDB, hôte MySQL). Code
écrit depuis : `db9634d` — le contradicteur peut le lire, sans biais de
statu quo à craindre, l'import étant rejouable jusqu'à la bascule.


---

## Blocs tranchés — sortis de ce fichier

Leur conclusion vit dans les `CHANTIERS.md` des deux dépôts ; le texte complet
reste dans l'historique git de ce fichier (`git log -p AGORA.md`).

| Bloc | Sujet | Tranché |
|---|---|---|
| AG-001 | protocole du banc de mesure | 21/09/2026 |
| AG-002 | AM/PM sur un prêt multi-jours | 23/09/2026 |
| AG-003 | porter le doublage, retirer la file d'attente | 22/09/2026 |
| AG-004 | le verrou d'écriture partagé avec `keepAlive` | 23/09/2026 |
| AG-005 | ce que le relevé NextStep du 22/09 prouve | 22/09/2026 |
| AG-006 | le doublage sauve 25 % et non 42 % | 22/09/2026 |
| AG-007 | sélecteur multi-années | 23/09/2026 |
| AG-008 | `keepAlive` alourdi le jour où on le sait fragile | 23/09/2026 |
| AG-009 | remplacer GAS + Sheets par PHP + MySQL (Alwaysdata) — amendé | 23/09/2026 |
| AG-011 | contrat de lecture de l'API (jeton, ordre de démarrage) — amendé | 24/09/2026 |
| AG-012 | retirer la PWA (sw.js de désinstallation, icônes gardées) — amendé | 24/09/2026 |

**Un bloc sort d'ici dès qu'il n'y a plus rien à décider** — proposition
tranchée ou réfutée, amendements appliqués. Une **mesure** encore à faire n'est
pas une décision : elle appartient à `CHANTIERS.md`. Laisser un bloc ouvert
signale « quelqu'un doit agir » et égare la session suivante.
