# Chantiers en cours — ATELIERS_NEWGEN

État au **21/09/2026** (soir), commit de référence `a7575a4`.
Fichier transitoire : à mettre à jour à chaque avancée, à supprimer quand tout
est soldé. Ce n'est pas de la documentation permanente (cf.
`MD-LIB/hygiene-instructions.md`).

Pour faire contredire une proposition par une autre session : `AGORA.md`
(section 8 du `CLAUDE.md`). Mis en place le 21/09/2026, un cycle complet
effectué — AG-001 a corrigé le protocole du banc avant la série.

> **Prochaine action, 22/09/2026 au matin — avant 11h.** Le banc a été revu
> le 21/09 au soir et est prêt ; il n'a **jamais été ouvert dans un
> navigateur** depuis ces modifications. Dans l'ordre :
> 1. Ouvrir `banc/`, lancer 10 min, vérifier que **file et doublage sont à
>    égalité** dans le résumé et que « 📋 Copier pour Claude » produit bien les
>    blocs *effet PARALLELISME* et *effet DOUBLAGE*.
> 2. **Vider**, puis lancer la vraie série : backend NEWGEN, intervalle 2 min,
>    plafond 800 (ou 1200 si série sur deux jours), démarrage avant 11h.
> 3. Coller la sortie « Copier pour Claude » ici ou dans une session — le
>    résultat se lit avec le seuil de conclusion du §1, pas à l'œil.

---

## 1. Décision à trancher — parallélisme contre sérialisation

**Les deux projets jumeaux tournent en production sur des stratégies
opposées**, et personne n'a arbitré :

| | NEWGEN | NextStep |
|---|---|---|
| Stratégie | lectures **doublées** à partir de 7 s | **file d'attente**, un appel en vol à la fois |
| Statut de l'hypothèse | efficacité constatée (3 sauvetages relevés le 18/09) | « HYPOTHÈSE NON VÉRIFIÉE », écrit dans son propre code |
| Mesure disponible | 54 % de pertes le 19/09 entre 12:43 et 14:32 | une seule, **d'attribution incertaine** (voir ci-dessous) |

**Ce qui bloque :** aucune mesure NextStep fiable. L'origine des captures du
19/09 est **contestée** — l'utilisateur les attribue aux deux sites, Claude
les attribuait toutes à NEWGEN ; les deux applis sont visuellement identiques
(même nav `#197d89`, même sidebar) et aucun élément de la capture ne tranche.
Le Journal des opérations porte depuis une pastille NEWGEN / NEXTSTEP pour
lever l'ambiguïté : seules les mesures postérieures à cette pastille comptent.

**Élément à ne pas perdre**, consigné dans le `CHANTIERS.md` de NextStep :
un relevé du 19/09 vers 09:30 (attribution incertaine, antérieure à la
pastille) montre `getComptes #1` abandonné à 12 s **alors qu'il était seul en
vol** — la file avait sérialisé, `getAll` était terminé depuis 12 s, donc
aucune rafale. Si ce relevé vient bien de NextStep, il contredit directement
l'hypothèse qui justifie sa file d'attente. À refaire proprement.

**Comment trancher — le banc de mesure (21/09/2026).** La méthode qui figurait
ici (console F12 sur l'Admin de chaque site après quelques jours d'usage) ne
pouvait pas conclure : la panne frappe par fenêtres de temps et les deux
applis ne sont ni utilisées aux mêmes heures, ni branchées sur le même
déploiement Apps Script — sans compter que leurs journaux étaient mélangés
jusqu'à cette date. Remplacée par un banc qui rejoue les **deux stratégies en
alternance**, depuis un seul poste, contre un seul backend :

**https://maswaddpt47-cmyk.github.io/ATELIERS_NEWGEN/banc/** — voir
`ATELIERS_NEWGEN/banc/README.md` pour la méthode, les limites et la lecture
des résultats.

Série prévue le 22/09/2026 : backend NEWGEN, **intervalle 2 min** (pas 3),
plafond 800 appels, démarrage avant 11h pour couvrir la fenêtre 11h-15h (la
plus dégradée). Résultat attendu dans ce fichier une fois la mesure faite.

