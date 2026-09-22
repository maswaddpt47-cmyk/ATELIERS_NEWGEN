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

## AG-003 — Porter les lectures doublées sur NextStep, et retirer sa file d'attente — ouvert le 22/09/2026
**Auteur** : session 01Dq1xi3 — lu sur `76e8174`
**Proposition** : la mesure du 22/09 tranche le §1 en faveur du doublage.
Porter `gasLectureDoublee` sur NextStep et **supprimer `_gasQueue`** : les deux
mécanismes sont incompatibles, un doublon mis en file derrière son jumeau ne
partirait qu'après l'abandon de celui-ci.
**Critère déclencheur** : n° 1 (ferme une porte — contrat de la couche réseau,
partagé par les deux applis) et n° 6 (coût côté usager : l'écriture d'un
atelier passe par là).
**Ce que ça engage** : la stratégie d'appel de la production. Revenir en
arrière après des semaines d'usage voudrait dire réécrire `reseau.test.js` et
reperdre la mesure qui a servi à trancher.
**Mesure à l'appui** : 124 paires appariées, backend NEWGEN, 07h26→17h15.
McNemar χ² = 10,32 (significatif à 1 %) ; salves incomplètes 18,4 % (file)
contre 4,0 % (doublage) ; durée appariée +11,8 s pour la file, IC95
[+7,8 ; +15,9] ; 10 % des salves file dépassent 60 s, aucune côté doublage.
**Non vérifié par l'auteur** — trois angles morts, le troisième est le plus
sérieux :
1. La série mesure le **backend NEWGEN**. Rien ne prouve que le déploiement
   Apps Script de NextStep se comporte pareil. Indice seulement : 39 % de
   pertes relevées sur NextStep le 22/09 au matin, cohérent avec les 30-38 %
   d'ici.
2. Le doublage coûte **+26 % d'appels** (5,4 contre 4,3 par salve). Sur le
   quota Apps Script d'un compte qui héberge déjà deux scripts, je n'ai pas
   chiffré ce que ça donne à l'échelle d'une équipe entière.
3. **`_gasQueue` de NextStep sérialise TOUS les appels, écritures comprises.**
   Le retirer supprime cette sérialisation. NEWGEN garantit qu'une écriture
   n'est jamais *doublée* (`doubler = !ecriture`, `shared.js:885`) mais **rien
   n'y sérialise deux écritures concurrentes** — la garantie repose sur le
   fait que l'interface n'en lance qu'une à la fois. Or le `CLAUDE.md` des
   deux projets inscrit « les écritures restent séquentielles ET jamais
   doublées » comme invariant, au motif que deux `appendRow` concurrents
   créent un atelier en double. **Je ne sais pas si NEWGEN respecte
   réellement cet invariant, ou s'il ne l'a jamais violé par chance.** À
   vérifier avant de retirer la file : un `saveMany` suivi d'un `saveEntry`,
   ou deux onglets, suffisent-ils à faire partir deux écritures ensemble ?
**Si personne ne répond, je fais quoi ?** — je ne porte rien. Sans réponse sur
le point 3, le portage échangerait un problème de latence mesuré contre un
risque de doublon en base non mesuré.
**Où regarder** : NextStep `shared.js` — `_gasQueue`, `gasUnAppel` ;
NEWGEN `shared.js:864-905` — `GAS_SANS_DOUBLON`, `gasAppel`,
`gasLectureDoublee` ; `reseau.test.js` des deux côtés.


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


_(aucun — AG-001 tranché le 21/09/2026, conclusions remontées dans
`CHANTIERS.md` §1 et « Points à ne pas défaire », code dans `banc/`.)_
