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

## AG-002 — La finesse AM/PM ne vaut que pour un prêt d'une seule journée — ouvert le 22/09/2026
**Auteur** : session 01Dq1xi3 — lu sur `315e218`
**Proposition** : `occupeCreneauMateriel` ne restreint l'occupation à une
demi-journée que si `debut === fin`. Dès qu'un prêt court sur plusieurs jours,
il occupe AM et PM de chaque jour, y compris ceux des extrémités.
**Critère déclencheur** : n° 1 — ferme une porte. C'est le contrat de calcul
partagé entre `logic.js` et `shared.js`, dans les deux applis.
**Ce que ça engage** : tout code qui lira `demi` sur un prêt multi-jours. Et
le choix inverse (appliquer l'AM/PM aux extrémités d'un prêt long) serait
coûteux à rattraper une fois des conflits arbitrés sur cette base.
**Non vérifié par l'auteur** : le cas réel « prélèvement la veille au soir,
atelier le lendemain matin, retour le surlendemain » n'a été confronté à
aucune donnée de production. Je suppose qu'un conseiller qui garde le
matériel une nuit le mobilise aussi les demi-journées d'extrémité, sans
l'avoir demandé à l'utilisateur. Deuxième point non vérifié : le repli
« demi inconnue → journée entière » peut faire réapparaître des alertes sur
les entrées importées sans `ampm`, dont je ne connais pas le volume.
**Si personne ne répond, je fais quoi ?** — je laisse en l'état : le
comportement est prudent (il sur-réserve plutôt que de sous-réserver) et se
change en une ligne.
**Où regarder** : `logic.js` — `occupeCreneauMateriel`, `demiJourneeAtelier` ;
`shared.js` — la copie miroir et la frise.



## AG-004 — Le verrou d'écriture partage le verrou de script avec keepAlive — ouvert le 22/09/2026
**Auteur** : session 01Dq1xi3 — lu sur `72c508e`
**Proposition** : `_avecVerrouEcriture` enveloppe `actionSaveEntry`,
`actionSaveMany` et `actionDelete` dans `LockService.getScriptLock()
.waitLock(20000)` / `releaseLock()` en `finally`, dans les deux copies GAS
(application de l'amendement d'AG-003). Non déployé, bandeau en tête des deux
fichiers.
**Critère déclencheur** : n° 2 — deux options envisagées, une seule écrite, et
ce deux fois :
1. **Contention avec `keepAlive` (NEWGEN uniquement).** Apps Script n'offre
   que trois verrous (script / user / document), **aucun verrou nommé** :
   `keepAlive` prend donc le même (`gas/GAS_NEWGEN.js`, `tryLock(0)`). Option
   retenue : accepter la contention. Option écartée : retirer le verrou de
   `keepAlive`, ce qui réintroduirait les exécutions empilées que ce verrou
   avait justement supprimées.
2. **Délai de 20 s**, au-delà du plafond client en écriture (12 s). Option
   écartée : 10 s, aligné sur la patience du client. Retenu 20 s pour couvrir
   un `saveMany` en cours (plafond client 25 s) qui tiendrait le verrou. Ce
   n'est pas un rallongement de plafond au sens du `CLAUDE.md` : aucun écran
   d'attente ne s'allonge, le client abandonne toujours à 12 s, et une
   écriture terminée après cet abandon est retrouvée par son `_id` au rejeu.
**Ce que ça engage** : le comportement des écritures sous charge, dans un
script déployé à la main. Retour arrière = un redéploiement de la version
précédente (30 s, sans changement d'URL) — donc peu coûteux, mais il faut
s'en apercevoir.
**Non vérifié par l'auteur** — c'est le point faible :
1. **Aucune contention mesurée.** L'estimation « moins de 1 pour cent des
   écritures attendent, ~1-2 s » vient du fait qu'un `keepAlive` log une durée
   de l'ordre de la seconde toutes les 5 min. **Hypothèse non vérifiée**,
   jamais recoupée dans les Exécutions.
2. Je n'ai pas vérifié si l'attente de `waitLock` est comptée dans le temps
   d'exécution facturé au quota Apps Script.
3. Sens écriture vers `keepAlive` : le cache ne sera pas réchauffé ce
   passage-là. Je suppose que c'est sans effet (le passage suivant le fait, et
   une écriture invalide le cache de toute façon), **sans l'avoir vérifié**.
4. `actionSaveMany` prend **un seul** verrou pour tout le lot plutôt qu'un par
   entrée. Plus court en attente, mais un lot long tient le verrou plus
   longtemps. Aucun lot réel chronométré.
**Si personne ne répond, je fais quoi ?** — l'utilisateur déploie tel quel le
23/09/2026. Le bloc sert à savoir quoi surveiller après, pas à bloquer : sans
verrou, un `delete` concurrent peut supprimer l'atelier voisin, ce qui est
pire que n'importe laquelle de ces inconnues.
**Où regarder** : `gas/GAS_NEWGEN.js` — `_avecVerrouEcriture`, `actionSaveMany`,
`keepAlive` ; `gas/GAS_NEXTSTEP.js` — mêmes fonctions, `keepAlive` **sans**
verrou (pas de contention de ce côté) ; `gas/README.md` des deux dépôts.

_(aucun — AG-001 tranché le 21/09/2026, conclusions remontées dans
`CHANTIERS.md` §1 et « Points à ne pas défaire », code dans `banc/`.)_
