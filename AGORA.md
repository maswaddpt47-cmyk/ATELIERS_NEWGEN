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

### Sincérité — trois contraintes contre la politesse

Douze « amendé » d'affilée (total sous le tableau des blocs tranchés) : un
contradicteur qui n'emploie jamais les deux autres verdicts a cessé de
contredire — il rend un service de politesse qui donne une fausse garantie.

1. **« Amendé » n'est valable que s'il nomme ce qui serait faux, manquant ou
   coûteux si la proposition était appliquée telle quelle.** Un amendement qui
   ne change ni le code, ni une décision, ni un chiffre n'est pas un
   amendement : le verdict est **« confirmé »**.
2. **« Confirmé » est une réponse pleine et utile**, pas un aveu d'inutilité :
   elle libère l'auteur pour agir, et c'est souvent ce qu'on attend d'elle. Ne
   jamais chercher un amendement pour justifier sa présence.
3. **Aucune appréciation de la proposition ni de son auteur** — ni compliment,
   ni « bien vu », ni « solide ». Une réponse commence par un constat : le
   compliment est le véhicule de la complaisance.

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

_(aucun)_

## Blocs tranchés — sortis de ce fichier

Leur conclusion vit dans les `CHANTIERS.md` des deux dépôts ; le texte complet
reste dans l'historique git de ce fichier (`git log -p AGORA.md`).

| Bloc | Sujet | Tranché |
|---|---|---|
| AG-001 | protocole du banc de mesure — amendé | 21/09/2026 |
| AG-002 | AM/PM sur un prêt multi-jours — sans réponse, tranché par l'utilisateur (laisser tel quel) | 23/09/2026 |
| AG-003 | porter le doublage, retirer la file d'attente — amendé | 22/09/2026 |
| AG-004 | le verrou d'écriture partagé avec `keepAlive` — amendé | 23/09/2026 |
| AG-005 | ce que le relevé NextStep du 22/09 prouve — amendé | 22/09/2026 |
| AG-006 | le doublage sauve 25 % et non 42 % — amendé | 22/09/2026 |
| AG-007 | sélecteur multi-années — amendé | 23/09/2026 |
| AG-008 | `keepAlive` alourdi le jour où on le sait fragile — amendé | 23/09/2026 |
| AG-009 | remplacer GAS + Sheets par PHP + MySQL (Alwaysdata) — amendé | 23/09/2026 |
| AG-011 | contrat de lecture de l'API (jeton, ordre de démarrage) — amendé | 24/09/2026 |
| AG-012 | retirer la PWA (sw.js de désinstallation, icônes gardées) — amendé | 24/09/2026 |
| AG-010 | schéma MySQL et import du classeur — sans réponse, réalisé sur feu vert de l'utilisateur (import du 25/09, verrouillé) | 26/09/2026 |
| AG-013 | « mot de passe oublié » par mail — sans réponse, réalisé sur feu vert de l'utilisateur (envoi de mail depuis Alwaysdata prouvé par l'essai des rappels du 25/09 ; tests RGPD-06/10/11) | 26/09/2026 |
| AG-014 | corbeille + page Sauvegardes dans l'Admin — amendé (numéro gardé, transaction, purge à la connexion, copies chiffrées 90 j ; bouton de copie gardé, prouvé en production) | 25/09/2026 |
| AG-015 | parité NEWGEN/NextStep par un test « cliquet » — amendé (grain : arbre et toutes instructions, un seul script dans NEWGEN, statuts `voulu`/`à aligner`, non bloquant ; lot 0 : NEWGEN charge `logic.js`) | 26/09/2026 |

**Au 27/09/2026, sur 15 blocs (AG-001 à AG-015) : 12 amendés, 0 confirmé, 0 contredit, 3 clos sans réponse** (AG-002, AG-010, AG-013). Recompté sur l'historique git le 27/09/2026 ; le total précédent oubliait AG-002.
Douze « amendé » d'affilée ne sont pas un bilan flatteur, c'est un signal — voir
« Sincérité » plus haut. Tenir ce total à jour à chaque bloc qui sort.
**Le total est une alerte, pas un objectif** : ne jamais rendre « confirmé »
pour casser la série — le verdict découle de la contrainte 1 appliquée au
bloc. Une série se juge en relisant ce que chaque « amendé » a changé (code,
décision, chiffre) ; celui qui n'a rien changé était un « confirmé ».

**Un bloc sort d'ici dès qu'il n'y a plus rien à décider** — proposition
tranchée ou réfutée, amendements appliqués. Une **mesure** encore à faire n'est
pas une décision : elle appartient à `CHANTIERS.md`. Laisser un bloc ouvert
signale « quelqu'un doit agir » et égare la session suivante.
