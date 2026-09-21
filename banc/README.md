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
toutes les 3 minutes, en alternant file d'attente et lectures doublées. Une
salve simule l'ouverture d'une application : 3 lectures.

- Les mesures survivent au rechargement (`localStorage`, 2000 salves max).
- **📋 Copier pour Claude** met dans le presse-papier un résumé compact
  (agrégats par stratégie, répartition horaire des pertes, 15 dernières
  salves) — c'est ce qu'il faut coller dans une conversation, pas le CSV brut.
- **Export CSV** pour l'analyse fine.

⚠️ **Ne pas changer de backend en cours de série** : elle ne vaudrait plus
rien. La sortie signale une série mélangée.

## Comment lire le résultat

| Ce qu'on observe | Ce qu'on en conclut |
|---|---|
| Taux de salves incomplètes comparable, durées très différentes | La file ne protège de rien et coûte en attente → aligner sur le doublage |
| Le doublage perd nettement moins de salves | Idem, conclusion renforcée |
| La file perd moins de salves que le doublage | L'hypothèse de NextStep tient → aligner sur la file |
| Taux global sous ~15 % | Ne rien construire de plus, l'appli est utilisable (§2) |
| Taux durablement au-dessus de 30-40 % | Le proxy du §3 se justifie |

## Limites, à ne pas oublier en lisant les chiffres

- **Hypothèse non vérifiée** : la perte de livraison frapperait indépendamment
  du poids de la réponse, donc `getConfig` (léger) serait représentatif de
  `getAll`. Indices en ce sens seulement (getAll, getComptes et getConfig
  tombent indifféremment ; 221 ateliers reviennent en 1 s quand ça passe).
- Un seul appareil, un seul réseau : ne dit rien d'un mobile en 4G ailleurs.
- Les constantes (plafond 12 s, doublon à 7 s, 3 tentatives, budget 45 s) sont
  **recopiées** de `shared.js`. Si elles changent là-bas, les corriger ici,
  sinon le banc mesure une stratégie qui n'est plus celle du produit.
- `getConfig` est une action de lecture **sans token** (cf. `CHANTIERS.md` §4).
  Le banc n'aggrave rien — l'endpoint est déjà ouvert — mais quand ce chantier
  sera traité, le banc devra suivre ou être retiré.
- Le banc consomme des exécutions Apps Script : ~7 appels toutes les 3 min,
  soit ~140/h. À arrêter une fois la mesure faite.

## Ce que le banc ne mesure pas

Les écritures. Elles restent séquentielles et jamais doublées dans les deux
projets, et ce n'est pas la question posée.
