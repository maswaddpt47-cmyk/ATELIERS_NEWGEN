# Chantiers en cours — ATELIERS_NEWGEN

État au **20/09/2026**, commit de référence `0f960a4`.
Fichier transitoire : à mettre à jour à chaque avancée, à supprimer quand tout
est soldé. Ce n'est pas de la documentation permanente (cf.
`MD-LIB/hygiene-instructions.md`).

---

## 1. Décision à trancher — parallélisme contre sérialisation

**Les deux projets jumeaux tournent en production sur des stratégies
opposées**, et personne n'a arbitré :

| | NEWGEN | NextStep |
|---|---|---|
| Stratégie | lectures **doublées** à partir de 7 s | **file d'attente**, un appel en vol à la fois |
| Statut de l'hypothèse | efficacité constatée (3 sauvetages relevés le 18/09) | « HYPOTHÈSE NON VÉRIFIÉE », écrit dans son propre code |
| Mesure disponible | 54 % de pertes le 19/09 entre 12:43 et 14:32 | **aucune à ce jour** |

**Ce qui bloque :** on n'a jamais réussi à mesurer NextStep. Les captures du
19/09 attribuées aux deux sites venaient en réalité toutes de NEWGEN — les
deux applis sont visuellement identiques (même nav `#197d89`, même sidebar).
Le Journal des opérations porte depuis une pastille NEWGEN / NEXTSTEP pour
lever l'ambiguïté.

**Comment trancher.** Console (F12) sur l'Admin de chaque site, après quelques
jours d'usage :

```js
(() => {
  const L = JSON.parse(localStorage.getItem('adm_logs')||'[]').map(e=>e.msg).filter(m=>m&&m.startsWith('GAS '));
  const ko = L.filter(m=>/404|bloqué|réseau/.test(m)).length;
  const ok = L.filter(m=>/— ok en/.test(m)).map(m=>parseFloat(m.match(/ok en ([\d.]+)/)[1])).sort((a,b)=>a-b);
  console.log(`${location.pathname} | appels ${L.length} | échecs ${ko} (${Math.round(ko/L.length*100)}%) | médiane ${ok[Math.floor(ok.length/2)]}s | doublons ${L.filter(m=>/#\d+b /.test(m)).length}`);
})()
```

Puis harmoniser les deux projets sur le gagnant. Si les ratios se tiennent,
l'argument d'alignement l'emporte et NextStep sert de référence.

⚠️ Ne pas harmoniser **avant** d'avoir mesuré : les deux applis étant
identiques par ailleurs, c'est aujourd'hui une expérience propre à une seule
variable. L'aligner maintenant détruirait le seul moyen de savoir.

## 2. Mesure en attente — le proxy se justifie-t-il ?

Relevé du 19/09 sur NEWGEN : **54 % de pertes** sur ~70 appels, avec deux
chargements terminés en écran d'erreur après trois tentatives. Mais le 18/09
au soir on était plutôt à 30 %, et à 12:44 le 19/09 les quatre appels sont
passés sans un raté. **On ne sait pas si 54 % est représentatif.**

- Taux qui retombe sous ~15 % → ne rien construire, l'appli est utilisable.
- Taux durablement au-dessus de 30-40 % → le proxy (§3) se justifie.

## 3. Chantier conditionnel — proxy pour supprimer la perte

La couche de reprise côté client a atteint sa limite : plafonds courts,
appels superflus supprimés, doublons annulés. À 54 % de pertes, aucune
politique de reprise ne compense.

Le proxy appellerait GAS **côté serveur** : la redirection `/exec →
googleusercontent`, qui expire avant d'être suivie depuis un mobile, serait
suivie en quelques dizaines de millisecondes depuis un datacenter. En bonus :
cache court mutualisé pour toute l'équipe, URL `/exec` retirée du JS public
(cf. §4), et les deux projets alignés sur la même couche réseau.

**Bloqué par une question sans réponse au 20/09/2026 :** l'utilisateur
dispose-t-il d'un hébergement exécutant PHP en HTTPS ? Recherche faite dans
les trois repos (fichiers `.php`, `.htaccess`, `CNAME`, workflows FTP,
domaines cités, historique git) : **aucune trace**. Les 13 dépôts sont
publics et vivent sur GitHub Pages, qui ne sert que du statique.

Alternative si aucun hébergement : Cloudflare Workers (gratuit, HTTPS
d'office, ~20 lignes). ⚠️ Ajoute un sous-traitant américain de plus dans la
chaîne — à assumer explicitement pour une collectivité.

## 4. ⚠️ Sécurité — endpoints accessibles sans token

`getAll`, `getComptes` et `getConfig` sont dans `READ_ACTIONS` côté GAS :
accessibles **sans aucun token**. L'URL `/exec` est en clair dans
`shared.js`, dans un dépôt **public**. Qui la lit peut récupérer les ateliers
de l'année, la liste des agents avec rôles et état actif, et la clé `emails`
de la config.

Chantier séparé, décidé le 18/09. Implique un redéploiement GAS manuel et de
revoir l'écran de connexion, qui appelle `getComptes` avant d'avoir un token.

## 5. Nettoyage restant — changelog dans `gas/GAS_NEWGEN.js`

35 entrées de version (~200 lignes sur 1020) subsistent en en-tête. Non
retirées volontairement : ce fichier est la **copie de référence diffable**
du script collé à la main dans l'éditeur Apps Script. Le nettoyer maintenant
désynchroniserait la copie et rendrait illisible le prochain diff avant
déploiement.

→ À faire **au prochain déploiement GAS réel**, quand le fichier doit de
toute façon être recollé dans l'éditeur.

---

## Points à ne pas défaire

Chacun a coûté cher à établir et est verrouillé par un test :

- **Plafonds : 12 s lecture, 12 s écriture, 25 s `saveMany`.** Les rallonger
  ne récupère aucune réponse perdue, ça allonge l'écran d'attente. Verrouillé
  par `reseau.test.js`.
- **Aucun appel GAS superflu au démarrage ni après une écriture.** Verrouillé
  par `appels.test.js`.
- **Les écritures ne sont jamais doublées** (deux `appendRow` concurrents =
  atelier en double). La couche réseau le décide seule, via
  `GAS_ACTIONS_ECRITURE` — ne pas repasser ça en option d'appelant.
- **`sw.js` ne met rien en cache et n'intercepte rien.** Voir la section 4 du
  `CLAUDE.md`.
