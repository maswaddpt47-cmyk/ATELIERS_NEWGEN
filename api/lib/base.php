<?php
// Configuration du serveur et connexion à la base.
//
// Les identifiants ne sont jamais dans le dépôt. Le déploiement
// (.github/workflows/deploy-api.yml) les tire des Secrets GitHub et écrit
// ~/config-api.php chez Alwaysdata, HORS du dossier servi ~/www/ : il n'est
// pas téléchargeable depuis internet. Pour les tests en local, la variable
// d'environnement ATELIERS_API_CONFIG désigne un autre fichier.

date_default_timezone_set('Europe/Paris');

function api_config(): array
{
    static $config = null;
    if ($config !== null) return $config;
    // lib/ → api/ → www/ → dossier personnel du compte.
    $chemin = getenv('ATELIERS_API_CONFIG') ?: dirname(__DIR__, 3) . '/config-api.php';
    $config = is_file($chemin) ? (require $chemin) : [];
    if (!is_array($config)) $config = [];
    return $config;
}

function api_base(): PDO
{
    $c = api_config();
    foreach (['db_hote', 'db_nom', 'db_utilisateur', 'db_mot_de_passe'] as $cle) {
        if (($c[$cle] ?? '') === '') throw new RuntimeException("Base non configurée ($cle manquant dans config-api.php).");
    }
    return new PDO(
        'mysql:host=' . $c['db_hote'] . ';dbname=' . $c['db_nom'] . ';charset=utf8mb4',
        $c['db_utilisateur'],
        $c['db_mot_de_passe'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
    );
}
