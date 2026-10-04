<?php
// Configuration du serveur et connexion à la base.
//
// Les identifiants ne sont jamais dans le dépôt. Le déploiement
// (.github/workflows/deploy-api.yml) les tire des Secrets GitHub et écrit
// ~/config-api.php chez Alwaysdata, HORS du dossier servi ~/www/ : il n'est
// pas téléchargeable depuis internet. Pour les tests en local, la variable
// d'environnement ATELIERS_API_CONFIG désigne un autre fichier.

date_default_timezone_set('Europe/Paris');

// Fichier de configuration d'après le dossier de l'API (bac à sable, AG-019,
// 04/10/2026) : www/api/ → ~/config-api.php (production), www/api-sandbox/ →
// ~/config-api-sandbox.php. Jamais de repli de l'un sur l'autre : sans son
// propre fichier, le bac à sable n'a pas de base, il ne lit pas celle de la
// production.
function api_chemin_config(string $dossierLib): string
{
    // lib/ → api*/ → www/ → dossier personnel du compte.
    return dirname($dossierLib, 3) . '/config-' . basename(dirname($dossierLib)) . '.php';
}

function api_config(): array
{
    static $config = null;
    if ($config !== null) return $config;
    $chemin = getenv('ATELIERS_API_CONFIG') ?: api_chemin_config(__DIR__);
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
