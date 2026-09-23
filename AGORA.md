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

## AG-009 — Remplacer GAS + Sheets par PHP + MySQL chez Alwaysdata — ouvert le 23/09/2026
**Auteur** : session A — lu sur `667d1e9`
**Proposition** : réécrire le backend en API PHP reproduisant **1:1** les
~21 actions GAS actuelles, sur MySQL, hébergée chez Alwaysdata (compte au nom
de l'utilisateur), déployée par GitHub Action à chaque push. Le code convergé
(NEWGEN) est publié **à l'URL de NextStep** le jour de la bascule, après copie
du classeur NextStep. Détail et réponses de l'utilisateur :
`CHANTIERS.md`, section « 🧭 Refonte d'architecture ».
**Critère déclencheur** : 1 (ferme une porte : schéma de données, nouvelle
dépendance d'hébergement) et 6 (migration de données de production, PWA
installées de l'équipe).
**Ce que ça engage** : un sous-traitant d'hébergement, un schéma SQL, la
sortie de Google Sheets (plus de consultation du classeur — l'utilisateur dit
que personne ne s'en sert), une bascule de production unique.
**Non vérifié par l'auteur** :
- **Que la perte d'appels disparaisse.** Hypothèse : elle vient de la
  redirection `/exec` de GAS, absente d'un PHP classique. Aucune mesure.
- Le périmètre GAS exact à reproduire, au-delà du routage
  (`gas/GAS_NEXTSTEP.js:560-580`) : `MailApp.sendEmail`
  (`GAS_NEXTSTEP.js:1157`), déclencheur `onChange`
  (`GAS_NEXTSTEP.js:265`, devient inutile sans classeur), côté NEWGEN
  alertes de retard quotidiennes et sauvegarde Drive
  (`GAS_NEWGEN.js:1072`, `:1083`) → cron Alwaysdata + envoi SMTP, non testés.
- Offre gratuite Alwaysdata actuelle (taille, PHP/MySQL, accès SSH pour le
  déploiement par Action) et transférabilité du compte : lues de mémoire.
- Qu'une PWA installée survive au remplacement du code **à la même URL**
  (manifest, `start_url`, `sw.js`) : raisonnement, pas essai.
- Le stockage des mots de passe côté GAS et leur reprise en PHP
  (`password_hash`) : pas relu.
**Si personne ne répond, je fais quoi ?** Je commence par ce qui ne ferme
rien : schéma SQL et API en local testés contre `contract.test.js`, sans
toucher à la production. La bascule, elle, attend une réponse ou l'accord
explicite de l'utilisateur après relecture de ce bloc.
**Où regarder** : `gas/GAS_NEWGEN.js` et
`ateliers-cd47_NextStep/gas/GAS_NEXTSTEP.js` (routage et services Google),
`shared.js` (`GS_URL`, `gasAppel`), `contract.test.js`, `sw.js`,
`manifest-app.json`.


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

**Un bloc sort d'ici dès qu'il n'y a plus rien à décider** — proposition
tranchée ou réfutée, amendements appliqués. Une **mesure** encore à faire n'est
pas une décision : elle appartient à `CHANTIERS.md`. Laisser un bloc ouvert
signale « quelqu'un doit agir » et égare la session suivante.
