<?php
// Point d'entrée de l'API : api/index.php?action=…
// Remplaçant du GAS NEWGEN (AG-009, AG-011). Le détail des actions et des
// écarts de contrat est dans lib/api.php.
//
// Requêtes « simples » au sens CORS (GET, ou POST en
// application/x-www-form-urlencoded, sans en-tête personnalisé) : le
// navigateur n'envoie pas de pré-vol OPTIONS, un aller-retour de moins.

// Jamais d'erreur PHP dans la réponse (elle révélerait des chemins du
// serveur) : elles vont au journal d'erreurs de l'hébergeur.
ini_set('display_errors', '0');

require_once __DIR__ . '/lib/api.php';

// Seules les pages servies par GitHub Pages peuvent lire les réponses.
header('Access-Control-Allow-Origin: https://maswaddpt47-cmyk.github.io');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

try {
    $action = (string) ($_POST['action'] ?? $_GET['action'] ?? '');
    $reponse = api_traiter(api_base(), $action, $_GET, $_POST);
} catch (Throwable $e) {
    // Le détail va dans le journal d'erreurs PHP de l'hébergeur, pas au client.
    error_log('api: ' . $e->getMessage());
    $reponse = ['ok' => false, 'error' => 'Erreur serveur'];
}
echo json_encode($reponse, JSON_UNESCAPED_UNICODE);
