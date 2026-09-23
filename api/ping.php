<?php
// ping.php — cible de mesure de l'étape 0 (AG-009, CHANTIERS « Refonte »).
//
// Répond un JSON statique, sans base de données ni calcul : ce qu'on mesure,
// c'est l'acheminement d'une réponse depuis Alwaysdata jusqu'au poste, à
// comparer au même instant avec le getConfig de GAS NextStep
// (banc/cibles.html). Taille voisine d'une réponse getConfig — environ 4 Ko,
// hypothèse non vérifiée : l'environnement de travail de Claude ne joint pas
// script.google.com, la taille réelle n'a pas pu être mesurée.
//
// À supprimer quand l'étape 0 est tranchée.

// Seule la page de mesure, servie par GitHub Pages, a le droit de lire la
// réponse. Requête GET simple sans en-tête personnalisé : pas de pré-vol CORS.
header('Access-Control-Allow-Origin: https://maswaddpt47-cmyk.github.io');
header('Content-Type: application/json; charset=utf-8');
// Jamais de cache : une réponse servie par un cache intermédiaire mesurerait
// le cache, pas l'hébergeur.
header('Cache-Control: no-store');

echo json_encode([
    'ok'     => true,
    'source' => 'alwaysdata',
    'config' => ['remplissage' => str_repeat('x', 4000)],
]);
