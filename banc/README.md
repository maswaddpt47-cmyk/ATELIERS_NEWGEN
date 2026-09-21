# Banc de mesure GAS — file d'attente vs lectures doublées

Répond à la question ouverte du `CHANTIERS.md` §1, et alimente le §2
(le taux de perte justifie-t-il le proxy ?).

## Pourquoi il existe

La méthode prévue au départ — « console F12 sur l'Admin de chaque site après
quelques jours d'usage, puis on compare les ratios » — ne peut pas trancher :

1. **La panne frappe par fenêtres de temps, pas par appel** (mesuré le
   18/09/2026 : trois appels meurent dans la même seconde, leurs trois
   doublons passent dans la même seconde). Comparer NextStep utilisé le matin
   et NEWGEN testé le soir compare deux moments, pas deux stratégies.
2. **Les deux applis ne visent pas le même backend.** Deux déploiements Apps
   Script distincts, deux URLs `/exec`, et c'est précisément dans
   l'acheminement de cette redirection que la réponse se perd. L'expérience
   n'a donc jamais été « à une seule variable ».
3. NextStep porte toute l'équipe, NEWGEN une seule personne : les volumes ne
   sont pas comparables et ne le deviendront pas.

Ce banc rejoue les **deux stratégies en alternance, depuis le même appareil,
contre un seul GAS**. Tout ce qui n'est pas la stratégie est neutralisé.

## Utilisation

Ouvrir la page, choisir le backend, **laisser l'onglet ouvert**. Une salve
toutes les 2 minutes, en alternant file d'attente et lectures doublées. Une
salve simule l'ouverture d'une application : 3 lectures.

**L'intervalle est un délai *entre* salves, pas une cadence** : la salve
suivante n'est programmée qu'une fois la précédente terminée, deux salves ne
se chevauchent donc jamais. C'est ce qui permet de descendre à 2 min sans
risque — et il le faut : voir « Quelle durée de série » ci-dessous.

- Les mesures survivent au rechargement (`localStorage`, 2000 salves max).
- Au démarrage, la page demande au navigateur de **garder l'écran allumé**
  (Wake Lock). Ça ne couvre pas la veille système, à désactiver à part. Le
  verrou est relâché quand l'onglet passe en arrière-plan et repris au retour.
- **Les interruptions sont signalées** : un écart de plus de 2,5 fois
  l'intervalle entre deux salves (veille, onglet gelé par le navigateur,
  fenêtre fermée) apparaît en bandeau et dans la sortie texte. Une série
  trouée reste exploitable — mais sa répartition horaire ne doit pas être lue
  comme si elle était continue.
- **Ne jamais mélanger deux backends dans un même journal** (bouton
  « Vider » avant de changer de cible). En revanche **mélanger deux jours sur
  le même backend est souhaitable** : chaque stratégie subit les mêmes
  fenêtres de panne puisqu'elles alternent salve après salve, et cumuler deux
  journées dans la même tranche horaire est le moyen le moins coûteux
  d'atteindre une taille de série concluante. Ne pas vider entre les deux.
- **Des salves peuvent être écartées automatiquement** : si les minuteurs du
  navigateur ont dérivé de plus de 2 s (onglet en arrière-plan), la salve n'a
  pas joué la stratégie qu'elle annonce — le doublon à 7 s peut ne pas être
  parti du tout. Ces salves restent dans le journal mais sont exclues de tous
  les agrégats, et leur nombre est affiché.
- **📋 Copier pour Claude** met dans le presse-papier un résumé compact
  (agrégats par stratégie, répartition horaire des pertes, 15 dernières
  salves) — c'est ce qu'il faut coller dans une conversation, pas le CSV brut.
- **Export CSV** pour l'analyse fine.

⚠️ **Ne pas changer de backend en cours de série** : elle ne vaudrait plus
rien. La sortie signale une série mélangée.

## Quelle durée de série — à lire avant de lancer

