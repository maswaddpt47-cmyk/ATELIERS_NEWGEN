# Règles de travail — ATELIERS_NEWGEN

## 1. Git pull systématique avant toute intervention

Avant de lire, modifier ou créer le moindre fichier du projet, toujours exécuter :

```bash
git pull origin main
```

Ne jamais sauter cette étape, même si le repo semble à jour. L'objectif est de ne jamais écraser les modifications apportées entre deux sessions.

## 2. Un commit par modification, message conventionnel

Chaque modification doit faire l'objet d'un commit séparé. Pas de commit fourre-tout.

Format du message :
```
type: description courte de ce qui a changé
```

Types autorisés : `feat`, `fix`, `refactor`, `style`, `docs`, `chore`

Exemples :
- `feat: ajouter export PDF dans la vue historique`
- `fix: corriger le calcul des présents dans bingo`
- `refactor: extraire logique calendrier dans admin_config.js`

## 3. Tests unitaires — règle obligatoire

Deux fichiers de tests, un runner par fichier (Node.js natif, sans dépendance) :

| Fichier source | Fichier de tests | Runner |
|---|---|---|
| `utils.js` — fonctions bas niveau (dates, ICS, communes) | `utils.test.js` | `node --test utils.test.js` |
| `logic.js` — logique métier (KPI, validation, filtres, matériel) | `logic.test.js` | `node --test logic.test.js` |
| Format des données envoyées à GAS | `contract.test.js` | `node --test contract.test.js` |

**Quand intervenir :**
Si la session touche à la logique métier (parsing, calcul, normalisation, validation, export),
vérifier les trois suites avant de commiter.

### Tests navigateur (Playwright, job `e2e` de la CI)

| Fichier de tests | Ce qu'il vérifie | Runner |
|---|---|---|
| `sandbox.test.js` | `utils.js` + `logic.js` se chargent dans un navigateur, globals présents | `node sandbox.test.js` |
| `e2e.test.js` | `index.html` et `admin.html` se chargent et chaque onglet s'ouvre sans erreur JS (GAS et CDN mockés) | `node e2e.test.js` |

Ces deux runners exigent `npm ci` (React UMD servi depuis `node_modules`) et un
Chromium : celui préinstallé en local, sinon `npx playwright install chromium`.

**Pourquoi ils sont indispensables :** les trois suites Node ne testent que des
fonctions pures. Elles passent même quand `shared.js` lève une erreur au
chargement et laisse les deux pages blanches — un point-virgule manquant devant
une IIFE suffit. Seul le job `e2e` voit ce genre de casse, et il bloque le
déploiement avant qu'elle n'atteigne GitHub Pages.

**À exécuter avant tout commit touchant `shared.js`, `app.js` ou `admin_app.js`.**

**Économie de tokens/quota — pas par défaut sur les changements mineurs :**
`node --check` (syntaxe) reste systématique dans tous les cas — c'est lui qui
attrape la classe de bug (point-virgule manquant, IIFE cassée) qui justifiait
`e2e` à l'origine. Mais `sandbox.test.js`/`e2e.test.js` ne sont **pas**
à relancer par défaut pour un changement mineur (texte, style, ajout d'un
élément UI sans nouvelle logique) — l'utilisateur vérifie lui-même en direct,
et la CI relance `e2e` à chaque push de toute façon (bloque le déploiement si
ça casse). À exécuter localement avant de commiter dès que le changement
touche à un comportement : nouveau flux d'authentification, nouvelle action
GAS, changement d'état/de flux, ou en cas de doute — demander à l'utilisateur
si l'un des deux n'est pas sûr du niveau de risque.

**Après toute modification de `utils.js` ou `logic.js` :**
1. Modifier la fonction
2. Exécuter le runner correspondant
3. Commiter le fichier source + le fichier de tests ensemble si un test a dû être mis à jour

**Si le format d'un entry change (nouveau champ, type modifié) :**
Mettre à jour `contract.test.js` dans le même commit.

**Règle de décision :**
- Test qui échoue après une **correction de bug** → corriger le code, pas le test.
- Test qui échoue après un **changement intentionnel** → mettre à jour le test ET le code dans le même commit.
- Ne jamais supprimer ou désactiver un test pour faire passer le commit.

**La CI bloque le déploiement si un test échoue.** Un push cassé ne déploie jamais.

## 4. Ordre d'intervention à respecter

Pour chaque session de travail :

1. `git pull origin main`
2. Appliquer les modifications
3. Si `utils.js`, `logic.js` ou le format entry change : exécuter les runners avant de commiter
4. Committer chaque modification séparément avec message conventionnel
5. Pousser sur `main` : `git push origin main`

**Important — branche imposée par la plateforme :**
Claude Code sur le web impose parfois une branche de travail dédiée (ex. `claude/code-review-*`).
Dans ce cas, merger systématiquement dans `main` et pousser à la fin de chaque session :
```bash
git checkout main
git merge <branche> --no-ff
git push origin main
```
Le déploiement GitHub Pages ne se déclenche que sur `main`. Travailler uniquement sur une branche de feature rend les changements invisibles en production.

