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

Les fonctions pures du projet sont dans `utils.js`. Le fichier de tests est `utils.test.js`.
Runner : `node --test utils.test.js` (Node.js natif, sans dépendance).

**Quand intervenir :**
Si la session touche à la logique métier (parsing, calcul, normalisation, export),
vérifier les tests avant de commiter.

**Après toute modification de `utils.js` :**
1. Modifier la fonction
2. Exécuter `node --test utils.test.js`
3. Commiter `utils.js` + `utils.test.js` ensemble si un test a dû être mis à jour

**Règle de décision :**
- Test qui échoue après une **correction de bug** → corriger le code, pas le test.
- Test qui échoue après un **changement intentionnel** → mettre à jour le test ET le code dans le même commit.
- Ne jamais supprimer ou désactiver un test pour faire passer le commit.

## 4. Ordre d'intervention à respecter

Pour chaque session de travail :

1. `git pull origin main`
2. Appliquer les modifications
3. Si `utils.js` est modifié : exécuter `node --test utils.test.js` avant de commiter
4. Committer chaque modification séparément avec message conventionnel
5. Pousser sur `main` : `git push origin main`
