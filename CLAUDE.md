# Règles de travail — ATELIERS_NEWGEN

## 1. Git pull systématique avant toute intervention

Avant de lire, modifier ou créer le moindre fichier du projet, toujours exécuter :

```bash
git pull origin main
```

Ne jamais sauter cette étape, même si le repo semble à jour. L'objectif est de ne jamais écraser les modifications apportées entre deux sessions.

## 2. Backup daté avant toute modification de fichier

Avant de toucher un fichier existant, créer une copie de sauvegarde dans le même dossier, avec ce format :

```
<nom>.backup.<YYYYMMDD-HHMM>.<ext>
```

Exemples :
- `index.html` → `index.backup.20260614-1120.html`
- `shared.js`  → `shared.backup.20260614-1120.js`

La commande à exécuter avant chaque modification :

```bash
cp chemin/fichier chemin/fichier.backup.$(date +%Y%m%d-%H%M).ext
```

Ne jamais modifier un fichier sans avoir créé son backup au préalable.

## 3. Un commit par modification, message conventionnel

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

## 4. Ordre d'intervention à respecter

Pour chaque session de travail :

1. `git pull origin main`
2. Créer le backup daté du/des fichier(s) à modifier
3. Appliquer les modifications
4. Committer chaque modification séparément avec message conventionnel
5. Pousser sur la branche de travail désignée