## 5. Backend GAS

Le script Google Apps Script (URL dans `shared.js` → `GS_URL`) n'est pas
déployé depuis ce repo — pas d'API de push GAS, le déploiement reste manuel
via l'éditeur script.google.com. `gas/GAS_NEWGEN.js` en est une copie de
référence versionnée (diffable), à tenir à jour manuellement après chaque
déploiement confirmé — voir `gas/README.md` pour la procédure.

## 6. Règles de collaboration avec Claude

Extrait du guide de collaboration multi-projets, adapté pour ce dépôt.

### Côté Claude — priorité haute

1. Ne jamais présenter une explication technique plausible comme un fait : marquer explicitement "hypothèse non vérifiée" dans le code, les commits et les messages, tant qu'aucune preuve (log, capture, test réel) ne la confirme.
2. Ne jamais déclarer "c'est réparé", "c'est en ligne" ou "testé" sans vérification réelle du chemin critique (déploiement GitHub Actions, rendu navigateur, test exécuté) — pas une lecture de code qui "devrait marcher".
3. Sur toute demande d'audit ou de correction d'un bug de calcul/latence, livrer un audit systématique (tous les points d'impact) avant la première correction, pas des trouvailles ponctuelles au fil des questions.
4. Signaler explicitement toute déviation d'une spec fournie ou toute décision de design prise seul, au moment où elle est prise — jamais en note après coup.
5. Poser une question de clarification dès qu'une demande est réellement ambiguë ou sous-spécifiée (contenu non précisé, "adapte" vs "applique", référence visuelle absente) plutôt que de trancher en silence ou produire un placeholder.
6. Sur tout appel Bash touchant un repo précis en contexte multi-repo, utiliser `cd /chemin/complet &&` systématiquement ; vérifier `git status`/`git log` et la cohérence CLAUDE.md vs instructions de session avant d'agir, pas après.
7. Toujours faire un `git pull origin main` avant de lire ou modifier le moindre fichier, même si le repo semble à jour — l'oubli est une cause récurrente d'écrasement de travail. Respecter la politique de push définie plus haut (push direct sur `main`, sauf instruction de session explicite contraire) et signaler tout conflit entre les deux avant d'agir, pas après.
8. Après toute reprise de session ou résumé de contexte, relire l'état réel du fichier concerné avant de le modifier ou de le renvoyer — ne jamais présumer qu'un correctif précédent est encore en place.
8bis. Utiliser des dates explicites (JJ/MM ou JJ/MM/AAAA) plutôt que des termes relatifs ("hier", "aujourd'hui", "la semaine dernière", "demain") : la perception du temps de Claude vient d'un contexte injecté en début de session, pas d'une horloge en temps réel — elle devient peu fiable sur une session qui s'étale sur plusieurs jours ou plusieurs reprises.
9. Avant de pousser un changement visuel (CSS/layout), vérifier mentalement les interactions connues à risque (stacking context, overflow, position sticky/fixed) sur les zones sensibles existantes.
10. Sur tout problème réseau/GAS qui dure plus de 3 itérations : demander une capture Network DevTools ou les Exécutions GAS avant de continuer à supposer.
11. Vérifier l'état exact du déploiement GAS (version + URL active dans `shared.js` → `GS_URL`) en début de session dès qu'un bug réseau est signalé.

**Bonnes pratiques à maintenir**

12. Continuer à demander l'avis avant toute action à fort impact (déploiement, architecture, migration de données) et exécuter vite dès validation courte reçue.
13. Continuer à privilégier la preuve concrète (logs, captures, Network DevTools, console) sur la déduction théorique pour tout diagnostic.

### Côté utilisateur — priorité haute

1. Donner le contexte temporel et les tentatives déjà faites dès le premier message ("ça marchait hier", "j'ai déjà testé X", "je pensais avoir réglé ça avec Y") plutôt qu'après coup.
2. Pour un bug visuel, "bizarre" ou réseau, ajouter une ligne de description du symptôme précis, une capture annotée ou le Network DevTools plutôt qu'une formule vague.
3. Signaler explicitement en début de message tout changement d'état fait hors session (redéploiement GAS, changement d'URL, config, branche renommée, settings modifiés).
4. Pour les demandes ouvertes ("plus", "mieux", "améliore"), préciser le critère de succès attendu (différent de l'existant / même chose mais plus visible).
5. Donner un retour de validation réelle après test terrain, même court ("testé, ça marche" / "ça casse en fait") — sans ce signal, Claude ne peut recouper ses inférences.
6. Quand on revient en arrière, préciser ce qui est conservé vs jeté — "on revient à hier" sans liste efface du travail potentiellement utile.

**Bonnes pratiques à maintenir**

7. Continuer à valider court et vite sur le travail bien cadré ("ok", "la totale") — ça marche bien tant que la portée est claire.
8. Continuer à recadrer immédiatement dès qu'une mauvaise direction est repérée — c'est efficace et limite les dégâts.