**Le protocole a été revu le 21/09/2026 après contradiction entre sessions
(AG-001).** Ce qui change, et pourquoi :

- **2 min au lieu de 3** : à 3 min, la fenêtre de 4 h ne donnait que 40 salves
  par stratégie, soit ~56 % de chances de détecter un écart réel de 20 points.
  À 2 min : 60 par bras, ~74 %. L'intervalle est un délai *entre* salves, pas
  une cadence — aucun risque de chevauchement.
- **Ne conclure « les deux se valent » que si l'intervalle de confiance à 95 %
  de la différence appariée exclut 10 points.** Sinon écrire « non concluant,
  série trop courte ». Le test à lire est McNemar sur les paires consécutives
  (les salves alternent une à une, le plan est apparié). Une absence de
  résultat n'est pas une observation — c'est le piège que la première version
  du tableau de lecture du `banc/README.md` tendait.
- **Si le 22/09 est non concluant : prolonger le même journal un second jour**
  sur la même tranche horaire (120 salves/bras ≈ 97 %). Ne pas vider entre les
  deux, et **relever le plafond à 1200** — il se compte sur le cumul du
  journal, 800 couperait la seconde journée en plein milieu.
- **Le banc sépare désormais l'effet du parallélisme de celui du doublage**
  sans troisième bras (voir « Points à ne pas défaire »).

⚠️ Chiffres de puissance issus d'une **simulation**, pas du terrain. À réviser
dès qu'une vraie série existe.

⚠️ Le quota Apps Script se compte **par compte Google**, pas par script : ne
pas viser le backend de production pendant les heures de travail de l'équipe.

**Piste ouverte par le relevé du 21/09** (132 appels, tous projets confondus,
donc non attribuable) : les pertes se concentrent très fortement selon
l'heure — 0 % à 10h et 16h, 68 % à 12h, 65 % à 13h, 55 % à 15h. Si le banc
confirme ce profil, la stratégie d'appel devient secondaire : ce serait la
charge de l'infrastructure Apps Script aux heures ouvrées, et le proxy
deviendrait le seul vrai levier.

Puis harmoniser les deux projets sur le gagnant. Si les ratios se tiennent,
l'argument d'alignement l'emporte et NextStep sert de référence.

⚠️ Ne pas harmoniser **avant** d'avoir mesuré : les deux applis étant
identiques par ailleurs, c'est aujourd'hui une expérience propre à une seule
variable. L'aligner maintenant détruirait le seul moyen de savoir.

## ⚠️ Origine commune — les deux applis partagent leur `localStorage`

Découvert le 21/09/2026. GitHub Pages sert les deux projets depuis la **même
origine** (`maswaddpt47-cmyk.github.io`), et `localStorage` est cloisonné par
origine, **pas par chemin**. Les deux applis utilisaient les mêmes clés.

**Ce que ça invalide** : toutes les mesures de journal antérieures au
21/09/2026 mélangent les deux projets. Le §1 attend « une mesure NextStep
fiable » — on sait maintenant qu'aucune ne pouvait l'être, et que la pastille
NEXTSTEP/NEWGEN ajoutée le 19/09 ne pouvait pas le révéler : elle nomme
l'appli qui **affiche** la liste, pas celle qui a **émis** l'appel. C'est
l'explication de l'attribution contestée des captures du 19/09.

**Corrigé** : le journal est cloisonné (`adm_logs_nextstep` /
`adm_logs_newgen`). Les lignes écrites sous l'ancienne clé `adm_logs` ne sont
pas reprises — elles mélangent les deux projets, elles ne sont pas
exploitables. Elles restent dans le navigateur tant qu'on ne vide pas les
données du site.

**Reste à faire** : les préférences sont toujours partagées —
`adm_conseiller`, `adm_dark`, `adm_sidebar_pinned`, `f_annee`, `cal_moisDeb`,
`cal_moisFin`, `sidebar_pinned`. Se connecter sur une appli change donc le
conseiller sélectionné ou le thème de l'autre. Jamais signalé comme un bug à
ce jour, mais c'est la même cause. Correction : préfixer ces clés comme pour
le journal, en migrant l'existant pour ne pas réinitialiser les préférences
des conseillers.

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

