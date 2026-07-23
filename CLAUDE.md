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