Les salves alternant une à une, chaque paire (file, doublage) tombe dans la
même fenêtre de panne : le plan est **apparié**, et le test à lire est
**McNemar sur les paires consécutives**, pas une comparaison de deux groupes
indépendants. Cet appariement est une bonne nouvelle — la corrélation
temporelle devient conservatrice au lieu d'être trompeuse — mais il ne crée
pas de la puissance là où il n'y a pas assez de salves.

Puissance estimée par simulation le 21/09/2026 (modèle en blocs de 20-60 min
calé sur le relevé horaire du 21/09, écart vrai 36 % → 16 % de salves
incomplètes) :

| Durée de série (intervalle 2 min) | salves / bras | chance de conclure |
|---|---|---|
| 2 h | 30 | ~40 % *(extrapolé, non simulé)* |
| 4 h (11h-15h) | 60 | ~74 % (McNemar ~84 %) |
| 4 h × 2 jours cumulés | 120 | ~97 % |

**Une série de 4 h laisse donc environ une chance sur quatre de ne rien
conclure alors qu'un écart réel existe.** Si le résultat tombe dans la zone
« non concluant », prolonger le **même** journal un second jour sur la même
tranche horaire plutôt que de recommencer.

⚠️ **Dans ce cas, relever le plafond d'arrêt automatique avant de lancer le
second jour.** Il se compte sur le **cumul du journal**, pas sur la journée :
à 2 min, 4 h consomment déjà ~540 appels, donc le plafond par défaut de 800
couperait la seconde journée en plein milieu. Passer à **1200** pour deux
journées (~40 min de quota Apps Script au total, à comparer aux 90 min/jour
d'un compte Google gratuit). Le plafond reste à 800 par défaut : c'est un
garde-fou, on le relève sciemment, pas par habitude.

⚠️ Ces chiffres viennent d'une **simulation**, pas du terrain : ils dépendent
du modèle de fenêtres de panne retenu. À réviser dès qu'une vraie série
existe.

## Comment lire le résultat

**Seuil de conclusion, à appliquer avant d'interpréter quoi que ce soit :**
ne conclure « les deux stratégies se valent » que si l'intervalle de confiance
à 95 % de la différence **appariée** exclut 10 points. Sinon, la mention à
écrire est **« non concluant, série trop courte »** — pas « comparable ».
Une absence de résultat n'est pas une observation.

| Ce qu'on observe | Ce qu'on en conclut |
|---|---|
| Écart apparié significatif en faveur du doublage | Aligner NextStep sur le doublage |
| Écart apparié significatif en faveur de la file | L'hypothèse de NextStep tient → aligner sur la file |
| Écart non significatif, IC à 95 % **excluant** 10 points | Les deux se valent → l'argument d'alignement tranche, NextStep sert de référence |
| Écart non significatif, IC à 95 % **incluant** 10 points | **Non concluant** — prolonger la série, ne rien décider |
| Les deux bras perdent **autant et énormément** (proche de 100 % en fenêtre) | La panne est **totale par fenêtre** : aucune stratégie d'appel n'y peut rien, et le proxy du §3 devient le seul levier. À ne pas confondre avec « la file ne protège de rien » |
| Taux global sous ~15 % | Ne rien construire de plus, l'appli est utilisable (§2) |
| Taux durablement au-dessus de 30-40 % | Le proxy du §3 se justifie |

### Séparer le parallélisme du doublage — sans troisième bras

Le bras « doublage » mélange deux ingrédients : les lectures partent
**ensemble**, et chacune est **doublée** à 7 s. Mais le premier appel de
chaque lecture part seul, le doublon n'arrivant qu'à `HEDGE_MS` : c'est donc
déjà une observation « parallèle, non doublé », directement comparable à
l'appel unique du bras « file ». La sortie « 📋 Copier pour Claude » expose
les deux séparément :

- **effet PARALLELISME** — échecs du 1<sup>er</sup> appel, 1<sup>re</sup>
  tentative : file (1 appel en vol) contre doublage (N appels en vol) ;
- **effet DOUBLAGE** — part des doublons partis qui ont sauvé la lecture.

Un troisième bras réel coûterait un tiers des salves de chaque bras, donc la
question principale — c'est pour ça qu'il n'y en a pas. En contrepartie, les
3 appels d'une même salve sont dans la même fenêtre de panne : **ce ne sont
pas 3 observations indépendantes**, et ces deux taux ne sont décidables qu'en
série longue. Les lire comme indicatifs.

## Limites, à ne pas oublier en lisant les chiffres

- **Hypothèse non vérifiée** : la perte de livraison frapperait indépendamment
  du poids de la réponse, donc `getConfig` (léger) serait représentatif de
  `getAll`. Indices en ce sens seulement (getAll, getComptes et getConfig
  tombent indifféremment ; 221 ateliers reviennent en 1 s quand ça passe).
- Un seul appareil, un seul réseau : ne dit rien d'un mobile en 4G ailleurs.
- La décision de réessayer est alignée sur les produits depuis le 21/09/2026 :
  seuls `[404,408,429,500,502,503,504]` (`GAS_RETRYABLE_HTTP`, identique dans
  NEWGEN `shared.js:708` et NextStep `shared.js:524`) et les timeouts/coupures
  réseau sont réessayés. Un 403 **et une réponse non-JSON** — les deux formes
  que prend un quota Apps Script épuisé — font rendre la main, comme dans
  `shared.js:798-801`. Sans cet alignement, le banc aurait noté des durées et
  un nombre d'appels que la production n'aurait jamais eus, au moment précis
  où on lit les chiffres, et aurait triplé sa consommation alors que le quota
  est déjà à sec. **Cette liste fait partie des constantes recopiées** : la
  resynchroniser avec `shared.js` comme les autres.
- Les constantes (plafond 12 s, doublon à 7 s, 3 tentatives, budget 45 s) sont
  **recopiées** de `shared.js`. Si elles changent là-bas, les corriger ici,
  sinon le banc mesure une stratégie qui n'est plus celle du produit.
  Vérifiées conformes aux **deux** produits le 21/09/2026 (NEWGEN
  `shared.js:709,742,743,745,747` ; NextStep `shared.js:543-545,563-566,570`,
  qui n'a aucun `HEDGE` — c'est bien la différence de stratégie, pas un oubli).
- `getConfig` est une action de lecture **sans token** (cf. `CHANTIERS.md` §4).
  Le banc n'aggrave rien — l'endpoint est déjà ouvert — mais quand ce chantier
  sera traité, le banc devra suivre ou être retiré.
- ⚠️ **Le quota Apps Script se compte par COMPTE Google, pas par script** :
  le temps d'exécution quotidien (90 min en gratuit, 6 h en Workspace) est
  partagé par tous les scripts d'un même compte. Une longue série visant un
  backend peut donc épuiser le quota de l'autre et casser la production. La
  page affiche la consommation estimée et **s'arrête d'elle-même** au plafond
  d'appels demandé (800 par défaut, soit ~27 min de quota).
- **Le banc consomme du quota Apps Script.** Un cycle ne lance qu'une salve
  (les stratégies alternent), soit ~4,5 appels toutes les 2 min ≈ **135/h**
  (~4 toutes les 3 min ≈ 80/h à l'ancien réglage), et
  chaque `doGet` compte dans le temps d'exécution quotidien du script (90 min
  par jour sur un compte Google gratuit, 6 h sur Workspace). À ~2 s par
  exécution, une journée de 8 h de mesure consomme ~20 min de ce quota —
  supportable, mais **à ne pas laisser tourner en permanence**, surtout en
  visant le backend de production. Espacer à 5 ou 10 min en cas de doute, et
  arrêter le banc une fois la série faite.

## Ce que le banc ne mesure pas

Les écritures. Elles restent séquentielles et jamais doublées dans les deux
projets, et ce n'est pas la question posée.