## 6. ⚠️ Piège — la logique du stock est dupliquée dans `shared.js`

Découvert le 21/09/2026 en corrigeant le cumul du jour de retour.
`periodePretMateriel`, `findOrdinateursConflicts`, `getPretsMateriel`,
`totauxParJourMateriel` et `totalJourParConseiller` existent **deux fois** :
dans `logic.js` (celui que testent les suites Node) et dans `shared.js`
(celui que les pages chargent réellement — `logic.js` n'est pas référencé par
`index.html`/`admin.html`, seulement par les tests).

**Corriger `logic.js` seul laisse l'application sur l'ancien calcul, avec
toutes les suites au vert.** C'est le pire cas possible : le test confirme un
correctif qui n'atteint jamais l'utilisateur. Vérifier systématiquement les
deux copies sur tout changement touchant le matériel.

NextStep n'a pas cette duplication : son `shared.js` consomme `logic.js`,
chargé avant lui dans les deux pages. C'est un argument de plus pour la piste
`gas-client.js` du §6 de NextStep — le même principe appliqué à la couche
matériel.

## 7. Vérifications terrain en attente (PWA, 19/09/2026)

Deux points livrés le 19/09 mais jamais vérifiés en dehors des tests
automatisés (qui ne peuvent pas les couvrir) :

- **Installabilité PWA** : confirmer sur un Android réel que "Installer
  l'application" apparaît bien pour `index.html` et `admin.html` (manifest +
  icônes + service worker déployés — voir `MD-LIB/pwa-service-worker.md`).
- **Lisibilité des couleurs de la Frise du parc** : les barres de
  `FriseMateriel` (`shared.js`) sont colorées par conum depuis le 19/09 —
  pas de vérification visuelle du contraste texte/fond pour chaque
  conseiller existant.
- **Le banc revu le 21/09 n'a pas été ouvert** (5 commits : `b648e4d`,
  `c7cee6c`, `a7575a4`). Vérifié seulement par `node --check` et par des tests
  de logique en isolation — alternance équilibrée après rechargement,
  agrégation par appel, six scénarios d'erreur réseau. À confirmer à l'écran
  avant la série : cf. le bandeau en tête de ce fichier.

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
- **Le banc n'a pas besoin d'un troisième bras « parallèle non doublé ».** Il
  existe déjà : dans le bras doublage, le premier appel de chaque lecture part
  seul, le doublon n'arrivant qu'à 7 s. Il suffit de ne pas jeter le sort de
  chaque appel à l'agrégation, ce que `banc/index.html` enregistre depuis le
  21/09/2026. Un vrai troisième bras coûterait un tiers des salves de chaque
  bras, donc la question principale.
- **Le compteur d'alternance du banc suit `salves.length`, jamais 0.** Les
  salves survivent au rechargement : repartir de zéro faisait retomber
  l'alternance sur `file` à chaque reprise (20 salves sur 20 dans le pire cas),
  et lui faisait hériter de toutes les salves d'après-interruption. Le bouton
  Vider le remet à zéro — les deux vont ensemble.
- **Le banc ne réessaie que sur `RETRYABLE_HTTP` + timeout/coupure réseau.**
  Un 403 **et une réponse non-JSON** (les deux formes d'un quota Apps Script
  épuisé, cf. `shared.js:798-801`) font rendre la main, comme en production.
  Ne pas remettre un `reessayable = true` inconditionnel : le `catch` de fin
  de chaîne écrasait la décision prise en amont et rendait le filtrage
  inopérant — le piège est de ne corriger que le `then`.
- **Ne jamais mélanger deux backends dans un journal de banc ; mélanger deux
  jours est au contraire souhaitable.** Les stratégies alternant salve après
  salve, chacune subit les mêmes fenêtres de panne : cumuler deux journées est
  le moyen le moins cher d'atteindre une série concluante.
- **`periodePretMateriel` retombe sur la veille/lendemain ouvrés** (jamais
  un jour de week-end) quand les dates de prélèvement/retour ne sont pas
  saisies — alignée sur NextStep le 19/09/2026. Ne pas revenir au repli
  "jour même de l'atelier".
