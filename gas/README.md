# GAS_NEWGEN.js — copie de référence

### ✅ v11.38 déployée le 23/09/2026

Vide le cache de l'appli quand on modifie le classeur **à la main**, y
compris quand on **supprime des lignes** (incident du 23/09/2026 : doublons
supprimés dans le classeur, encore affichés 10 min dans l'appli).

1. Coller `gas/GAS_NEWGEN.js` dans l'éditeur, **Déployer → Gérer les
   déploiements → ✏️ → Nouvelle version**.
2. Menu **Exécuter** → `installerTriggerChangement` → Exécuter (une seule
   fois ; Google demande d'autoriser l'accès au classeur, accepter).
3. Vérifier : supprimer une ligne de test dans le classeur, puis cliquer sur
   Sync dans l'appli — la ligne doit disparaître tout de suite.
4. Me dire « déployé ».

## ✅ v11.37 déployée le 23/09/2026

Confirmé par l'utilisateur : `testerSecuriteDoGet` ok, enregistrement et
suppression ok. Contient v11.35 (verrou d'écriture serveur), v11.36 et v11.37
(`keepAlive` sans verrou, AG-004). Les sections ci-dessous restent comme
référence.

| | Avant | Après |
|---|---|---|
| Deux `saveEntry` simultanés, même `_id` | peuvent créer **deux lignes** | une seule |
| Deux `delete` simultanés | le second peut supprimer **l'atelier voisin** (index décalé) | impossible |
| Latence de connexion | inchangée | **inchangée** |

`keepAlive` ne partage **plus** le verrou de script (v11.37). Les mails
« Summary of failures » de NextStep montrent trois `keepAlive` bloqués
**8 min** par la plateforme les 19-20/09/2026 : avec le verrou, chaque
blocage aurait refusé toutes les écritures pendant 8 min.

### Marche à suivre (≈ 3 min)

1. Ouvrir <https://script.google.com> → projet Apps Script **NewGen**.
2. Tout sélectionner dans l'éditeur, coller le contenu complet de
   `gas/GAS_NEWGEN.js`.
3. **Déployer → Gérer les déploiements → ✏️ → Version : Nouvelle version →
   Déployer.** (Ne pas créer un *nouveau* déploiement : l'URL changerait et
   il faudrait modifier `GS_URL` dans `shared.js`.)
4. Menu **Exécuter** → `testerSecuriteDoGet` → Exécuter, puis **Journal
   d'exécution** : vérifier que tout est en ✅.
5. Test réel : enregistrer un atelier, puis en supprimer un.
6. Les jours suivants, dans **Exécutions** : une exécution `doGet`
   d'écriture qui dure **≈ 20 s** est un `waitLock` épuisé, donc une
   écriture refusée pour cause de verrou (AG-004). Côté journal Admin, un
   refus serveur apparaît désormais avec le motif `serveur : …`.
7. Me dire « déployé » — je retire alors les bandeaux ⚠️ en tête du fichier.

### Si ça se passe mal

Dans **Déployer → Gérer les déploiements**, le menu Version liste les
versions précédentes : en sélectionner une et redéployer revient en arrière
en 30 secondes, sans toucher à l'URL.

---

Ce fichier n'est **pas déployé automatiquement**. Google Apps Script n'a pas
d'API de push depuis ce dépôt ; le déploiement reste manuel :

1. Ouvrir le projet Apps Script NewGen (script.google.com).
2. Remplacer le contenu de l'éditeur par celui de `GAS_NEWGEN.js`.
3. Publier une nouvelle version (Déployer → Gérer les déploiements → Nouvelle version).
4. Vérifier que `testerSecuriteDoGet()` (dans le fichier) renvoie bien des ✅
   avant de considérer le déploiement validé.

L'intérêt de ce fichier n'est donc pas l'automatisation, mais d'avoir un
historique versionné et diffable — avant, les échanges se faisaient par
fichiers `.docx`, sans diff possible et avec des risques d'encodage
(espaces insécables introduites par Word, notamment).

**Après chaque déploiement réel confirmé**, mettre à jour ce fichier dans le
même commit que le changement frontend correspondant, pour qu'il reflète
toujours ce qui est censé tourner en production — pas un brouillon en
cours de test.

À la date du dernier commit touchant ce fichier, l'état exact du
déploiement réel (confirmé par l'utilisateur ou en attente de test) est
précisé dans le message de commit.
