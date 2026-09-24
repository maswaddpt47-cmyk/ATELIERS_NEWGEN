<?php
// Test de contrat de l'API de lecture (api/index.php, api/lib/api.php).
//
//   ATELIERS_TEST_MYSQL=root@127.0.0.1 ATELIERS_TEST_MYSQL_MDP=… php api-tests/api.test.php
//
// Lance l'API sur le serveur intégré de PHP, contre une base jetable remplie
// par l'import (même classeur de test que import.test.php), et vérifie la
// forme des réponses attendues par le client NEWGEN. La liste des champs
// d'un atelier est relue dans contract.test.js : si le client ajoute un
// champ, ce test le réclame à l'API.

require __DIR__ . '/outils.php';

$mysql = getenv('ATELIERS_TEST_MYSQL');
if (!$mysql) { echo "(API non testée : ATELIERS_TEST_MYSQL non défini)\n"; exit(0); }
[$util, $hote] = explode('@', $mysql) + [1 => '127.0.0.1'];
$mdp = (string) getenv('ATELIERS_TEST_MYSQL_MDP');

// ── Base jetable et configuration ─────────────────────────────────────────
$db = new PDO("mysql:host=$hote;charset=utf8mb4", $util, $mdp, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db->exec('DROP DATABASE IF EXISTS ateliers_test_api');
$db->exec('CREATE DATABASE ateliers_test_api CHARACTER SET utf8mb4');
$db->exec('USE ateliers_test_api');
$c = classeur([
    atelier('entry_1'),
    atelier('entry_2', ['horaire' => null, 'inscrits' => null, 'presents' => 0, 'Classe mobile' => null, 'Ordinateur' => null, 'date_prelevement_materiel' => null, 'date_retour_materiel' => null]),
    atelier('entry_3', ['date' => ['d', 46389]]),   // 02/01/2027
]);
$c['Comptes'][] = ['Ancien Collegue', hash('sha256', 'x'), 'user', 'NON', 0, null];
$c['Config'][] = ['visibility', '{}'];
$c['Config'][] = ['list_statuts', "Planifié\nRéalisé"];
$c['Config'][] = ['maintenance_msg', 'Retour à 14 h'];
$a = analyser($c);
if ($a['erreurs']) { echo "Classeur de test invalide :\n" . implode("\n", $a['erreurs']) . "\n"; exit(1); }
import_charger($db, $a, 'test');
$db->exec("UPDATE comptes SET role = 'user' WHERE conseiller = 'Nouveau Venu'");

$cfg = tempnam(sys_get_temp_dir(), 'cfg');
file_put_contents($cfg, '<?php return ' . var_export(['db_hote' => $hote, 'db_nom' => 'ateliers_test_api', 'db_utilisateur' => $util, 'db_mot_de_passe' => $mdp], true) . ';');

// ── Serveur ───────────────────────────────────────────────────────────────
$port = 8700 + random_int(0, 99);
$srv = proc_open([PHP_BINARY, '-S', "127.0.0.1:$port", '-t', __DIR__ . '/../api'],
    [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $tubes, null,
    ['ATELIERS_API_CONFIG' => $cfg] + getenv());
register_shutdown_function(function () use ($srv, $cfg, $db) {
    proc_terminate($srv);
    @unlink($cfg);
    $db->exec('DROP DATABASE IF EXISTS ateliers_test_api');
});
for ($i = 0; $i < 50 && !@fsockopen('127.0.0.1', $port); $i++) usleep(100000);

// Appel : $get dans l'URL, $post dans le corps (form-urlencoded, comme le client).
function appel(array $get, array $post = [], ?array &$entetes = null): array
{
    global $port;
    $url = "http://127.0.0.1:$port/index.php?" . http_build_query($get);
    $ctx = stream_context_create(['http' => $post
        ? ['method' => 'POST', 'header' => 'Content-Type: application/x-www-form-urlencoded', 'content' => http_build_query($post), 'ignore_errors' => true]
        : ['ignore_errors' => true]]);
    $corps = file_get_contents($url, false, $ctx);
    $entetes = $http_response_header ?? [];
    $r = json_decode((string) $corps, true);
    return is_array($r) ? $r : ['_brut' => $corps];
}

// Champs d'un atelier côté client : relus dans contract.test.js (buildEntry).
preg_match('/function buildEntry.*?return \{(.*?)\.\.\.overrides/s', file_get_contents(__DIR__ . '/../contract.test.js'), $m);
preg_match_all('/^\s*(\w+)\s*:/m', $m[1] ?? '', $mm);
$champsClient = $mm[1];

echo "API — connexion\n";
$r = appel(['action' => 'getComptes'], [], $ent);
verifier(in_array('Access-Control-Allow-Origin: https://maswaddpt47-cmyk.github.io', $ent, true), 'CORS limité à github.io');
verifier($r['ok'] === true && array_column($r['comptes'], 'conseiller') === ['Conseiller Test', 'Nouveau Venu'], 'getComptes public : comptes actifs seuls');
verifier(!isset($r['comptes'][0]['role']) && !isset($r['comptes'][0]['actif']), 'getComptes public : ni rôle ni état');
verifier($r['maintenance'] === false && $r['maintenance_msg'] === '', 'getComptes public : état de maintenance');

$r = appel(['action' => 'checkPassword', 'conseiller' => 'Conseiller Test', 'password' => 'secret-test']);
verifier($r === ['ok' => false, 'error' => 'Paramètres manquants'], 'mot de passe dans l\'URL ignoré');
$r = appel(['action' => 'checkPassword'], ['conseiller' => 'Conseiller Test', 'password' => 'mauvais']);
verifier($r === ['ok' => false, 'error' => 'Mot de passe incorrect'], 'mauvais mot de passe refusé');
$r = appel(['action' => 'checkPassword'], ['conseiller' => 'Ancien Collegue', 'password' => 'x']);
verifier(($r['error'] ?? '') === 'Compte désactivé', 'compte inactif refusé');
$admin = appel(['action' => 'checkPassword'], ['conseiller' => 'Conseiller Test', 'password' => ' secret-test ']);
verifier($admin['ok'] === true && $admin['role'] === 'admin' && preg_match('/^[0-9a-f]{64}$/', $admin['token']), 'bon mot de passe : jeton et rôle');
$db->exec('USE ateliers_test_api');
verifier((int) $db->query("SELECT COUNT(*) FROM sessions WHERE jeton_hash = '" . hash('sha256', $admin['token']) . "'")->fetchColumn() === 1, 'seule l\'empreinte du jeton est en base');
$user = appel(['action' => 'checkPassword'], ['conseiller' => 'Nouveau Venu', 'password' => 'cd47nouveau']);
verifier($user['ok'] === true && ($user['doit_changer'] ?? false) === true, 'ancien mot de passe en clair : connexion, avec doit_changer');

for ($i = 0; $i < 5; $i++) appel(['action' => 'checkPassword'], ['conseiller' => 'Nouveau Venu', 'password' => 'faux']);
$r = appel(['action' => 'checkPassword'], ['conseiller' => 'Nouveau Venu', 'password' => 'cd47nouveau']);
verifier(str_starts_with($r['error'] ?? '', 'Trop de tentatives'), '5 échecs : compte bloqué, même avec le bon mot de passe');
verifier((int) $db->query("SELECT COUNT(*) FROM journal WHERE action = 'loginFail'")->fetchColumn() === 6, 'échecs journalisés');

echo "API — getAll\n";
$r = appel(['action' => 'getAll', 'year' => '2026']);
verifier(($r['ok'] ?? null) === false && ($r['auth'] ?? false) === true, 'sans jeton : refusé');
$r = appel(['action' => 'getAll', 'year' => '2026', 'token' => $admin['token']]);
verifier(($r['ok'] ?? null) === false, 'jeton dans l\'URL : refusé');
$r = appel(['action' => 'getAll', 'year' => '2026'], ['token' => $user['token']]);
verifier($r['ok'] === true, 'jeton dans le corps : accepté');
verifier(array_keys($r) === ['ok', 'entries', 'lists', 'visibility', 'conseiller_colors', 'emails', 'stockOrdinateurs', 'materielsCaches'], 'clés de la réponse = GAS NEWGEN : ' . implode(',', array_keys($r)));
verifier(count($r['entries']) === 2, '2026 seule : 2 ateliers');
$parId = array_column($r['entries'], null, '_id');
$e1 = $parId['entry_1'];
$manquants = array_diff($champsClient, array_keys($e1));
verifier(count($champsClient) >= 20 && $manquants === [], 'tous les champs de contract.test.js présents' . ($manquants ? ' — manque : ' . implode(',', $manquants) : ''));
verifier($e1['date'] === '2026-09-24' && $e1['horaire'] === '13:30' && $e1['inscrits'] === 6 && $e1['_n'] === 2, 'formats : date, heure, nombres');
verifier($e1['materiel'] === ['Classe mobile', 'Ordinateur'], 'matériel en tableau');
$e2 = $parId['entry_2'];
verifier($e2['horaire'] === '' && $e2['inscrits'] === '' && $e2['presents'] === 0 && $e2['materiel'] === [] && $e2['date_retour_materiel'] === '', 'vide → \'\', 0 reste 0');
verifier($r['lists']['statuts'] === ['Planifié', 'Réalisé'], 'liste en lignes découpée');
verifier($r['visibility'] === [] && $r['stockOrdinateurs'] === 14 && $r['materielsCaches'] === ['Tablette'], 'config : visibilité, stock, matériels cachés');
$brut = file_get_contents("http://127.0.0.1:$port/index.php?action=getAll", false, stream_context_create(['http' => ['method' => 'POST', 'header' => 'Content-Type: application/x-www-form-urlencoded', 'content' => 'year=2026&token=' . $user['token']]]));
verifier(str_contains($brut, '"visibility":{}') && str_contains($brut, '"conseiller_colors":{}'), 'objets vides rendus {} et non []');

$r = appel(['action' => 'getAll', 'years' => '2027,2026,2026'], ['token' => $user['token']]);
verifier($r['years'] === ['2026', '2027'] && count($r['entries']) === 3, 'years : triées, dédoublonnées, fusionnées');
verifier(appel(['action' => 'getAll', 'years' => '2024,2025,2026,2027'], ['token' => $user['token']])['error'] === 'Paramètre years invalide', 'years : 4 années refusées');

echo "API — maintenance et droits\n";
$db->exec("UPDATE config SET valeur = 'true' WHERE cle = 'maintenance'");
$r = appel(['action' => 'getAll', 'source' => 'admin'], ['token' => $user['token']]);
verifier($r === ['ok' => false, 'maintenance' => true, 'msg' => 'Retour à 14 h'], 'maintenance : conseiller bloqué, même avec source=admin');
verifier(appel(['action' => 'getAll'], ['token' => $admin['token']])['ok'] === true, 'maintenance : admin passe (rôle du jeton)');
$r = appel(['action' => 'getComptes']);
verifier($r['maintenance'] === true && $r['maintenance_msg'] === 'Retour à 14 h', 'maintenance visible avant connexion');
$r = appel(['action' => 'getComptes'], ['token' => $admin['token']]);
verifier(count($r['comptes']) === 3 && $r['comptes'][0] === ['conseiller' => 'Ancien Collegue', 'role' => 'user', 'actif' => 'NON'], 'getComptes admin : liste complète');
$r = appel(['action' => 'getComptes'], ['token' => $user['token']]);
verifier(!isset($r['comptes'][0]['role']), 'getComptes conseiller : liste réduite');
$r = appel(['action' => 'getConfig'], ['token' => $user['token']]);
verifier($r['ok'] === true && $r['config']['maintenance'] === 'true' && !isset($r['config']['admin_password']), 'getConfig avec jeton');
verifier(appel(['action' => 'getConfig'])['ok'] === false, 'getConfig sans jeton : refusé');
verifier(appel(['action' => 'getVisibility'], ['token' => $user['token']]) === ['ok' => true, 'visibility' => []], 'getVisibility');
$db->exec("UPDATE sessions SET expire = NOW() - INTERVAL 1 SECOND");
verifier(appel(['action' => 'getAll'], ['token' => $admin['token']])['auth'] ?? false, 'jeton expiré : refusé');
verifier(appel(['action' => 'saveEntry'])['error'] === 'action inconnue: saveEntry', 'écritures pas encore ouvertes');

echo $echecs ? "\n$echecs échec(s)\n" : "\nTous les tests passent.\n";
exit($echecs ? 1 : 0);
