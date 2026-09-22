# GAS_NEWGEN.js — copie de référence

## ⏳ EN ATTENTE DE DÉPLOIEMENT — préparé le 22/09/2026

**Ce fichier est en avance sur la production** : **v11.35** (verrou
d'écriture serveur) n'est pas déployée. Correctif de **sécurité des
données**, pas de confort — il **n'accélère rien**.

| | Avant | Après |
|---|---|---|
| Deux `saveEntry` simultanés, même `_id` | peuvent créer **deux lignes** | une seule |
| Deux `delete` simultanés | le second peut supprimer **l'atelier voisin** (index décalé) | impossible |
| Latence de connexion | inchangée | **inchangée** |

⚠️ Point à surveiller propre à NEWGEN : `keepAlive` prend le **même** verrou
de script (Apps Script n'a pas de verrou nommé). Une écriture peut donc
attendre la fin d'un `keepAlive` en cours — ~1-2 s mesurées côté serveur,
fenêtre estimée à moins de 1 %. **Hypothèse non vérifiée** : à recouper dans
les Exécutions après déploiement si une écriture paraît anormalement lente.

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
6. Me dire « déployé » — je retire alors les bandeaux ⚠️ en tête du fichier.

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
