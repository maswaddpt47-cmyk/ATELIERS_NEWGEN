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
require_once __DIR__ . '/../api/lib/ecriture.php';
require_once __DIR__ . '/../api/lib/api.php';

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
$c['Config'][] = ['emails', json_encode(['Nouveau Venu' => 'nouveau.venu@example.org'])];
$a = analyser($c);
if ($a['erreurs']) { echo "Classeur de test invalide :\n" . implode("\n", $a['erreurs']) . "\n"; exit(1); }
import_charger($db, $a, 'test');
$db->exec("UPDATE comptes SET role = 'user' WHERE conseiller = 'Nouveau Venu'");

$dossierMails = sys_get_temp_dir() . '/mails_' . getmypid();
@mkdir($dossierMails);
$dossierSauv = sys_get_temp_dir() . '/sauv_api_' . getmypid();
$cfg = tempnam(sys_get_temp_dir(), 'cfg');
file_put_contents($cfg, '<?php return ' . var_export(['db_hote' => $hote, 'db_nom' => 'ateliers_test_api', 'db_utilisateur' => $util, 'db_mot_de_passe' => $mdp], true) . ';');

// ── Serveur ───────────────────────────────────────────────────────────────
$port = 8700 + random_int(0, 99);
$srv = proc_open([PHP_BINARY, '-S', "127.0.0.1:$port", '-t', __DIR__ . '/../api'],
    [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $tubes, null,
    ['ATELIERS_API_CONFIG' => $cfg, 'ATELIERS_MAIL_TEST_DIR' => $dossierMails, 'ATELIERS_SAUVEGARDE_DIR' => $dossierSauv, 'PHP_CLI_SERVER_WORKERS' => '8'] + getenv());
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

echo "API — configuration\n";
verifier(api_chemin_config('/home/compte/www/api/lib') === '/home/compte/config-api.php'
    && api_chemin_config('/home/compte/www/api-sandbox/lib') === '/home/compte/config-api-sandbox.php',
    'bac à sable : sa propre configuration, jamais celle de la production (AG-019)');

echo "API — connexion\n";
$r = appel(['action' => 'getComptes'], [], $ent);
verifier(in_array('Access-Control-Allow-Origin: https://maswaddpt47-cmyk.github.io', $ent, true), 'CORS limité à github.io');
verifier($r['ok'] === true && array_column($r['comptes'], 'conseiller') === ['Ancien Collegue', 'Conseiller Test', 'Nouveau Venu'], 'getComptes public : tous les comptes (l\'interrupteur ne ferme que l\'Admin)');
verifier(!isset($r['comptes'][0]['role']) && !isset($r['comptes'][0]['actif']), '[RGPD-01] getComptes public : ni rôle ni état');
verifier(array_column(appel(['action' => 'getComptes', 'source' => 'admin'])['comptes'], 'conseiller') === ['Conseiller Test', 'Nouveau Venu'], 'getComptes page Admin : interrupteurs activés seuls');
verifier($r['maintenance'] === false && $r['maintenance_msg'] === '', 'getComptes public : état de maintenance');
$db->exec('USE ateliers_test_api');
$roleAvant = $db->query("SELECT role FROM comptes WHERE conseiller = 'Conseiller Test'")->fetchColumn();
$db->exec("UPDATE comptes SET role = 'superviseur' WHERE conseiller = 'Conseiller Test'");
verifier(!in_array('Conseiller Test', array_column(appel(['action' => 'getComptes'])['comptes'], 'conseiller'), true), 'getComptes Index : sans les superviseurs');
verifier(in_array('Conseiller Test', array_column(appel(['action' => 'getComptes', 'source' => 'admin'])['comptes'], 'conseiller'), true), 'getComptes page Admin : superviseur présent');
$db->exec('USE ateliers_test_api');
$db->prepare('UPDATE comptes SET role = ? WHERE conseiller = ?')->execute([$roleAvant, 'Conseiller Test']);

$r = appel(['action' => 'checkPassword', 'conseiller' => 'Conseiller Test', 'password' => 'secret-test']);
verifier($r === ['ok' => false, 'error' => 'Paramètres manquants'], 'mot de passe dans l\'URL ignoré');
$r = appel(['action' => 'checkPassword'], ['conseiller' => 'Conseiller Test', 'password' => 'mauvais']);
verifier($r === ['ok' => false, 'error' => 'Mot de passe incorrect'], 'mauvais mot de passe refusé');
$r = appel(['action' => 'checkPassword'], ['conseiller' => 'Ancien Collegue', 'password' => 'x', 'source' => 'admin.html']);
verifier(($r['error'] ?? '') === "Accès à l'Admin non autorisé pour ce compte", 'interrupteur désactivé : Admin refusé');
$r = appel(['action' => 'checkPassword'], ['conseiller' => 'Ancien Collegue', 'password' => 'x', 'source' => 'index.html']);
verifier(($r['ok'] ?? false) === true, 'interrupteur désactivé : Index ouvert');
$db->exec('USE ateliers_test_api');
$db->exec("INSERT INTO journal (horodatage, action, conseiller) VALUES (NOW() - INTERVAL 13 MONTH, 'login', 'Vieux'), (NOW() - INTERVAL 11 MONTH, 'login', 'Recent')");
$admin = appel(['action' => 'checkPassword'], ['conseiller' => 'Conseiller Test', 'password' => ' secret-test ']);
verifier($db->query("SELECT GROUP_CONCAT(conseiller) FROM journal WHERE conseiller IN ('Vieux','Recent')")->fetchColumn() === 'Recent', '[RGPD-03] journal : plus de 12 mois purgé à la connexion, 11 mois gardé');
verifier($admin['ok'] === true && $admin['role'] === 'admin' && preg_match('/^[0-9a-f]{64}$/', $admin['token']), 'bon mot de passe : jeton et rôle');
$db->exec('USE ateliers_test_api');
verifier((int) $db->query("SELECT COUNT(*) FROM sessions WHERE jeton_hash = '" . hash('sha256', $admin['token']) . "'")->fetchColumn() === 1, '[RGPD-05] seule l\'empreinte du jeton est en base');
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
verifier(array_keys($r) === ['ok', 'entries', 'lists', 'visibility', 'conseiller_colors', 'stockOrdinateurs', 'materielsCaches', 'conseillers_inactifs', 'rappelsFiches', 'tickets'], 'clés de la réponse conseiller = GAS NEWGEN sans emails : ' . implode(',', array_keys($r)));
// RGPD (25/09/2026) : les adresses mail ne vont qu'à l'Admin.
verifier(!str_contains(json_encode($r), 'nouveau.venu@example.org'), '[RGPD-15] getAll conseiller : aucune adresse mail');
$ra = appel(['action' => 'getAll', 'year' => '2026'], ['token' => $admin['token']]);
verifier(($ra['emails']['Nouveau Venu'] ?? '') === 'nouveau.venu@example.org', 'getAll admin : adresses mail présentes');
verifier($r['conseillers_inactifs'] === [], 'conseillers_inactifs vide : personne masqué du sélecteur');
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
verifier(!isset($r['comptes'][0]['role']), '[RGPD-02] getComptes conseiller : liste réduite');
$r = appel(['action' => 'getConfig'], ['token' => $user['token']]);
verifier($r['ok'] === true && $r['config']['maintenance'] === 'true' && !isset($r['config']['admin_password']), 'getConfig avec jeton');
verifier(appel(['action' => 'getConfig'])['ok'] === false, 'getConfig sans jeton : refusé');
verifier(!array_key_exists('emails', $r['config']), '[RGPD-15] getConfig conseiller : clé emails retirée');
verifier(isset(appel(['action' => 'getConfig'], ['token' => $admin['token']])['config']['emails']), 'getConfig admin : clé emails présente');
verifier(appel(['action' => 'getVisibility'], ['token' => $user['token']]) === ['ok' => true, 'visibility' => []], 'getVisibility');
echo "API — déconnexion\n";
// Un jeton rendu par la connexion ne doit plus rien valoir après logout.
$jetonTemp = bin2hex(random_bytes(32));
$db->prepare('INSERT INTO sessions (jeton_hash, conseiller, role, expire) VALUES (?, ?, ?, ?)')
   ->execute([hash('sha256', $jetonTemp), 'Nouveau Venu', 'user', date('Y-m-d H:i:s', time() + 3600)]);
verifier((appel(['action' => 'getVisibility'], ['token' => $jetonTemp])['ok'] ?? false) === true, 'jeton valable avant logout');
verifier(appel(['action' => 'logout'], ['token' => $jetonTemp]) === ['ok' => true], 'logout : ok');
verifier((appel(['action' => 'getVisibility'], ['token' => $jetonTemp])['auth'] ?? false) === true, '[RGPD-16] jeton refusé après logout');
verifier(appel(['action' => 'logout'], ['token' => 'nimportequoi']) === ['ok' => true], 'logout sans jeton valable : ok, rien d\'effacé');
verifier((appel(['action' => 'getVisibility'], ['token' => $user['token']])['ok'] ?? false) === true, 'logout ne touche pas les autres sessions');
echo "API — écritures d'ateliers\n";
$db->exec("UPDATE config SET valeur = 'false' WHERE cle = 'maintenance'");
$T = ['token' => $user['token']];
$nb = fn() => (int) $db->query('SELECT COUNT(*) FROM ateliers')->fetchColumn();
$lire = function (string $id) use ($user) {
    $r = appel(['action' => 'getAll', 'years' => '2026,2027'], ['token' => $user['token']]);
    return array_column($r['entries'], null, '_id')[$id] ?? null;
};
verifier((appel(['action' => 'saveEntry'], ['entry' => '{}'])['auth'] ?? false) === true, 'saveEntry sans jeton : refusé');
$avant = $nb();
$nouveau = ['statut' => 'Planifié', 'date' => '2026-10-05', 'horaire' => '9:30', 'ampm' => 'AM', 'thematique' => 'Mails',
            'commune' => 'Nérac', 'conseiller' => 'Conseiller Test', 'inscrits' => 5, 'presents' => '', 'materiel' => ['Vidéoprojecteur', 'Classe mobile']];
$r = appel(['action' => 'saveEntry'], $T + ['entry' => json_encode($nouveau)]);
verifier($r['ok'] === true && str_starts_with($r['_id'], 'entry_') && $nb() === $avant + 1, 'création sans _id : identifiant attribué');
$e = $lire($r['_id']);
verifier($e['horaire'] === '09:30' && $e['presents'] === '' && $e['inscrits'] === 5 && $e['_n'] === 3, 'relu : heure normalisée, vide gardé, numéro suivant');
verifier($e['materiel'] === ['Classe mobile', 'Videoprojecteur'], 'matériel rapproché du nom de colonne (« Vidéoprojecteur » → Videoprojecteur)');

$maj = ['_id' => 'entry_1', '_n' => '', 'statut' => 'Réalisé', 'date' => '2026-09-24', 'horaire' => '13:30', 'presents' => '5', 'conseiller' => 'Conseiller Test', 'materiel' => 'Ordinateur|Tablette'];
$avant = $nb();
appel(['action' => 'saveEntry'], $T + ['entry' => json_encode($maj)]);
$r = appel(['action' => 'saveEntry'], $T + ['entry' => json_encode($maj)]);   // rejeu
$e = $lire('entry_1');
verifier($r === ['ok' => true, '_id' => 'entry_1'] && $nb() === $avant, 'mise à jour rejouée : pas de doublon');
verifier($e['statut'] === 'Réalisé' && $e['presents'] === 5 && $e['_n'] === 2 && $e['materiel'] === ['Ordinateur', 'Tablette'], 'mise à jour : champs, numéro conservé, matériel remplacé');
$r = appel(['action' => 'saveEntry'], $T + ['entry' => json_encode(['_id' => 'entry_x', 'date' => '24/09/2026'])]);
verifier($r['ok'] === false && str_contains($r['error'], 'date') && $lire('entry_x') === null, 'date invalide : refusée, rien écrit');
$r = appel(['action' => 'saveEntry'], $T + ['entry' => json_encode(['_id' => "x\r\nEND:VEVENT", 'date' => '2026-11-02'])]);
verifier($r === ['ok' => false, 'error' => '_id invalide'], '_id avec retour à la ligne : refusé (faux événement dans l\'export agenda)');

$r = appel(['action' => 'saveMany'], $T + ['entries' => json_encode([['_id' => 'lot_1', 'date' => '2026-11-02'], ['_id' => 'lot_2', 'date' => 'demain']])]);
verifier($r['ok'] === false && str_contains($r['error'], '"idx":1') && $lire('lot_1') !== null, 'saveMany : erreur rapportée par position, entrée valide écrite');
verifier(appel(['action' => 'saveMany'], $T + ['entries' => '[{"_id":"lot_3","date":"2026-11-03"}]']) === ['ok' => true, 'count' => 1], 'saveMany valide');
verifier(appel(['action' => 'verifierIds'], $T + ['ids' => 'lot_1,absent,lot_3']) === ['ok' => true, 'presents' => ['lot_1', 'lot_3']], 'verifierIds');
verifier(appel(['action' => 'delete'], $T + ['_id' => 'lot_1']) === ['ok' => true] && $lire('lot_1') === null, 'suppression');
verifier(appel(['action' => 'delete'], $T + ['_id' => 'lot_1'])['error'] === 'Entrée introuvable', 'suppression rejouée : introuvable');
verifier((int) $db->query("SELECT COUNT(*) FROM ateliers_materiel WHERE atelier_id = 'lot_1'")->fetchColumn() === 0, 'matériel supprimé avec l\'atelier');
verifier((int) $db->query("SELECT COUNT(*) FROM journal WHERE action = 'saveEntry' AND ref = 'entry_1'")->fetchColumn() === 2, 'écritures journalisées');
// AM/PM (AG-018) : écrit d'après l'heure de début, quoi qu'envoie le client.
appel(['action' => 'saveEntry'], $T + ['entry' => json_encode(['horaire' => '12:00', 'ampm' => 'AM'] + $maj)]);
$a = $lire('entry_1')['ampm'];
appel(['action' => 'saveEntry'], $T + ['entry' => json_encode(['horaire' => '11:59', 'ampm' => 'PM'] + $maj)]);
verifier($a === 'PM' && $lire('entry_1')['ampm'] === 'AM', 'AM/PM calculé par l\'API d\'après l\'heure de début (12:00 → PM, 11:59 → AM)');
appel(['action' => 'saveEntry'], $T + ['entry' => json_encode($maj)]);
// Durée (AG-017) : enregistrée, gardée quand un ancien client ne l'envoie pas, bornée.
appel(['action' => 'saveEntry'], $T + ['entry' => json_encode($maj + ['duree' => 120])]);
appel(['action' => 'saveEntry'], $T + ['entry' => json_encode($maj)]);
verifier($lire('entry_1')['duree'] === 120, 'durée enregistrée, gardée par un envoi sans durée (client en cache)');
$r = appel(['action' => 'saveEntry'], $T + ['entry' => json_encode($maj + ['duree' => 45])]);
verifier($r['ok'] === false && str_contains($r['error'], 'duree') && $lire('entry_1')['duree'] === 120, 'durée hors demi-heure : refusée');
// Fiche bilan (AG-020) : objet validé, gardé par un envoi sans bilan, refus hors liste.
$bilan = ['niveau' => 'Débutant', 'objectif' => 'Partiellement', 'difficultes' => ['Connexion', 'Absences'], 'supports' => ['Diaporama'], 'suite' => 'Nouvel atelier'];
appel(['action' => 'saveEntry'], $T + ['entry' => json_encode($maj + ['fiche_bilan' => $bilan])]);
appel(['action' => 'saveEntry'], $T + ['entry' => json_encode($maj)]);
verifier($lire('entry_1')['fiche_bilan'] === $bilan, 'bilan enregistré tel quel, gardé par un envoi sans bilan (client en cache)');
$r = appel(['action' => 'saveEntry'], $T + ['entry' => json_encode($maj + ['fiche_bilan' => ['objectif' => 'Peut-être']])]);
verifier($r['ok'] === false && str_contains($r['error'], 'fiche bilan') && $lire('entry_1')['fiche_bilan'] === $bilan, 'bilan : valeur hors liste refusée');
appel(['action' => 'saveEntry'], $T + ['entry' => json_encode($maj + ['fiche_bilan' => ['difficultes' => ['Autre'], 'difficultes_autre' => 'Coupure de courant']])]);
$b1 = $lire('entry_1')['fiche_bilan'];
appel(['action' => 'saveEntry'], $T + ['entry' => json_encode($maj + ['fiche_bilan' => ['difficultes' => ['Connexion'], 'difficultes_autre' => 'oublié']])]);
verifier(($b1['difficultes_autre'] ?? '') === 'Coupure de courant' && !isset($lire('entry_1')['fiche_bilan']['difficultes_autre']), 'précision « Autre » gardée seulement si « Autre » est coché');
appel(['action' => 'saveEntry'], $T + ['entry' => json_encode($maj + ['fiche_bilan' => ''])]);
verifier($lire('entry_1')['fiche_bilan'] === '', 'bilan vidé');
// Amendement B : une valeur retirée des listes n'empêche pas de modifier l'atelier.
$db->exec("UPDATE ateliers SET fiche_bilan = '{\"supports\":[\"Rétroprojecteur\"]}' WHERE id = 'entry_1'");
$ancien = $lire('entry_1')['fiche_bilan'];
$r = appel(['action' => 'saveEntry'], $T + ['entry' => json_encode(['horaire' => '15:00', 'fiche_bilan' => $ancien] + $maj)]);
verifier(($r['ok'] ?? false) && $lire('entry_1')['horaire'] === '15:00' && $lire('entry_1')['fiche_bilan'] === $ancien, 'fiche avec un choix retiré, renvoyée telle quelle : l\'atelier reste modifiable');
appel(['action' => 'saveEntry'], $T + ['entry' => json_encode($maj + ['fiche_bilan' => ''])]);
verifier($db->query("SELECT conseiller FROM journal WHERE action = 'delete' ORDER BY id DESC LIMIT 1")->fetchColumn() === 'Nouveau Venu', 'suppression journalisée au nom de la personne connectée');

echo "API — administration\n";
$A = ['token' => $admin['token']];
// Corbeille (AG-014) : lot_1, supprimé plus haut, doit y être.
verifier(str_contains(appel(['action' => 'getCorbeille'], $T)['error'] ?? '', 'administrateurs'), 'corbeille réservée aux administrateurs');
$cb = appel(['action' => 'getCorbeille'], $A);
verifier(($cb['ok'] ?? false) && in_array('lot_1', array_column($cb['ateliers'], '_id'), true) && $cb['ateliers'][0]['supprime_par'] === 'Nouveau Venu', 'atelier supprimé : dans la corbeille, avec son auteur');
$nAvant = json_decode($db->query("SELECT donnees FROM ateliers_corbeille WHERE id = 'lot_1'")->fetchColumn(), true)['_n'];
$lot1 = array_values(array_filter($cb['ateliers'], fn($a) => $a['_id'] === 'lot_1'))[0];
verifier((int) $lot1['_n'] === (int) $nAvant && array_key_exists('lieu', $lot1) && array_key_exists('orienteur', $lot1), 'corbeille : numéro, lieu et orienteur fournis pour repérer l\'atelier');
$db->exec("INSERT INTO ateliers (id, n, statut, date, horaire, thematique, conseiller, commune, orienteur, lieu, co_animateur, residence, remarques)
           VALUES ('bouche_trou', 999, 'Planifié', '2026-01-01', '', '', '', '', '', '', '', '', '')");   // MAX(n)+1 ≠ numéro d'origine
$r = appel(['action' => 'restaurerCorbeille'], $A + ['_id' => 'lot_1']);
$db->exec("DELETE FROM ateliers WHERE id = 'bouche_trou'");
verifier(($r['ok'] ?? false) && ($r['entry']['_id'] ?? '') === 'lot_1' && $r['entry']['date'] === '2026-11-02' && $lire('lot_1') !== null, 'restauration : atelier revenu à l\'identique');
verifier($nAvant > 0 && ($r['entry']['_n'] ?? 0) === $nAvant, "restauration : numéro #N d'origine repris, pas le suivant");
verifier(!in_array('lot_1', array_column(appel(['action' => 'getCorbeille'], $A)['ateliers'], '_id'), true), 'restauré : retiré de la corbeille');
appel(['action' => 'delete'], $T + ['_id' => 'lot_1']);
appel(['action' => 'saveEntry'], $T + ['entry' => json_encode(['_id' => 'lot_1', 'date' => '2026-12-01'])]);
$r = appel(['action' => 'restaurerCorbeille'], $A + ['_id' => 'lot_1']);
verifier(($r['ok'] ?? true) === false && str_contains($r['error'], 'existe déjà') && $lire('lot_1')['date'] === '2026-12-01', 'restauration refusée si l\'atelier a été recréé : jamais d\'écrasement');
$db->exec("UPDATE ateliers_corbeille SET supprime_le = NOW() - INTERVAL 31 DAY");
appel(['action' => 'checkPassword'], ['conseiller' => 'Conseiller Test', 'password' => 'secret-test']);
verifier((int) $db->query('SELECT COUNT(*) FROM ateliers_corbeille')->fetchColumn() === 0, 'corbeille purgée au-delà de 30 jours, dès une connexion');
appel(['action' => 'saveEntry'], $T + ['entry' => json_encode(['_id' => 'num_a', 'date' => '2026-12-02'])]);
$nA = $lire('num_a')['_n'];
appel(['action' => 'delete'], $T + ['_id' => 'num_a']);
appel(['action' => 'saveEntry'], $T + ['entry' => json_encode(['_id' => 'num_b', 'date' => '2026-12-03'])]);
verifier($lire('num_b')['_n'] === $nA + 1, 'nouvel atelier : ne reprend pas le numéro d\'un atelier de la corbeille');
appel(['action' => 'delete'], $T + ['_id' => 'num_b']);
$db->exec("DELETE FROM ateliers_corbeille WHERE id IN ('num_a', 'num_b')");
// Sauvegardes (AG-014) : état en lecture, copie à la demande limitée.
verifier(str_contains(appel(['action' => 'copieMaintenant'], $T)['error'] ?? '', 'administrateurs'), 'copie à la demande réservée aux administrateurs');
$r = appel(['action' => 'copieMaintenant'], $A);
$e = appel(['action' => 'etatSauvegardes'], $A);
verifier(($r['ok'] ?? false) && count($e['copies'] ?? []) === 1 && $e['copies'][0]['nom'] === $r['fichier'] && $e['chiffree'] === '', 'copie à la demande faite, visible dans l\'état des sauvegardes');
verifier(str_contains(appel(['action' => 'copieMaintenant'], $A)['error'] ?? '', '5 minutes'), 'seconde copie dans les 5 minutes : refusée');
file_put_contents("$dossierSauv/.derniere-copie-chiffree", '2026-09-25 04:15');
verifier(appel(['action' => 'etatSauvegardes'], $A)['chiffree'] === '2026-09-25 04:15', 'date de la dernière copie chiffrée lue');
exec('rm -rf ' . escapeshellarg($dossierSauv));
// Usage des onglets (29/09/2026) : compteurs anonymes.
verifier(appel(['action' => 'usageOnglets'], ['site' => 'newgen', 'page' => 'index', 'vues' => '{"historique":1}'])['auth'] ?? false, 'usage des onglets sans jeton : refusé');
$r = appel(['action' => 'usageOnglets'], $T + ['site' => 'newgen', 'page' => 'index', 'vues' => json_encode(['historique' => 3, 'agenda' => 1, 'Nom Suspect' => 2, 'carte' => 0])]);
verifier(($r['ok'] ?? false) && $r['enregistres'] === 2, 'usage des onglets : noms douteux et zéros ignorés');
appel(['action' => 'usageOnglets'], $T + ['site' => 'newgen', 'page' => 'index', 'vues' => '{"historique":2}']);
verifier((int) $db->query("SELECT vues FROM usage_onglets WHERE site = 'newgen' AND page = 'index' AND onglet = 'historique' AND jour = '" . date('Y-m-d') . "'")->fetchColumn() === 5, 'usage des onglets : les envois du jour s\'additionnent');
verifier((appel(['action' => 'usageOnglets'], $T + ['site' => 'ailleurs', 'page' => 'index', 'vues' => '{"historique":1}'])['ok'] ?? true) === false, 'usage des onglets : site inconnu refusé');
$cols = $db->query('SHOW COLUMNS FROM usage_onglets')->fetchAll(PDO::FETCH_COLUMN);
verifier($cols === ['jour', 'site', 'page', 'onglet', 'vues'], '[RGPD-18] usage des onglets : aucune colonne ne désigne une personne');
verifier(str_contains(appel(['action' => 'getUsageOnglets'], $T)['error'] ?? '', 'administrateurs'), 'usage des onglets : lecture réservée aux administrateurs');
$u = appel(['action' => 'getUsageOnglets'], $A)['usage'] ?? [];
verifier(count(array_filter($u, fn($l) => $l['onglet'] === 'historique' && $l['vues'] === 5 && preg_match('/^\d{4}-S\d{2}$/', $l['semaine']))) === 1, 'usage des onglets : totaux par semaine pour l\'Admin');
appel(['action' => 'logAccesIndex'], $T + ['site' => 'nextstep']);
appel(['action' => 'logAccesIndex'], $T + ['site' => 'pirate']);
$sites = $db->query("SELECT site FROM journal WHERE action = 'accesIndex' ORDER BY id DESC LIMIT 2")->fetchAll(PDO::FETCH_COLUMN);
verifier($sites === ['', 'nextstep'], 'journal : site d\'origine noté, valeur inconnue ignorée (' . implode(',', $sites) . ')');
verifier(array_key_exists('site', appel(['action' => 'getLogs'], $A)['logs'][0] ?? []), 'getLogs : champ site rendu');
$roles = $db->query("SELECT DISTINCT role FROM journal WHERE action IN ('saveEntry', 'delete') AND conseiller = 'Conseiller Test'")->fetchAll(PDO::FETCH_COLUMN);
verifier($roles === ['admin'], 'journal : écritures d\'ateliers avec le rôle du compte (' . implode(',', $roles) . ')');
verifier(str_contains(appel(['action' => 'setConfig'], $T + ['key' => 'stock_ordinateurs', 'value' => '20'])['error'] ?? '', 'administrateurs'), 'action admin refusée à un conseiller');
appel(['action' => 'setConfig'], $A + ['key' => 'stock_ordinateurs', 'value' => '20']);
appel(['action' => 'saveVisibility'], $A + ['visibility' => json_encode(['saisie' => true, 'carte' => false])]);
verifier(appel(['action' => 'getAll'], $A)['stockOrdinateurs'] === 20, 'setConfig relu par getAll');
// Fenêtre « fiche bilan » (27/10/2026) : getAll dit qui recevra le mail de
// rappel d'une fiche vide — rien si l'interrupteur est éteint, des noms sans adresse.
verifier(array_key_exists('rappelsFiches', appel(['action' => 'getAll'], $T)) && appel(['action' => 'getAll'], $T)['rappelsFiches'] === null, 'rappel des fiches éteint : getAll n\'annonce aucun mail');
appel(['action' => 'setConfig'], $A + ['key' => 'rappels_fiches', 'value' => 'true']);
$rf = appel(['action' => 'getAll'], $T)['rappelsFiches'] ?? null;
verifier(is_array($rf) && $rf['depuis'] === '2026-11-01' && in_array('Nouveau Venu', $rf['conseillers'], true) && !str_contains(json_encode($rf), '@'), 'rappel des fiches allumé : date de départ et destinataires, sans adresse — ' . json_encode($rf));
appel(['action' => 'setConfig'], $A + ['key' => 'rappels_fiches', 'value' => 'false']);
verifier(appel(['action' => 'getVisibility'], $A)['visibility'] === ['saisie' => true, 'carte' => false], 'saveVisibility relu');
appel(['action' => 'saveVisibility'], $A + ['visibility' => json_encode(['saisie' => true, 'carte' => false, 'corbeille' => true])]);
verifier((appel(['action' => 'getCorbeille'], $T)['ok'] ?? false) === true, 'corbeille rendue visible sur Index : ouverte aux conseillers');
// AG-034 (27/10/2026) : un conseiller ne voit et ne restaure que ses ateliers
// (animés ou co-animés) ; l'admin voit tout.
$ins = $db->prepare("INSERT INTO ateliers_corbeille (id, donnees, supprime_le, supprime_par) VALUES (?, ?, NOW(), 'Admin')");
foreach (['cb_mien' => ['conseiller' => 'Nouveau Venu'], 'cb_co' => ['conseiller' => 'Ancien Collegue', 'co_animateur' => 'Nouveau Venu'], 'cb_autre' => ['conseiller' => 'Ancien Collegue', 'co_animateur' => '']] as $id => $qui)
    $ins->execute([$id, json_encode(['_id' => $id, 'date' => '2026-12-05'] + $qui)]);
$vus = array_column(appel(['action' => 'getCorbeille'], $T)['ateliers'] ?? [], '_id');
verifier(in_array('cb_mien', $vus, true) && in_array('cb_co', $vus, true) && !in_array('cb_autre', $vus, true), 'corbeille d\'un conseiller : ses ateliers animés ou co-animés, pas ceux des autres');
verifier(in_array('cb_autre', array_column(appel(['action' => 'getCorbeille'], $A)['ateliers'], '_id'), true), 'corbeille de l\'admin : tous les ateliers');
$r = appel(['action' => 'restaurerCorbeille'], $T + ['_id' => 'cb_autre']);
verifier(($r['ok'] ?? true) === false && str_contains($r['error'], 'absent') && $lire('cb_autre') === null && (int) $db->query("SELECT COUNT(*) FROM ateliers_corbeille WHERE id = 'cb_autre'")->fetchColumn() === 1, 'atelier d\'un autre : restauration refusée, sans dire qu\'il existe');
$ins->execute(['cb_casse', json_encode(['_id' => 'cb_casse', 'date' => '2026-12-05', 'conseiller' => 'NOUVEAU VENU'])]);
$cbT = appel(['action' => 'getCorbeille'], $T);
verifier(in_array('cb_casse', array_column($cbT['ateliers'], '_id'), true) && $cbT['filtre'] === true && appel(['action' => 'getCorbeille'], $A)['filtre'] === false, 'corbeille : majuscules ignorées comme pour les avis ; filtre signalé au conseiller seulement');
$r = appel(['action' => 'restaurerCorbeille'], $T + ['_id' => 'cb_co']);
verifier(($r['ok'] ?? false) && $lire('cb_co') !== null, 'co-animateur : restauration acceptée');
$db->exec("DELETE FROM ateliers_corbeille WHERE id IN ('cb_mien', 'cb_autre', 'cb_casse')");
$db->exec("DELETE FROM ateliers WHERE id = 'cb_co'");
verifier(str_contains(appel(['action' => 'saveVisibility'], $T + ['visibility' => '{}'])['error'] ?? '', 'administrateurs'), 'corbeille ouverte : les autres actions admin restent fermées');
appel(['action' => 'saveVisibility'], $A + ['visibility' => json_encode(['saisie' => true, 'carte' => false])]);
verifier(str_contains(appel(['action' => 'restaurerCorbeille'], $T + ['_id' => 'x'])['error'] ?? '', 'administrateurs'), 'corbeille masquée sur Index : refermée aux conseillers');
// Suppression définitive (13/10/2026, AG-024 amendé par Codex) : admin seulement,
// même corbeille ouverte ; liste JSON ; un atelier par ligne de journal ;
// avis et jeton QR partent avec l'atelier.
$idsLongs = ['atelier_1760000000001_abcde', 'atelier_1760000000002_abcde', 'atelier_1760000000003_abcde', 'atelier_1760000000004_abcde', 'pur,virgule'];
foreach ($idsLongs as $i => $id) {
    appel(['action' => 'saveEntry'], $T + ['entry' => json_encode(['_id' => $id, 'date' => '2026-12-0' . ($i + 4)])]);
    if ($i === 0) appel(['action' => 'jetonAvis'], $T + ['_id' => $id]);   // crée les tables des avis
    appel(['action' => 'delete'], $T + ['_id' => $id]);
}
appel(['action' => 'saveEntry'], $T + ['entry' => json_encode(['_id' => 'pur', 'date' => '2026-12-10'])]);
appel(['action' => 'delete'], $T + ['_id' => 'pur']);
$db->prepare("INSERT INTO avis (atelier_id, cree_le, attentes) VALUES (?, NOW(), 4)")->execute([$idsLongs[0]]);
$db->prepare("REPLACE INTO avis_jetons (atelier_id, jeton, cree_le) VALUES (?, ?, NOW())")->execute([$idsLongs[0], str_repeat('b', 32)]);
appel(['action' => 'saveVisibility'], $A + ['visibility' => json_encode(['saisie' => true, 'corbeille' => true])]);
verifier(str_contains(appel(['action' => 'purgerCorbeille'], $T + ['ids' => json_encode([$idsLongs[0]])])['error'] ?? '', 'administrateurs'), 'suppression définitive refusée à un conseiller, même corbeille ouverte');
appel(['action' => 'saveVisibility'], $A + ['visibility' => json_encode(['saisie' => true, 'carte' => false])]);
$r = appel(['action' => 'purgerCorbeille'], $A + ['ids' => json_encode(array_merge($idsLongs, [$idsLongs[0]]))]);
$reste = (int) $db->query("SELECT COUNT(*) FROM ateliers_corbeille WHERE id IN ('" . implode("','", $idsLongs) . "')")->fetchColumn();
verifier(($r['ok'] ?? false) && $r['supprimes'] === 5 && $reste === 0, 'suppression définitive : les ateliers cochés quittent la corbeille');
verifier($db->query("SELECT COUNT(*) FROM ateliers_corbeille WHERE id = 'pur'")->fetchColumn() == 1, 'suppression définitive : « pur,virgule » ne vise pas « pur » (liste JSON, pas de séparateur)');
$journal = $db->query("SELECT ref FROM journal WHERE action = 'purgerCorbeille' ORDER BY id DESC LIMIT 5")->fetchAll(PDO::FETCH_COLUMN);
sort($journal); $attendu = $idsLongs; sort($attendu);
verifier($journal === $attendu, 'suppression définitive : une ligne de journal par atelier, identifiant complet');
verifier($db->query("SELECT COUNT(*) FROM avis WHERE atelier_id = '{$idsLongs[0]}'")->fetchColumn() == 0 && $db->query("SELECT COUNT(*) FROM avis_jetons WHERE atelier_id = '{$idsLongs[0]}'")->fetchColumn() == 0, 'suppression définitive : avis et jeton QR effacés avec l\'atelier');
verifier((appel(['action' => 'purgerCorbeille'], $A + ['ids' => json_encode(array_map('strval', range(1, 201)))])['ok'] ?? true) === false, 'suppression définitive : 200 ateliers au plus par envoi');
verifier((appel(['action' => 'purgerCorbeille'], $A + ['_id' => 'pur'])['ok'] ?? true) === false, 'suppression définitive : l\'ancien format « _id » séparé par des virgules est refusé');
$db->exec("DELETE FROM ateliers_corbeille WHERE id = 'pur'");
appel(['action' => 'saveLists'], $A + ['lists' => json_encode(['statuts' => ['Planifié'], 'conseillers' => ['Conseiller Test', 'Nouvelle Recrue'], 'publics' => [], 'materiels' => ['Ordinateur']])]);
verifier(appel(['action' => 'getAll'], $A)['lists']['conseillers'] === ['Conseiller Test', 'Nouvelle Recrue'], 'saveLists relu');
verifier($db->query("SELECT hash IS NULL FROM comptes WHERE conseiller = 'Nouvelle Recrue'")->fetchColumn() == 1, 'compte créé par saveLists, sans mot de passe');
verifier(appel(['action' => 'checkPassword'], ['conseiller' => 'Nouvelle Recrue', 'password' => 'cd47nouvelle'])['ok'] === false, 'plus de mot de passe cd47+prénom');
$r = appel(['action' => 'resetPassword'], $A + ['conseiller' => 'Nouvelle Recrue']);
verifier($r['ok'] === true && strlen($r['newPassword']) === 12, 'resetPassword : mot de passe provisoire aléatoire');
$nr = appel(['action' => 'checkPassword'], ['conseiller' => 'Nouvelle Recrue', 'password' => $r['newPassword']]);
verifier($nr['ok'] === true && ($nr['doit_changer'] ?? false) === true, 'provisoire : connexion avec changement exigé');
verifier(appel(['action' => 'selfSetPassword'], ['token' => $nr['token'], 'password' => 'court'])['error'] === API_MDP_POLITIQUE, 'selfSetPassword : politique appliquée');
verifier(appel(['action' => 'selfSetPassword'], ['token' => $nr['token'], 'password' => 'Un-Mot-De-Passe-7'])['ok'] === true, 'selfSetPassword');
$nr = appel(['action' => 'checkPassword'], ['conseiller' => 'Nouvelle Recrue', 'password' => 'Un-Mot-De-Passe-7']);
verifier($nr['ok'] === true && !isset($nr['doit_changer']), 'nouveau mot de passe : plus de changement exigé');
// Audit Codex du 06/10/2026 (n° 3 et 5) : changer de mot de passe ferme les
// autres connexions et les liens « mot de passe oublié » en cours, pas la sienne.
$nr2 = appel(['action' => 'checkPassword'], ['conseiller' => 'Nouvelle Recrue', 'password' => 'Un-Mot-De-Passe-7']);
require_once __DIR__ . '/../api/lib/reinit.php';
reinit_schema($db);
$db->prepare('INSERT INTO reinitialisations (jeton_hash, conseiller, cree, expire) VALUES (?, ?, NOW(), NOW() + INTERVAL 30 MINUTE)')->execute([hash('sha256', 'lien-copie'), 'Nouvelle Recrue']);
// AG-023 (option 1) : hors mot de passe provisoire, l'actuel est exigé.
verifier(appel(['action' => 'selfSetPassword'], ['token' => $nr2['token'], 'password' => 'Un-Mot-De-Passe-7'])['error'] === 'Mot de passe actuel requis'
    && appel(['action' => 'selfSetPassword'], ['token' => $nr2['token'], 'currentPwd' => 'Pas-Le-Bon-1', 'password' => 'Un-Mot-De-Passe-7'])['error'] === 'Mot de passe actuel incorrect',
    'selfSetPassword hors provisoire : mot de passe actuel exigé, faux refusé');
$db->exec("DELETE FROM tentatives");
appel(['action' => 'selfSetPassword'], ['token' => $nr2['token'], 'currentPwd' => 'Un-Mot-De-Passe-7', 'password' => 'Un-Mot-De-Passe-7']);
verifier((appel(['action' => 'getAll'], ['token' => $nr2['token']])['ok'] ?? false) === true
    && (appel(['action' => 'getAll'], ['token' => $nr['token']])['auth'] ?? false) === true
    && (int) $db->query("SELECT utilise FROM reinitialisations WHERE conseiller = 'Nouvelle Recrue'")->fetchColumn() === 1,
    'changement de mot de passe : sa connexion reste, les autres et les liens en cours tombent');
verifier(appel(['action' => 'setPassword'], $A + ['conseiller' => 'Nouvelle Recrue', 'password' => 'Autre-Mot-De-Passe-8'])['ok'] === false, 'setPassword sans mot de passe actuel : refusé');
verifier(appel(['action' => 'setPassword'], $A + ['conseiller' => 'Nouvelle Recrue', 'currentPwd' => 'Pas-Le-Bon-1', 'password' => 'Autre-Mot-De-Passe-8'])['error'] === 'Mot de passe actuel incorrect', 'setPassword : mot de passe actuel faux refusé');
verifier(appel(['action' => 'setPassword', 'currentPwd' => 'Un-Mot-De-Passe-7'], $A + ['conseiller' => 'Nouvelle Recrue', 'password' => 'Autre-Mot-De-Passe-8'])['ok'] === false, 'setPassword : mot de passe actuel dans l\'URL ignoré');
verifier(appel(['action' => 'setPassword'], $A + ['conseiller' => 'Nouvelle Recrue', 'currentPwd' => 'Un-Mot-De-Passe-7', 'password' => 'Autre-Mot-De-Passe-8'])['ok'] === true, 'setPassword avec mot de passe actuel');
$db->exec("DELETE FROM tentatives");
// Nouvelle connexion : setPassword vient de fermer les précédentes.
$nr = appel(['action' => 'checkPassword'], ['conseiller' => 'Nouvelle Recrue', 'password' => 'Autre-Mot-De-Passe-8']);
appel(['action' => 'saveCompte'], $A + ['conseiller' => 'Nouvelle Recrue', 'actif' => 'NON']);
verifier((appel(['action' => 'getAll'], ['token' => $nr['token']])['ok'] ?? false) === true, 'interrupteur désactivé : la connexion Index continue');
appel(['action' => 'saveCompte'], $A + ['conseiller' => 'Nouvelle Recrue', 'role' => 'superviseur']);
verifier((appel(['action' => 'getAll'], ['token' => $nr['token']])['auth'] ?? false) === true, 'rôle changé : sa connexion tombe aussitôt');
$db->exec("UPDATE comptes SET actif = 0 WHERE conseiller = 'Conseiller Test'");
verifier(str_contains(appel(['action' => 'getLogs'], $A)['error'] ?? '', 'administrateurs'), 'admin à l\'interrupteur désactivé : actions Admin refusées même avec un jeton déjà obtenu');
verifier(!isset(appel(['action' => 'getComptes'], $A)['comptes'][0]['role']), 'admin à l\'interrupteur désactivé : liste réduite');
$db->exec("UPDATE comptes SET actif = 1 WHERE conseiller = 'Conseiller Test'");
verifier(appel(['action' => 'saveCompte'], $A + ['conseiller' => 'Nouvelle Recrue', 'role' => 'roi'])['error'] === 'Rôle inconnu', 'saveCompte : rôle contrôlé');
$j = $db->query("SELECT conseiller, ref, role FROM journal WHERE action = 'saveCompte' ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
verifier($j === ['conseiller' => 'Conseiller Test', 'ref' => 'Nouvelle Recrue', 'role' => 'admin'], 'action admin journalisée (auteur, cible) : ' . json_encode($j, JSON_UNESCAPED_UNICODE));
$r = appel(['action' => 'getLogs'], $A + ['n' => 3]);
verifier(count($r['logs']) === 3 && str_ends_with($r['logs'][0]['timestamp'], 'Z') && is_bool($r['logs'][0]['success']), 'getLogs : n dernières lignes, format GAS');
// AG-036 (28/10/2026) : lecture pure hors journal ; lignes Admin écartées avant la limite.
verifier((int) $db->query("SELECT COUNT(*) FROM journal WHERE action = 'getUsageOnglets'")->fetchColumn() === 0, 'getUsageOnglets : lecture, non journalisée');
// Toutes les lignes non Admin tiennent dans 1000 : période = depuis la plus ancienne d'entre elles.
$nbAdmin = (int) $db->query("SELECT COUNT(*) FROM journal WHERE role = 'admin' AND succes = 1 AND id >= (SELECT MIN(id) FROM journal WHERE NOT (role = 'admin' AND succes = 1))")->fetchColumn();
$r = appel(['action' => 'getLogs'], $A + ['n' => 1000, 'sansAdmin' => 1]);
verifier($nbAdmin > 0 && $r['logs'] && !array_filter($r['logs'], fn($l) => $l['role'] === 'admin' && $l['success']) && $r['nbMasques'] === $nbAdmin,
    "getLogs sansAdmin : aucune ligne Admin réussie, nbMasques = $nbAdmin (" . ($r['nbMasques'] ?? '?') . ')');
$r1 = appel(['action' => 'getLogs'], $A + ['n' => 1, 'sansAdmin' => 1]);
$apres = (int) $db->query("SELECT COUNT(*) FROM journal WHERE role = 'admin' AND succes = 1 AND id >= (SELECT MAX(id) FROM journal WHERE NOT (role = 'admin' AND succes = 1))")->fetchColumn();
verifier(count($r1['logs']) === 1 && $r1['nbMasques'] === $apres, "getLogs sansAdmin : nbMasques compté sur la période rendue ($apres)");
// Journal entièrement masqué : total sur tout le journal, marqué comme tel (Codex n° 2).
$db->exec("CREATE TEMPORARY TABLE journal_sauve AS SELECT * FROM journal WHERE NOT (role = 'admin' AND succes = 1)");
$db->exec("DELETE FROM journal WHERE NOT (role = 'admin' AND succes = 1)");
$tout = (int) $db->query("SELECT COUNT(*) FROM journal")->fetchColumn();
$r0 = appel(['action' => 'getLogs'], $A + ['n' => 500, 'sansAdmin' => 1]);
verifier($r0['logs'] === [] && ($r0['toutJournal'] ?? false) === true && $r0['nbMasques'] === $tout && $tout > 0, "getLogs sansAdmin, tout masqué : $tout sur tout le journal (" . ($r0['nbMasques'] ?? '?') . ')');
$db->exec("INSERT INTO journal SELECT * FROM journal_sauve");
verifier(appel(['action' => 'logLogin'], ['conseiller' => 'Faux']) === ['ok' => true] && (int) $db->query("SELECT COUNT(*) FROM journal WHERE conseiller = 'Faux'")->fetchColumn() === 0, 'logLogin : ne journalise plus rien');
verifier(appel(['action' => 'logAccesIndex'], ['conseiller' => 'Faux'])['ok'] === false, 'logAccesIndex sans jeton : refusé');
appel(['action' => 'logAccesIndex'], $T + ['conseiller' => 'Conseiller Test', 'userAgent' => 'test']);
$l = $db->query("SELECT conseiller, ref FROM journal WHERE action = 'accesIndex' ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
verifier($l === ['conseiller' => 'Nouveau Venu', 'ref' => 'Conseiller Test'], 'logAccesIndex : personne du jeton, nom choisi en ref (AG-011)');
verifier((int) $db->query("SELECT COUNT(*) FROM journal WHERE action = 'login'")->fetchColumn() >= 4, 'connexions réussies journalisées par checkPassword');

echo "API — tickets (AG-016)\n";
$nbMails = fn() => count(glob("$dossierMails/*.txt") ?: []);
$db->exec("UPDATE comptes SET actif = 1 WHERE conseiller = 'Conseiller Test'");
$db->exec("REPLACE INTO config (cle, valeur) VALUES ('emails', '" . json_encode(['Nouveau Venu' => 'nouveau.venu@example.org', 'Conseiller Test' => ['email' => 'admin@example.org']]) . "')");
$avant = $nbMails();
$tk = ['_id' => 'tk_essai_1', 'type' => 'Bug', 'gene' => 'bloquant', 'titre' => 'Le <b>calendrier</b> plante', 'description' => str_repeat('x', 2500), 'onglet' => 'Calendrier', 'appareil' => 'PC', 'site' => 'nextstep', 'auteur' => 'Usurpateur'];
verifier(appel(['action' => 'creerTicket'], $tk)['auth'] ?? false, 'ticket sans jeton : refusé');
$r = appel(['action' => 'creerTicket'], $T + $tk);
verifier(($r['ok'] ?? false) && $r['nouveau'] === true && $r['ticket']['auteur'] === 'Nouveau Venu' && $r['ticket']['site'] === 'nextstep' && mb_strlen($r['ticket']['description']) === 2000, 'ticket créé : auteur = personne connectée, site validé, description bornée');
verifier($nbMails() === $avant + 1 && $r['mails'] === 1, 'un mail, au seul admin actif qui a une adresse (format objet {email} lu)');
$r2 = appel(['action' => 'creerTicket'], $T + $tk);
verifier(($r2['ok'] ?? false) && $r2['nouveau'] === false && (int) $db->query("SELECT COUNT(*) FROM tickets")->fetchColumn() === 1 && $nbMails() === $avant + 1, 'envoi rejoué (réponse perdue) : ni second ticket ni second mail');
$m = file_get_contents(max(glob("$dossierMails/*.txt")));
verifier(str_contains($m, 'A: admin@example.org') && str_contains($m, 'Le <b>calendrier</b> plante'), 'mail : bon destinataire, titre repris tel quel en texte brut');
verifier((appel(['action' => 'creerTicket'], $T + ['_id' => 'tk_essai_2', 'type' => 'Inconnu', 'titre' => 't', 'description' => 'd'])['ok'] ?? true) === false, 'type de ticket inconnu : refusé');
$l = appel(['action' => 'getTickets'], $T);
verifier(($l['ok'] ?? false) && count($l['tickets']) === 1 && $l['moi'] === 'Nouveau Venu', 'getTickets : tous les tickets, et le nom de la personne connectée');
verifier(str_contains(appel(['action' => 'repondreTicket'], $T + ['_id' => 'tk_essai_1', 'statut' => 'Résolu'])['error'] ?? '', 'administrateurs'), 'répondre : réservé aux administrateurs');
$r = appel(['action' => 'repondreTicket'], $A + ['_id' => 'tk_essai_1', 'statut' => 'En cours', 'reponse' => 'Je regarde.']);
verifier(($r['ok'] ?? false) && $r['ticket']['statut'] === 'En cours' && $r['ticket']['repondu_par'] === 'Conseiller Test' && $r['ticket']['clos_le'] === null, 'réponse : statut, auteur de la réponse, ticket encore ouvert');
verifier((int) $db->query("SELECT COUNT(*) FROM journal WHERE action = 'repondreTicket' AND ref = 'tk_essai_1'")->fetchColumn() === 1, 'réponse journalisée avec le numéro du ticket pour cible, jamais le texte');
appel(['action' => 'creerTicket'], $T + ['_id' => 'tk_essai_3', 'type' => 'Amélioration', 'titre' => 'Même chose', 'description' => 'd']);
$r = appel(['action' => 'repondreTicket'], $A + ['_id' => 'tk_essai_3', 'doublon_de' => 'tk_essai_1']);
verifier(($r['ok'] ?? false) && $r['ticket']['statut'] === 'Non retenu' && $r['ticket']['clos_le'] !== null && $r['ticket']['doublon_de'] === 'tk_essai_1', 'doublon : clos, rattaché à l\'original');
// Mail au demandeur quand l'Admin change le statut ou répond (AG-045, 04/11/2026).
$dernierMail = function () use ($dossierMails) { $f = glob("$dossierMails/*.txt"); sort($f); return $f ? file_get_contents(end($f)) : ''; };
appel(['action' => 'creerTicket'], $T + ['_id' => 'tk_mail_1', 'type' => 'Question', 'titre' => 'Où voir mes ateliers ?', 'description' => 'd', 'site' => 'nextstep']);
$avant = $nbMails();
$r = appel(['action' => 'repondreTicket'], $A + ['_id' => 'tk_mail_1', 'statut' => 'En cours', 'reponse' => "Dans l'onglet Planning.\nBonne journée"]);
$m = $dernierMail();
verifier(($r['mail'] ?? '') === 'envoye' && ($r['mail_a'] ?? '') === 'Nouveau Venu' && $nbMails() === $avant + 1 && str_starts_with($m, 'A: nouveau.venu@example.org')
    && str_contains($m, 'Où voir mes ateliers ?') && str_contains($m, 'En cours') && str_contains($m, "Dans l'onglet Planning.") && str_contains($m, 'Conseiller Test'), 'ticket : le demandeur reçoit un mail (titre, statut, réponse, nom du répondant)');
$r = appel(['action' => 'repondreTicket'], $A + ['_id' => 'tk_mail_1', 'statut' => 'En cours', 'reponse' => "Dans l'onglet Planning.\nBonne journée"]);
verifier(($r['mail'] ?? '') === 'inchange' && ($r['mail_a'] ?? 'x') === '' && $nbMails() === $avant + 1, 'ticket : même validation rejouée, aucun second mail, la raison est dite (rien n\'a changé)');
$r = appel(['action' => 'repondreTicket'], $A + ['_id' => 'tk_mail_1', 'statut' => 'Résolu', 'reponse' => "Dans l'onglet Planning.\nBonne journée"]);
verifier(($r['mail'] ?? '') === 'envoye' && $nbMails() === $avant + 2 && str_contains($dernierMail(), 'Résolu'), 'ticket : changement de statut seul, mail envoyé');
$r = appel(['action' => 'repondreTicket'], $A + ['_id' => 'tk_mail_1', 'statut' => 'Résolu', 'reponse' => '']);
verifier(($r['mail'] ?? '') === 'inchange' && $nbMails() === $avant + 2, 'ticket : réponse effacée sans autre changement, pas de mail (rien de nouveau à dire)');
// L'auteur répond à son propre ticket : pas de mail.
appel(['action' => 'creerTicket'], $A + ['_id' => 'tk_mail_2', 'type' => 'Bug', 'titre' => 'Ticket de l\'Admin', 'description' => 'd']);
$avant2 = $nbMails();
$ra = appel(['action' => 'repondreTicket'], $A + ['_id' => 'tk_mail_2', 'statut' => 'Vu', 'reponse' => 'ok']);
verifier(($ra['mail'] ?? '') === 'auteur' && ($ra['mail_a'] ?? '') === 'Conseiller Test' && $nbMails() === $avant2, 'ticket : l\'auteur qui répond à son propre ticket n\'est pas prévenu, et la raison est dite');
// Sans adresse enregistrée.
$emailsAvant = $db->query("SELECT valeur FROM config WHERE cle = 'emails'")->fetchColumn();
$db->exec("REPLACE INTO config (cle, valeur) VALUES ('emails', '" . json_encode(['Conseiller Test' => ['email' => 'admin@example.org']]) . "')");
$r = appel(['action' => 'repondreTicket'], $A + ['_id' => 'tk_mail_1', 'statut' => 'En cours']);
verifier(($r['mail'] ?? '') === 'sans_adresse' && ($r['mail_a'] ?? '') === 'Nouveau Venu' && $nbMails() === $avant2 && ($r['ticket']['statut'] ?? '') === 'En cours', 'ticket : auteur sans adresse, le dire, mais enregistrer quand même');
$db->prepare("REPLACE INTO config (cle, valeur) VALUES ('emails', ?)")->execute([$emailsAvant]);
// Interrupteur global : éteint, aucun mail ; l'Admin le voit, pas le conseiller ; rallumé, ça repart. Sans lien avec rappels_actifs.
verifier(appel(['action' => 'getTickets'], $A)['mail_demandeur'] === true && !array_key_exists('mail_demandeur', appel(['action' => 'getTickets'], $T)), 'ticket : interrupteur du mail au demandeur allumé par défaut, visible de l\'Admin seulement');
appel(['action' => 'setConfig'], $A + ['key' => 'tickets_mail_demandeur', 'value' => 'false']);
$r = appel(['action' => 'repondreTicket'], $A + ['_id' => 'tk_mail_1', 'statut' => 'Vu']);
verifier(($r['mail'] ?? '') === 'desactive' && $nbMails() === $avant2 && appel(['action' => 'getTickets'], $A)['mail_demandeur'] === false, 'ticket : interrupteur éteint, aucun mail, l\'Admin le voit éteint');
appel(['action' => 'setConfig'], $A + ['key' => 'tickets_mail_demandeur', 'value' => 'true']);
$db->exec("REPLACE INTO config (cle, valeur) VALUES ('rappels_actifs', '" . json_encode(['Nouveau Venu' => false]) . "')");
$r = appel(['action' => 'repondreTicket'], $A + ['_id' => 'tk_mail_1', 'statut' => 'En cours']);
verifier(($r['mail'] ?? '') === 'envoye' && $nbMails() === $avant2 + 1, 'ticket : l\'interrupteur individuel des rappels n\'empêche pas le mail de réponse');
$db->exec("DELETE FROM config WHERE cle = 'rappels_actifs'");
// Quatre validations identiques SIMULTANÉES : un seul mail (le droit d'envoyer suit la mise à jour atomique).
appel(['action' => 'creerTicket'], $T + ['_id' => 'tk_mail_4', 'type' => 'Question', 'titre' => 'Envoi unique', 'description' => 'SECRET-DESC-XYZ', 'site' => 'nextstep']);
$avant4 = $nbMails();
$par = appels_paralleles(array_fill(0, 4, [['action' => 'repondreTicket'], $A + ['_id' => 'tk_mail_4', 'statut' => 'En cours', 'reponse' => 'Pris en compte.']]));
$etats = array_map(fn($r) => $r['mail'] ?? ($r['error'] ?? '?'), $par);
verifier($nbMails() === $avant4 + 1 && count(array_filter($etats, fn($e) => $e === 'envoye')) === 1 && count(array_filter($etats, fn($e) => in_array($e, ['deja', 'inchange'], true))) === 3, 'ticket : quatre validations identiques simultanées, un seul mail, les autres disent pourquoi (« déjà » ou « inchangé » selon l\'instant de leur lecture) (' . implode(',', $etats) . ')');
$m4 = $dernierMail();
verifier(str_contains($m4, 'ateliers-cd47_NextStep/index.html') && !str_contains($m4, 'admin.html') && !str_contains($m4, 'SECRET-DESC-XYZ'), 'ticket : le lien mène à l\'application du site du ticket (pas à l\'Admin), la description d\'origine n\'est pas reprise');
// Relecture de Codex du 04/11/2026 (AG-045), n° 1 : un appel « rien ne change » n'écrase pas une modification
// faite entre sa lecture et son écriture. La ligne est verrouillée par une autre connexion qui la modifie.
appel(['action' => 'creerTicket'], $T + ['_id' => 'tk_mail_5', 'type' => 'Question', 'titre' => 'Écrasement', 'description' => 'd', 'site' => 'nextstep']);
$autreTk = new PDO("mysql:host=$hote;dbname=ateliers_test_api;charset=utf8mb4", $util, $mdp, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$autreTk->beginTransaction();
$autreTk->query("SELECT id FROM tickets WHERE id = 'tk_mail_5' FOR UPDATE")->fetchAll();   // B tient la ligne : A lit l'ancien état, son écriture attend
$p5 = appels_paralleles([[['action' => 'repondreTicket'], $A + ['_id' => 'tk_mail_5', 'statut' => 'Nouveau', 'reponse' => '']]], function () use ($autreTk) {
    $autreTk->exec("UPDATE tickets SET statut = 'En cours', reponse = 'Pris en compte (B).', repondu_par = 'Admin B' WHERE id = 'tk_mail_5'");
    $autreTk->commit();
});
$l5 = $db->query("SELECT statut, reponse FROM tickets WHERE id = 'tk_mail_5'")->fetch(PDO::FETCH_ASSOC);
verifier(($p5[0]['ok'] ?? true) === false && $l5['statut'] === 'En cours' && $l5['reponse'] === 'Pris en compte (B).', 'ticket : un appel sans changement n\'écrase pas une modification concurrente, il signale le conflit (' . json_encode($p5[0]) . ')');
// n° 2 : « texte » → « Texte » par deux Admins en même temps : la base tient les deux pour égaux (collation), un seul mail.
$db->exec("UPDATE tickets SET statut = 'En cours', reponse = 'texte', repondu_par = 'Admin B' WHERE id = 'tk_mail_5'");
$avant5 = $nbMails();
$autreTk->beginTransaction();
$autreTk->query("SELECT id FROM tickets WHERE id = 'tk_mail_5' FOR UPDATE")->fetchAll();
$p5 = appels_paralleles([[['action' => 'repondreTicket'], $A + ['_id' => 'tk_mail_5', 'statut' => 'En cours', 'reponse' => 'Texte']]], function () use ($autreTk) {
    $autreTk->exec("UPDATE tickets SET reponse = 'Texte', repondu_par = 'Admin B' WHERE id = 'tk_mail_5'");   // l'autre Admin gagne le premier
    $autreTk->commit();
});
verifier(($p5[0]['mail'] ?? '') === 'deja' && $nbMails() === $avant5, 'ticket : « texte » → « Texte » déjà fait par un autre Admin, pas de second mail (comparaison binaire) (' . json_encode($p5[0]) . ')');
// n° 3 : changer le statut sans toucher à la réponse d'un autre : le mail l'attribue à celui qui l'a écrite.
$db->exec("UPDATE tickets SET statut = 'En cours', reponse = 'Réponse écrite par Alice.', repondu_par = 'Alice Fictive' WHERE id = 'tk_mail_5'");
$r = appel(['action' => 'repondreTicket'], $A + ['_id' => 'tk_mail_5', 'statut' => 'Résolu', 'reponse' => 'Réponse écrite par Alice.']);
$m5 = $dernierMail();
verifier(($r['mail'] ?? '') === 'envoye' && str_contains($m5, 'Réponse de Alice Fictive') && !str_contains($m5, 'Réponse de Conseiller Test') && ($r['ticket']['repondu_par'] ?? '') === 'Alice Fictive', 'ticket : statut changé par un autre Admin, la réponse reste attribuée à celle qui l\'a écrite');
// Doublon : rattaché, mail qui le dit. Ticket anonymisé : pas de mail.
appel(['action' => 'creerTicket'], $T + ['_id' => 'tk_mail_3', 'type' => 'Question', 'titre' => 'Même question', 'description' => 'd']);
$r = appel(['action' => 'repondreTicket'], $A + ['_id' => 'tk_mail_3', 'doublon_de' => 'tk_mail_1']);
verifier(($r['mail'] ?? '') === 'envoye' && str_contains($dernierMail(), 'rattachée') && str_contains($dernierMail(), 'Non retenu'), 'ticket : doublon, le mail dit qu\'il est rattaché');
$db->exec("UPDATE tickets SET auteur = '—' WHERE id = 'tk_mail_3'");
$avant3 = $nbMails();
verifier((appel(['action' => 'repondreTicket'], $A + ['_id' => 'tk_mail_3', 'statut' => 'Vu'])['mail'] ?? '') === 'anonyme' && $nbMails() === $avant3, 'ticket : auteur anonymisé, pas de mail, la raison est dite');
// Échec d'envoi : l'enregistrement reste.
rename($dossierMails, "$dossierMails.sav");
$r = appel(['action' => 'repondreTicket'], $A + ['_id' => 'tk_mail_1', 'statut' => 'Vu']);
rename("$dossierMails.sav", $dossierMails);
verifier(($r['mail'] ?? '') === 'echec' && ($r['ticket']['statut'] ?? '') === 'Vu' && $db->query("SELECT statut FROM tickets WHERE id = 'tk_mail_1'")->fetchColumn() === 'Vu', 'ticket : mail non parti, statut quand même enregistré, l\'échec est dit');
$db->exec("DELETE FROM tickets WHERE id LIKE 'tk_mail\\_%'");
// Modification par l'équipe tant que le ticket est « Nouveau » (AG-041, 03/11/2026).
$avant = $nbMails();
appel(['action' => 'creerTicket'], $T + ['_id' => 'tk_essai_5', 'type' => 'Bug', 'gene' => 'gênant', 'onglet' => 'Historique', 'titre' => 'Envoi à la Comm', 'description' => 'd', 'appareil' => 'PC', 'site' => 'nextstep']);
$avant = $nbMails();
$mod = ['_id' => 'tk_essai_5', 'type' => 'Amélioration', 'gene' => 'bloquant', 'onglet' => 'Calendrier', 'titre' => 'Envoi mensuel à la Comm', 'description' => str_repeat('y', 2500), 'auteur' => 'Usurpateur', 'site' => 'newgen', 'statut' => 'Résolu'];
verifier(appel(['action' => 'modifierTicket'], $mod)['auth'] ?? false, 'modifier un ticket sans jeton : refusé');
$r = appel(['action' => 'modifierTicket'], $T + $mod);
verifier(($r['ok'] ?? false) && $r['ticket']['type'] === 'Amélioration' && $r['ticket']['gene'] === '' && $r['ticket']['onglet'] === 'Calendrier'
    && $r['ticket']['titre'] === 'Envoi mensuel à la Comm' && mb_strlen($r['ticket']['description']) === 2000, 'modification par l\'auteur : champs saisis remplacés, gêne effacée hors Bug, description bornée');
verifier($r['ticket']['auteur'] === 'Nouveau Venu' && $r['ticket']['site'] === 'nextstep' && $r['ticket']['appareil'] === 'PC' && $r['ticket']['statut'] === 'Nouveau', 'modification : auteur, site, appareil et statut inchangés (champs non modifiables ignorés)');
$r = appel(['action' => 'modifierTicket'], $A + ['_id' => 'tk_essai_5', 'type' => 'Bug', 'gene' => 'mineur', 'onglet' => '', 'titre' => 'Repris par un collègue', 'description' => 'd2']);
verifier(($r['ok'] ?? false) && $r['ticket']['titre'] === 'Repris par un collègue' && $r['ticket']['gene'] === 'mineur' && $r['ticket']['auteur'] === 'Nouveau Venu', 'modification par un autre membre de l\'équipe : acceptée, auteur d\'origine gardé');
verifier((appel(['action' => 'modifierTicket'], $T + ['_id' => 'tk_essai_5', 'type' => 'Inconnu', 'titre' => 't', 'description' => 'd'])['ok'] ?? true) === false
    && (appel(['action' => 'modifierTicket'], $T + ['_id' => 'tk_essai_5', 'type' => 'Bug', 'titre' => ' ', 'description' => 'd'])['ok'] ?? true) === false
    && $db->query("SELECT titre FROM tickets WHERE id = 'tk_essai_5'")->fetchColumn() === 'Repris par un collègue', 'modification : type inconnu ou titre vide refusés, ticket intact');
verifier(($r = appel(['action' => 'modifierTicket'], $T + ['_id' => 'tk_essai_1', 'type' => 'Bug', 'titre' => 'Réécrit', 'description' => 'd']))['ok'] === false && str_contains($r['error'] ?? '', 'pris en charge')
    && $db->query("SELECT titre FROM tickets WHERE id = 'tk_essai_1'")->fetchColumn() === 'Le <b>calendrier</b> plante', 'ticket dont l\'Admin a changé le statut : modification refusée, ticket intact');
verifier((appel(['action' => 'modifierTicket'], $T + ['_id' => 'tk_inexistant', 'type' => 'Bug', 'titre' => 't', 'description' => 'd'])['error'] ?? '') === 'Ticket introuvable', 'modification d\'un ticket inexistant : refusée');
verifier($nbMails() === $avant && (int) $db->query("SELECT COUNT(*) FROM journal WHERE action = 'modifierTicket'")->fetchColumn() === 0, 'modification : ni mail ni journal (décision de l\'utilisateur)');
$db->exec("DELETE FROM tickets WHERE id = 'tk_essai_5'");   // hors des comptages de purge qui suivent (RGPD-19)
appel(['action' => 'creerTicket'], $T + ['_id' => 'tk_essai_4', 'type' => 'Bug', 'titre' => 'Test', 'description' => 'd']);
appel(['action' => 'repondreTicket'], $A + ['_id' => 'tk_essai_3', 'doublon_de' => 'tk_essai_4']);
verifier(str_contains(appel(['action' => 'supprimerTicket'], $T + ['_id' => 'tk_essai_4'])['error'] ?? '', 'administrateurs'), 'supprimer un ticket : réservé aux administrateurs');
$r = appel(['action' => 'supprimerTicket'], $A + ['_id' => 'tk_essai_4']);
verifier(($r['ok'] ?? false) && (int) $db->query("SELECT COUNT(*) FROM tickets WHERE id = 'tk_essai_4'")->fetchColumn() === 0
    && $db->query("SELECT doublon_de FROM tickets WHERE id = 'tk_essai_3'")->fetchColumn() === ''
    && (int) $db->query("SELECT COUNT(*) FROM journal WHERE action = 'supprimerTicket' AND ref = 'tk_essai_4'")->fetchColumn() === 1, 'suppression : ticket effacé, renvoi « doublon » vidé, journalisée');
verifier(isset(appel(['action' => 'getAll'], $A)['tickets']['nouveaux']), 'getAll : résumé des tickets pour les pastilles');
$db->exec("UPDATE tickets SET statut = 'Résolu', reponse = 'Fait.', repondu_par = 'Conseiller Test', clos_le = '" . date('Y-m-d H:i:s', strtotime('-13 months')) . "' WHERE id = 'tk_essai_1'");
appel(['action' => 'checkPassword'], ['conseiller' => 'Conseiller Test', 'password' => 'secret-test']);
$t1 = $db->query("SELECT auteur, repondu_par, description FROM tickets WHERE id = 'tk_essai_1'")->fetch(PDO::FETCH_ASSOC);
verifier($t1 && $t1['auteur'] === '—' && $t1['repondu_par'] === '' && $t1['description'] !== '', '[RGPD-19] ticket clos depuis 12 mois : anonymisé (auteur, répondant), gardé en archive');
$db->exec("UPDATE tickets SET clos_le = '" . date('Y-m-d H:i:s', strtotime('-37 months')) . "' WHERE id = 'tk_essai_3'");
$db->exec("UPDATE tickets SET clos_le = NULL, cree_le = '" . date('Y-m-d H:i:s', strtotime('-25 months')) . "' WHERE id = 'tk_essai_1'");
appel(['action' => 'checkPassword'], ['conseiller' => 'Conseiller Test', 'password' => 'secret-test']);
verifier((int) $db->query("SELECT COUNT(*) FROM tickets")->fetchColumn() === 0, '[RGPD-19] tickets supprimés : clos depuis 36 mois, ou jamais clos depuis 24 mois');
array_map('unlink', glob("$dossierMails/*.txt"));

echo "API — durée remplie pour l'utilisateur (05/10/2026)\n";
$db->exec("DELETE FROM config WHERE cle = 'migration_duree_utilisateur'");
$db->exec("INSERT INTO ateliers (id, statut, date, horaire, duree, orienteur, lieu, thematique, conseiller, co_animateur, residence, remarques) VALUES
  ('mig_1', 'Réalisé', '2026-09-01', '09:00', NULL, '', '', 'T', 'Michel Aswad', '', '', ''),
  ('mig_2', 'Réalisé', '2026-09-01', '09:00', 60, '', '', 'T', 'Michel Aswad', '', '', ''),
  ('mig_3', 'Réalisé', '2026-09-01', '09:00', NULL, '', '', 'T', 'Autre Conseiller', '', '', '')");
appel(['action' => 'getAll'], $T);
$d = $db->query("SELECT id, duree FROM ateliers WHERE id LIKE 'mig\\_%' ORDER BY id")->fetchAll(PDO::FETCH_KEY_PAIR);
verifier($d === ['mig_1' => 90, 'mig_2' => 60, 'mig_3' => null] && str_contains((string) $db->query("SELECT valeur FROM config WHERE cle = 'migration_duree_utilisateur'")->fetchColumn(), '1 atelier'),
    'durée vide remplie à 1 h 30 pour les seuls ateliers de l\'utilisateur, une fois — ' . json_encode($d));
$db->exec("UPDATE ateliers SET duree = NULL WHERE id = 'mig_1'");
appel(['action' => 'getAll'], $T);
verifier($db->query("SELECT duree FROM ateliers WHERE id = 'mig_1'")->fetchColumn() === null, 'remplissage fait une seule fois (repère dans config)');
$db->exec("DELETE FROM ateliers WHERE id LIKE 'mig\\_%'");

echo "API — avis des stagiaires (AG-021)\n";
$auj = date('Y-m-d');
appel(['action' => 'saveEntry'], $T + ['entry' => json_encode(['_id' => 'av_1', 'date' => $auj, 'horaire' => '10:00', 'thematique' => 'Smartphone', 'conseiller' => 'Nouveau Venu', 'commune' => 'Nérac'])]);
appel(['action' => 'saveEntry'], $T + ['entry' => json_encode(['_id' => 'av_vieux', 'date' => date('Y-m-d', strtotime('-40 days')), 'thematique' => 'X', 'conseiller' => 'Nouveau Venu'])]);
verifier(appel(['action' => 'jetonAvis'], ['_id' => 'av_1'])['auth'] ?? false, 'jeton d\'avis : réservé à l\'équipe connectée');
$j = appel(['action' => 'jetonAvis'], $T + ['_id' => 'av_1']);
$j2 = appel(['action' => 'jetonAvis'], $T + ['_id' => 'av_1']);
verifier(($j['ok'] ?? false) && preg_match('/^[0-9a-f]{32}$/', $j['jeton']) && $j2['jeton'] === $j['jeton'], 'jeton aléatoire, le même à chaque demande');
$pub = appel(['action' => 'avisPublic'], ['a' => $j['jeton']]);
verifier(($pub['ok'] ?? false) && $pub['atelier'] === ['date' => $auj, 'thematique' => 'Smartphone'], 'page publique : date et thème seulement (ni animateur, ni lieu)');
$ok = appel(['action' => 'deposerAvis'], ['a' => $j['jeton'], 'attentes' => '5', 'rythme' => 'Adapté', 'clarte' => '4', 'aise' => 'Oui', 'autonomie' => 'Avec de l\'aide', 'sujet' => 'Autre', 'sujet_autre' => 'Tablette', 'remarque' => 'Merci']);
verifier(($ok['ok'] ?? false) === true, 'avis déposé sans connexion');
verifier(!(appel(['action' => 'deposerAvis'], ['a' => $j['jeton'], 'attentes' => '9'])['ok'] ?? true)
    && !(appel(['action' => 'deposerAvis'], ['a' => $j['jeton'], 'rythme' => 'Bof'])['ok'] ?? true)
    && !(appel(['action' => 'deposerAvis'], ['a' => $j['jeton']])['ok'] ?? true), 'réponse hors liste ou avis vide : refusés');
verifier(!(appel(['action' => 'deposerAvis'], ['a' => str_repeat('0', 32), 'attentes' => '5'])['ok'] ?? true), 'jeton inconnu : refusé');
// AG-035 (27/10/2026) : sujets à choix multiples, tableau JSON ; valeur seule
// (ancienne page en cache) toujours acceptée ; toujours relus en tableau.
$db->exec('ALTER TABLE avis MODIFY sujet VARCHAR(40) NULL');   // base d'avant le 27/10/2026
appel(['action' => 'saveEntry'], $T + ['entry' => json_encode(['_id' => 'av_multi', 'date' => $auj, 'thematique' => 'X', 'conseiller' => 'Nouveau Venu'])]);
$jm = appel(['action' => 'jetonAvis'], $T + ['_id' => 'av_multi'])['jeton'];
$m1 = appel(['action' => 'deposerAvis'], ['a' => $jm, 'sujet' => json_encode(['Sécurité', 'Smartphone', 'Sécurité', 'Autre']), 'sujet_autre' => 'Tablette']);
$m2 = appel(['action' => 'deposerAvis'], ['a' => $jm, 'sujet' => json_encode(['Smartphone', 'Bof'])]);
$m3 = appel(['action' => 'deposerAvis'], ['a' => $jm, 'sujet' => 'Messagerie', 'sujet_autre' => 'ignoré']);
$lus = appel(['action' => 'avisAtelier'], $A + ['_id' => 'av_multi'])['avis'] ?? [];
verifier(($m1['ok'] ?? false) && !($m2['ok'] ?? true) && ($m3['ok'] ?? false)
    && array_column($lus, 'sujet') === [['Smartphone', 'Sécurité', 'Autre'], ['Messagerie']] && array_column($lus, 'sujet_autre') === ['Tablette', null],
    'sujets : plusieurs choix, dans l\'ordre de la liste, sans doublon ; valeur hors liste refusée ; ancienne valeur seule acceptée — ' . json_encode($lus));
verifier(stripos($db->query("SHOW COLUMNS FROM avis LIKE 'sujet'")->fetch(PDO::FETCH_ASSOC)['Type'], 'varchar(255)') === 0, 'sujets : colonne élargie à 255 sur une base existante');
$db->exec("INSERT INTO avis (atelier_id, cree_le, sujet, source) VALUES ('av_multi', CURDATE(), 'Sécurité', 'qr')");   // avis d'avant le 27/10/2026
$tous = appel(['action' => 'avisAtelier'], $A + ['_id' => 'av_multi'])['avis'];
verifier(end($tous)['sujet'] === ['Sécurité'], 'sujets : ancienne valeur seule en base, relue en tableau');
$db->exec("DELETE FROM avis WHERE atelier_id = 'av_multi'");
$db->exec("DELETE FROM ateliers WHERE id = 'av_multi'");
$jv = appel(['action' => 'jetonAvis'], $T + ['_id' => 'av_vieux']);
$f = appel(['action' => 'avisPublic'], ['a' => $jv['jeton']]);
verifier(!($f['ok'] ?? true) && str_contains($f['error'] ?? '', 'fermé depuis le'), 'atelier passé depuis plus de 30 jours : questionnaire fermé, avec la date');
appel(['action' => 'saveEntry'], $T + ['entry' => json_encode(['_id' => 'av_futur', 'date' => date('Y-m-d', strtotime('+10 days')), 'thematique' => 'X', 'conseiller' => 'Nouveau Venu'])]);
$jf = appel(['action' => 'jetonAvis'], $T + ['_id' => 'av_futur']);
$f = appel(['action' => 'avisPublic'], ['a' => $jf['jeton']]);
verifier(!($f['ok'] ?? true) && str_contains($f['error'] ?? '', 'ouvrira le ' . date('d/m/Y', strtotime('+10 days'))) && $jf['ouvert_du'] === date('Y-m-d', strtotime('+10 days')), 'atelier à venir : questionnaire ouvert le jour de l\'atelier seulement, dates données à la fenêtre du QR');
$col = $db->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'avis' AND column_name IN ('ip', 'adresse_ip', 'nom', 'prenom', 'age', 'tranche_age', 'contact', 'recontact', 'email', 'telephone')")->fetchColumn();
verifier((int) $col === 0, '[RGPD-20] avis anonymes : aucune colonne IP, nom, âge ou contact');
$res = appel(['action' => 'jetonAvis'], $A + ['_id' => 'av_1'])['avis'];
verifier($res['n'] === 1 && $res['attentes'] == 5 && $res['remarques'] === ['Merci'], 'résumé des avis pour la fiche (Admin) : ' . json_encode($res));
$res = appel(['action' => 'jetonAvis'], $T + ['_id' => 'av_1'])['avis'];
verifier($res['n'] === 1 && ($res['masque'] ?? false) && $res['attentes'] === null && $res['remarques'] === [], 'moins de 3 avis : le conseiller ne voit que leur nombre (écart n° 10)');
verifier($db->query("SELECT cree_le FROM avis LIMIT 1")->fetchColumn() === $auj . ' 00:00:00', 'avis daté du jour seulement, sans l\'heure (amendement B)');
// Amendement D : avis papier, par un conseiller connecté, sans fenêtre de dates.
verifier(appel(['action' => 'saisirAvisPapier'], ['_id' => 'av_vieux', 'attentes' => '3'])['auth'] ?? false, 'avis papier : réservé à l\'équipe connectée');
$pap = appel(['action' => 'saisirAvisPapier'], $T + ['_id' => 'av_vieux', 'attentes' => '3', 'remarque' => '']);
verifier(($pap['ok'] ?? false) && $db->query("SELECT source FROM avis WHERE atelier_id = 'av_vieux'")->fetchColumn() === 'papier'
    && (int) $db->query("SELECT COUNT(*) FROM journal WHERE action = 'saisirAvisPapier' AND ref = 'av_vieux'")->fetchColumn() === 1, 'avis papier enregistré hors fenêtre, marqué « papier », journalisé');
// Bilan trimestriel : avis des seuls ateliers « Réalisé » de la période.
$debut = date('Y-m-d', strtotime('-60 days'));
verifier(appel(['action' => 'bilanAvis'], ['format' => '2', 'du' => $debut, 'au' => $auj])['auth'] ?? false, 'bilan des avis : réservé à l\'équipe connectée');
verifier(!(appel(['action' => 'bilanAvis'], $T + ['format' => '2', 'du' => $auj, 'au' => $debut])['ok'] ?? true), 'bilan des avis : période à l\'envers refusée');
$vieuxLecteur = appel(['action' => 'bilanAvis'], $A + ['du' => $debut, 'au' => $auj]);
verifier(!($vieuxLecteur['ok'] ?? true) && str_contains($vieuxLecteur['error'], 'rechargez'), 'bilan des avis : page d\'avant AG-035 (sans format) invitée à recharger, pas de chiffres faux');
$db->exec("UPDATE ateliers SET statut = 'Réalisé' WHERE id = 'av_vieux'");
$b = appel(['action' => 'bilanAvis'], $A + ['format' => '2', 'du' => $debut, 'au' => $auj]);
verifier(($b['ok'] ?? false) && count($b['avis']) === 1 && $b['avis'][0]['atelier_id'] === 'av_vieux' && $b['avis'][0]['attentes'] === 3 && $b['remarques'] === [],
    'bilan des avis : atelier non réalisé écarté — ' . json_encode($b));
$db->exec("UPDATE ateliers SET statut = 'Réalisé' WHERE id = 'av_1'");
$bT = appel(['action' => 'bilanAvis'], $T + ['format' => '2', 'du' => $debut, 'au' => $auj]);
verifier(($bT['masque'] ?? false) && $bT['avis'] === [] && $bT['remarques'] === [], 'bilan de moins de 3 avis : rien de lisible pour le conseiller');
$b = appel(['action' => 'bilanAvis'], $A + ['format' => '2', 'du' => $debut, 'au' => $auj]);
verifier(count($b['avis'] ?? []) === 2 && $b['remarques'] === ['Merci'] && !array_key_exists('remarque', $b['avis'][0]) && !array_key_exists('cree_le', $b['avis'][0]),
    'bilan des avis : remarques à part, sans date ni lien avec l\'atelier');
verifier(($b['remarques_ateliers'] ?? null) === [['atelier_id' => 'av_1', 'remarque' => 'Merci']] && !array_key_exists('remarques_ateliers', $bT),
    'bilan des avis : lien remarque-atelier pour l\'Admin seul (filtre par conseiller) — ' . json_encode($b['remarques_ateliers'] ?? null));
// Récapitulatif par atelier : tous statuts, remarques rattachées à l'atelier.
$db->exec("UPDATE ateliers SET statut = 'Planifié' WHERE id = 'av_1'");
verifier(appel(['action' => 'avisParAtelier'], ['du' => $debut, 'au' => $auj])['auth'] ?? false, 'récapitulatif des avis : réservé à l\'équipe connectée');
$rp = appel(['action' => 'avisParAtelier'], $A + ['du' => $debut, 'au' => $auj]);
$par = array_column($rp['ateliers'] ?? [], null, 'atelier_id');
verifier(count($par) === 2 && $par['av_1']['n'] === 1 && $par['av_1']['attentes'] == 5 && $par['av_1']['aise_oui'] === 1 && $par['av_1']['remarques'] === ['Merci']
    && $par['av_vieux']['papier'] === 1 && $par['av_vieux']['remarques'] === [], 'récapitulatif des avis par atelier, tous statuts — ' . json_encode($rp));
verifier(($par['av_1']['detail']['rythme'] ?? null) === ['Trop lent' => 0, 'Adapté' => 1, 'Trop rapide' => 0]
    && $par['av_1']['detail']['autonomie']['Avec de l\'aide'] === 1 && array_sum($par['av_vieux']['detail']['aise']) === 0,
    'récapitulatif par atelier : détail de chaque réponse à choix, non-réponses écartées');
// Avis réservés à l'animateur et au co-animateur (04/10/2026) ; Admin : tout.
appel(['action' => 'saveEntry'], $T + ['entry' => json_encode(['_id' => 'av_autre', 'date' => $auj, 'thematique' => 'X', 'conseiller' => 'Conseiller Test'])]);
$ja = appel(['action' => 'jetonAvis'], $A + ['_id' => 'av_autre']);
appel(['action' => 'deposerAvis'], ['a' => $ja['jeton'], 'attentes' => '2', 'remarque' => 'Autre animateur']);
$refus = appel(['action' => 'jetonAvis'], $T + ['_id' => 'av_autre']);
verifier(!($refus['ok'] ?? true) && str_contains($refus['error'] ?? '', 'réservés à son animateur')
    && !(appel(['action' => 'saisirAvisPapier'], $T + ['_id' => 'av_autre', 'attentes' => '3'])['ok'] ?? true), 'avis de l\'atelier d\'un collègue : QR et saisie papier refusés');
$idsT = array_column(appel(['action' => 'avisParAtelier'], $T + ['du' => $debut, 'au' => $auj])['ateliers'] ?? [], 'atelier_id');
$idsA = array_column(appel(['action' => 'avisParAtelier'], $A + ['du' => $debut, 'au' => $auj])['ateliers'] ?? [], 'atelier_id');
$idsAmoi = array_column(appel(['action' => 'avisParAtelier'], $A + ['du' => $debut, 'au' => $auj, 'moi' => '1'])['ateliers'] ?? [], 'atelier_id');
verifier(in_array('av_autre', $idsAmoi, true) && !in_array('av_1', $idsAmoi, true), 'Admin, « Mes bilans » (moi=1) : ses seuls ateliers');
$db->exec("UPDATE ateliers SET statut = 'Réalisé' WHERE id = 'av_autre'");
$remT = appel(['action' => 'bilanAvis'], $T + ['format' => '2', 'du' => $debut, 'au' => $auj])['remarques'] ?? null;
verifier(!in_array('av_autre', $idsT, true) && in_array('av_autre', $idsA, true) && is_array($remT) && !in_array('Autre animateur', $remT, true),
    'récapitulatif et bilan : le conseiller ne voit que ses ateliers, l\'Admin voit tout');
$db->exec("UPDATE ateliers SET co_animateur = 'Nouveau Venu' WHERE id = 'av_autre'");
verifier((appel(['action' => 'jetonAvis'], $T + ['_id' => 'av_autre'])['ok'] ?? false) === true, 'co-animateur : avis de l\'atelier accessibles');
appel(['action' => 'deposerAvis'], ['a' => $ja['jeton'], 'attentes' => '4']);
appel(['action' => 'deposerAvis'], ['a' => $ja['jeton'], 'attentes' => '3']);
$r3 = appel(['action' => 'jetonAvis'], $T + ['_id' => 'av_autre'])['avis'] ?? [];
verifier($r3['n'] === 3 && !isset($r3['masque']) && $r3['attentes'] == 3 && in_array('Autre animateur', $r3['remarques'], true), 'à partir de 3 avis : résumé et remarques visibles du conseiller');
$db->exec("DELETE FROM ateliers WHERE id = 'av_autre'");
// Suppression d'un avis isolé : Admin seulement, journalisée sans le contenu.
$liste = appel(['action' => 'avisAtelier'], $A + ['_id' => 'av_1'])['avis'] ?? [];
verifier(count($liste) === 1 && $liste[0]['remarque'] === 'Merci' && $liste[0]['cree_le'] === $auj, 'Admin : avis d\'un atelier un par un');
verifier(!(appel(['action' => 'avisAtelier'], $T + ['_id' => 'av_1'])['ok'] ?? true)
    && !(appel(['action' => 'supprimerAvis'], $T + ['_id' => (string) $liste[0]['id']])['ok'] ?? true), 'avis un par un et suppression : refusés à un conseiller');
appel(['action' => 'saisirAvisPapier'], $T + ['_id' => 'av_1', 'attentes' => '1', 'remarque' => 'Cite quelqu\'un']);
$liste = appel(['action' => 'avisAtelier'], $A + ['_id' => 'av_1'])['avis'];
$cible = end($liste)['id'];
verifier((appel(['action' => 'supprimerAvis'], $A + ['_id' => (string) $cible])['ok'] ?? false)
    && count(appel(['action' => 'avisAtelier'], $A + ['_id' => 'av_1'])['avis']) === 1
    && $db->query("SELECT ref FROM journal WHERE action = 'supprimerAvis' ORDER BY id DESC LIMIT 1")->fetchColumn() === (string) $cible
    && (int) $db->query("SELECT COUNT(*) FROM journal WHERE action = 'avisAtelier'")->fetchColumn() === 0, 'Admin : avis supprimé, journalisé avec son seul numéro (lecture non journalisée)');
// Plafond : un avis par présent (à défaut par inscrit), avis papier compris.
appel(['action' => 'saveEntry'], $T + ['entry' => json_encode(['_id' => 'av_plaf', 'date' => $auj, 'thematique' => 'X', 'conseiller' => 'Nouveau Venu', 'inscrits' => 5, 'presents' => 2])]);
$jp = appel(['action' => 'jetonAvis'], $T + ['_id' => 'av_plaf'])['jeton'];
$r1 = appel(['action' => 'deposerAvis'], ['a' => $jp, 'attentes' => '4']);
$r2 = appel(['action' => 'saisirAvisPapier'], $T + ['_id' => 'av_plaf', 'attentes' => '3']);
$r3 = appel(['action' => 'deposerAvis'], ['a' => $jp, 'attentes' => '5']);
verifier(($r1['ok'] ?? false) && ($r2['ok'] ?? false) && !($r3['ok'] ?? true) && str_contains($r3['error'] ?? '', 'un par participant'), 'plafond : autant d\'avis que de présents, avis papier compris');
$db->exec("UPDATE ateliers SET presents = NULL WHERE id = 'av_plaf'");
verifier((appel(['action' => 'deposerAvis'], ['a' => $jp, 'attentes' => '5'])['ok'] ?? false) === true, 'plafond : présents non saisis, un avis par inscrit');
$db->exec("DELETE FROM avis WHERE atelier_id = 'av_plaf'");
$db->exec("DELETE FROM ateliers WHERE id = 'av_plaf'");
// Amendement A : corbeille → avis et jeton gardés ; restauration → rendus ; sortie de corbeille → effacés.
appel(['action' => 'delete'], $T + ['_id' => 'av_1']);
appel(['action' => 'checkPassword'], ['conseiller' => 'Conseiller Test', 'password' => 'secret-test']);
appel(['action' => 'restaurerCorbeille'], $A + ['_id' => 'av_1']);
$apres = appel(['action' => 'jetonAvis'], $T + ['_id' => 'av_1']);
verifier(($apres['jeton'] ?? '') === $j['jeton'] && $apres['avis']['n'] === 1, 'atelier supprimé puis restauré : même jeton, avis gardés');
appel(['action' => 'delete'], $T + ['_id' => 'av_1']);
$db->exec("UPDATE ateliers_corbeille SET supprime_le = NOW() - INTERVAL 40 DAY WHERE id = 'av_1'");
appel(['action' => 'checkPassword'], ['conseiller' => 'Conseiller Test', 'password' => 'secret-test']);
verifier((int) $db->query("SELECT COUNT(*) FROM avis WHERE atelier_id = 'av_1'")->fetchColumn() === 0
    && (int) $db->query("SELECT COUNT(*) FROM avis_jetons WHERE atelier_id = 'av_1'")->fetchColumn() === 0, 'atelier sorti de la corbeille : ses avis et son jeton partent avec lui');
$db->exec("UPDATE avis SET cree_le = '" . date('Y-m-d H:i:s', strtotime('-25 months')) . "'");
appel(['action' => 'checkPassword'], ['conseiller' => 'Conseiller Test', 'password' => 'secret-test']);
verifier((int) $db->query('SELECT COUNT(*) FROM avis')->fetchColumn() === 0, '[RGPD-20] avis purgés à 24 mois');

echo "API — mot de passe oublié\n";
$mails = function () use ($dossierMails) { $f = glob("$dossierMails/*.txt"); sort($f); return array_map('file_get_contents', $f); };
$RETOUR = 'https://maswaddpt47-cmyk.github.io/ATELIERS_NEWGEN/index.html?backend=php';
verifier(appel(['action' => 'demanderReinit'], ['conseiller' => 'Nouveau Venu', 'retour' => 'https://pirate.example/'])['ok'] === false && $mails() === [], '[RGPD-11] lien vers un autre site : refusé, aucun mail');
$r1 = appel(['action' => 'demanderReinit'], ['conseiller' => 'Nouveau Venu', 'retour' => $RETOUR]);
$r2 = appel(['action' => 'demanderReinit'], ['conseiller' => 'Conseiller Test', 'retour' => $RETOUR]);   // pas d'adresse
$r3 = appel(['action' => 'demanderReinit'], ['conseiller' => 'Personne Inconnue', 'retour' => $RETOUR]);
verifier($r1 === $r2 && $r2 === $r3 && $r1['ok'] === true, '[RGPD-10] réponse identique (adresse, sans adresse, inconnu) : rien ne se devine');
$m = $mails();
verifier(count($m) === 1 && str_contains($m[0], 'A: nouveau.venu@example.org'), 'un seul mail, à la bonne adresse');
preg_match('/#reinit=([0-9a-f]{64})/', $m[0] ?? '', $mm);
$jetonReinit = $mm[1] ?? '';
verifier($jetonReinit !== '' && str_contains($m[0], $RETOUR . '#reinit='), 'lien vers la page de départ, ?backend=php conservé, jeton après le # (hors journaux de GitHub Pages)');
verifier((int) $db->query("SELECT COUNT(*) FROM reinitialisations WHERE jeton_hash = '$jetonReinit'")->fetchColumn() === 0, '[RGPD-06] jeton jamais stocké en clair');
$avant = appel(['action' => 'checkPassword'], ['conseiller' => 'Nouveau Venu', 'password' => 'Un-Autre-Mdp-99', 'source' => 'index.html']);
verifier(appel(['action' => 'reinitMotDePasse'], ['jeton' => $jetonReinit, 'password' => 'court'])['error'] === API_MDP_POLITIQUE, 'mot de passe trop faible refusé, lien pas consommé');
verifier(appel(['action' => 'reinitMotDePasse', 'jeton' => $jetonReinit, 'password' => 'Nouveau-Mdp-2026!'])['ok'] === false, 'jeton et mot de passe dans l\'URL : ignorés');
$r = appel(['action' => 'reinitMotDePasse'], ['jeton' => $jetonReinit, 'password' => 'Nouveau-Mdp-2026!']);
verifier(($r['ok'] ?? false) === true && $r['conseiller'] === 'Nouveau Venu', 'réinitialisation réussie');
verifier(appel(['action' => 'checkPassword'], ['conseiller' => 'Nouveau Venu', 'password' => 'Nouveau-Mdp-2026!', 'source' => 'index.html'])['ok'] === true, 'connexion avec le nouveau mot de passe');
verifier(str_contains(appel(['action' => 'reinitMotDePasse'], ['jeton' => $jetonReinit, 'password' => 'Encore-Un-Mdp-3!'])['error'] ?? '', 'invalide ou expiré'), 'lien à usage unique');
appel(['action' => 'demanderReinit'], ['conseiller' => 'Nouveau Venu', 'retour' => $RETOUR]);
appel(['action' => 'demanderReinit'], ['conseiller' => 'Nouveau Venu', 'retour' => $RETOUR]);
appel(['action' => 'demanderReinit'], ['conseiller' => 'Nouveau Venu', 'retour' => $RETOUR]);
verifier(count($mails()) === 3, '3 demandes par heure au plus (la 4e n\'envoie rien)');
$db->exec("UPDATE reinitialisations SET cree = NOW() - INTERVAL 2 HOUR, expire = NOW() - INTERVAL 1 MINUTE");
appel(['action' => 'demanderReinit'], ['conseiller' => 'Nouveau Venu', 'retour' => $RETOUR]);
$vieux = $db->query("SELECT COUNT(*) FROM reinitialisations WHERE expire < NOW()")->fetchColumn();
verifier(count($mails()) === 4 && (int) $vieux >= 1, 'après une heure, nouvelle demande possible');
// Admin (06/10/2026) : lien envoyé à un collègue, réponse explicite.
$nv = appel(['action' => 'checkPassword'], ['conseiller' => 'Nouveau Venu', 'password' => 'Nouveau-Mdp-2026!', 'source' => 'index.html']);
verifier(str_contains(appel(['action' => 'envoyerLienReinit'], ['token' => $nv['token'], 'conseiller' => 'Nouveau Venu', 'retour' => $RETOUR])['error'] ?? '', 'administrateurs'), 'envoi du lien par l\'Admin : refusé à un conseiller');
$e = appel(['action' => 'envoyerLienReinit'], $A + ['conseiller' => 'Nouveau Venu', 'retour' => $RETOUR]);
$m = $mails();
verifier(($e['ok'] ?? false) && count($m) === 5 && str_contains(end($m), 'A: nouveau.venu@example.org') && str_contains(end($m), '#reinit=') && str_contains(end($m), "L'administrateur"),
    'envoi du lien par l\'Admin : mail parti, lien après le #');
$sans = appel(['action' => 'envoyerLienReinit'], $A + ['conseiller' => 'Conseiller Test', 'retour' => $RETOUR]);
verifier(!($sans['ok'] ?? true) && str_contains($sans['error'] ?? '', 'Aucune adresse') && count($mails()) === 5, 'envoi du lien par l\'Admin : sans adresse, le dire (provisoire en secours)');

// Appels réellement simultanés (serveur à plusieurs processus) : chaque élément [get, post].
function appels_paralleles(array $reqs, ?callable $pendant = null): array
{
    global $port;
    $mh = curl_multi_init(); $hs = [];
    foreach ($reqs as $i => [$get, $post]) {
        $ch = curl_init("http://127.0.0.1:$port/index.php?" . http_build_query($get));
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query($post), CURLOPT_TIMEOUT => 20]);
        curl_multi_add_handle($mh, $ch); $hs[$i] = $ch;
    }
    $fait = false;
    do {
        curl_multi_exec($mh, $run); curl_multi_select($mh, 0.05);
        // Laisse les requêtes atteindre la base (curl continue d'émettre pendant l'attente : une simple pause
        // retenait l'envoi, et la requête n'arrivait qu'après l'action « concurrente »), puis agit.
        if ($pendant && !$fait) { $fin = microtime(true) + 0.6; while (microtime(true) < $fin) { curl_multi_exec($mh, $run); curl_multi_select($mh, 0.05); } $pendant(); $fait = true; }
    } while ($run > 0);
    $res = [];
    foreach ($hs as $i => $ch) { $res[$i] = json_decode((string) curl_multi_getcontent($ch), true); curl_multi_remove_handle($mh, $ch); }
    return $res;
}

// Connexion TS complète (AG-043 : 2FA) : mot de passe, puis code lu dans le dernier mail.
// Le délai d'une minute entre deux défis est levé pour pouvoir enchaîner les essais.
function ts_connexion(string $id, string $mdp, ?array &$etape1 = null): array
{
    global $db, $dossierMails;
    $norm = mb_strtolower(preg_replace('/\s+/', '', $id));
    $db->prepare('UPDATE comptes_ts SET code_expire = NULL WHERE email = ?')->execute([$norm]);
    $db->prepare("DELETE FROM envois_ts WHERE type = 'code' AND email = ?")->execute([$norm]);
    $etape1 = appel(['action' => 'tsConnexion'], ['identifiant' => $id, 'password' => $mdp]);
    if (!($etape1['etape2'] ?? false)) return $etape1;
    $f = glob("$dossierMails/*.txt"); sort($f);
    preg_match('/Code de connexion : (\d{6})/', file_get_contents(end($f)), $m);
    return appel(['action' => 'tsCode'], ['defi' => $etape1['defi'], 'code' => $m[1] ?? '']);
}

// ── Page des travailleurs sociaux (AG-042, 04/11/2026) ────────────────────
echo "\nAPI — page des travailleurs sociaux\n";
array_map('unlink', glob("$dossierMails/*.txt"));
$db->exec("UPDATE config SET valeur = '" . json_encode(['Nouveau Venu' => 'nouveau.venu@example.org', 'Conseiller Test' => 'admin.test@example.org']) . "' WHERE cle = 'emails'");
$db->exec("INSERT INTO ateliers (id, statut, date, horaire, orienteur, commune, lieu, thematique, public, conseiller, co_animateur, residence, remarques, inscrits)
    VALUES ('ts_futur', 'Planifié', CURDATE() + INTERVAL 3 DAY, '14:00', 'CCAS Agen', 'Agen', 'MDS', 'Mail', 'Seniors', 'Conseiller Test', '', '', 'remarque secrète', 7),
           ('ts_jour', 'Planifié', CURDATE(), NULL, 'Orienteur', 'Nérac', 'Mairie', 'Smartphone', 'Tous publics', 'Conseiller Test', '', '', '', NULL),
           ('ts_passe', 'Planifié', CURDATE() - INTERVAL 1 DAY, NULL, 'O', 'Agen', 'L', 'T', 'P', 'Conseiller Test', '', '', '', NULL),
           ('ts_realise', 'Réalisé', CURDATE() + INTERVAL 5 DAY, NULL, 'O', 'Agen', 'L', 'T', 'P', 'Conseiller Test', '', '', '', NULL)");
verifier(appel(['action' => 'tsDemandeAcces'], ['email' => 'pas-une-adresse'])['ok'] === false, 'demande : adresse invalide refusée');
// AG-043 : seules les adresses @lotetgaronne.fr sont acceptées (domaine exact).
foreach (['agent@example.org', 'agent@sub.lotetgaronne.fr', 'agent@lotetgaronne.fr.autre.com', 'agent@lotetgaronne.org'] as $mauvaise) {
    $r = appel(['action' => 'tsDemandeAcces'], ['email' => $mauvaise]);
    verifier(($r['ok'] ?? true) === false && str_contains($r['error'] ?? '', '@lotetgaronne.fr') && !$db->query('SELECT COUNT(*) FROM comptes_ts WHERE email = ' . $db->quote($mauvaise))->fetchColumn(), "demande : domaine refusé ($mauvaise)");
}
verifier(appel(['action' => 'tsDemandeAcces'], ['email' => 'robot@lotetgaronne.fr', 'url' => 'http://spam'])['ok'] === true
    && !$db->query("SELECT COUNT(*) FROM comptes_ts WHERE email = 'robot@lotetgaronne.fr'")->fetchColumn() && count($mails()) === 0, 'demande : champ piège rempli, rien enregistré, aucun mail');
$d1 = appel(['action' => 'tsDemandeAcces', 'site' => 'nextstep'], ['email' => ' TS.Un@Lotetgaronne.fr ']);
verifier($d1 === ['ok' => true]
    && $db->query("SELECT doit_changer FROM comptes_ts WHERE email = 'ts.un@lotetgaronne.fr'")->fetchColumn() == 1
    && $db->query("SELECT COUNT(*) FROM envois_ts WHERE type = 'creation' AND email = 'ts.un@lotetgaronne.fr'")->fetchColumn() == 1, 'demande : compte créé en minuscules (provisoire à changer), un envoi inscrit au registre, réponse sans mot de passe');
$m = $mails();
verifier(count($m) === 1 && str_starts_with($m[0], 'A: ts.un@lotetgaronne.fr') && preg_match('/Mot de passe provisoire : (\S{12})\n/', $m[0], $mm) === 1 && str_contains($m[0], 'ateliers-cd47_NextStep/ts.html'), 'demande : un seul mail, au demandeur, avec le mot de passe provisoire (aucun mail aux admins)');
$provisoire = $mm[1] ?? '';
verifier(appel(['action' => 'tsDemandeAcces'], ['email' => 'ts.un@lotetgaronne.fr']) === $d1 && count($mails()) === 1, 'demande en double : même réponse, aucun second mail, mot de passe inchangé');
// Quota : chaque ENVOI (création ou oubli) est une ligne du registre `envois_ts` (AG-043, Codex n° 2).
$insE = $db->prepare("INSERT INTO envois_ts (envoye_le, type, email) VALUES (NOW(), 'creation', ?)");
for ($i = 1; $i < TS_QUOTA_ENVOIS; $i++) $insE->execute(["d$i@lotetgaronne.fr"]);
// File pleine (30 envois en 24 h) : même refus pour une adresse nouvelle, déjà créée ou titulaire, aucun compte ni mail.
$db->exec("INSERT INTO comptes_ts (email, hash, actif, doit_changer, cree_le) VALUES ('titulaire@lotetgaronne.fr', NULL, 1, 0, NOW())");
$pleine = array_map(fn($e) => appel(['action' => 'tsDemandeAcces'], ['email' => $e]), ['nouvelle@lotetgaronne.fr', 'ts.un@lotetgaronne.fr', 'titulaire@lotetgaronne.fr']);
verifier($pleine[0] === $pleine[1] && $pleine[1] === $pleine[2] && ($pleine[0]['ok'] ?? true) === false && str_contains($pleine[0]['error'] ?? '', 'Trop de demandes')
    && count($mails()) === 1 && !$db->query("SELECT COUNT(*) FROM comptes_ts WHERE email = 'nouvelle@lotetgaronne.fr'")->fetchColumn(), 'quota de ' . TS_QUOTA_ENVOIS . ' envois en 24 h : même refus pour toute adresse, rien créé, aucun mail');
$db->exec("DELETE FROM comptes_ts WHERE email = 'titulaire@lotetgaronne.fr'");
// Les envois de plus de 24 h ne comptent plus.
$db->exec("UPDATE envois_ts SET envoye_le = NOW() - INTERVAL 2 DAY WHERE email LIKE 'd%@lotetgaronne.fr'");
// Section critique : une autre connexion tient le verrou, la demande attend puis est refusée sans rien écrire.
$autre = new PDO("mysql:host=$hote;dbname=ateliers_test_api;charset=utf8mb4", $util, $mdp, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$autre->query("SELECT GET_LOCK('ts_demande_acces', 1)");
$occupe = appel(['action' => 'tsDemandeAcces'], ['email' => 'concurrente@lotetgaronne.fr']);
$autre->query("SELECT RELEASE_LOCK('ts_demande_acces')");
verifier(($occupe['ok'] ?? true) === false && !$db->query("SELECT COUNT(*) FROM comptes_ts WHERE email = 'concurrente@lotetgaronne.fr'")->fetchColumn(), 'demande pendant la section critique d\'une autre : refusée, rien créé');
// Purges au passage d'une demande (Codex n° 3) : premier accès jamais terminé depuis 30 jours supprimé ;
// compte déjà utilisé puis réinitialisé (même ancien), ou utilisé, gardé ; registre de plus de 7 jours effacé.
$db->exec("INSERT INTO comptes_ts (email, hash, actif, doit_changer, cree_le, mdp_change_le) VALUES
    ('dormant@lotetgaronne.fr', NULL, 1, 1, NOW() - INTERVAL 31 DAY, NULL),
    ('reinit@lotetgaronne.fr', NULL, 1, 1, NOW() - INTERVAL 31 DAY, NOW() - INTERVAL 20 DAY),
    ('actif@lotetgaronne.fr', NULL, 1, 0, NOW() - INTERVAL 31 DAY, NOW() - INTERVAL 20 DAY)");
$db->exec("INSERT INTO envois_ts (envoye_le, type, email) VALUES (NOW() - INTERVAL 8 DAY, 'creation', 'vieux@lotetgaronne.fr')");
verifier(appel(['action' => 'tsDemandeAcces'], ['email' => 'concurrente@lotetgaronne.fr'])['ok'] === true && count($mails()) === 2
    && $db->query("SELECT COUNT(*) FROM comptes_ts WHERE email = 'concurrente@lotetgaronne.fr'")->fetchColumn() == 1, 'verrou libéré : le compte est créé et le mail part');
verifier(!$db->query("SELECT COUNT(*) FROM comptes_ts WHERE email = 'dormant@lotetgaronne.fr'")->fetchColumn()
    && $db->query("SELECT COUNT(*) FROM comptes_ts WHERE email IN ('reinit@lotetgaronne.fr', 'actif@lotetgaronne.fr')")->fetchColumn() == 2
    && !$db->query("SELECT COUNT(*) FROM envois_ts WHERE email = 'vieux@lotetgaronne.fr'")->fetchColumn(), 'purge : premier accès jamais terminé supprimé, compte déjà utilisé (même réinitialisé) gardé, registre de plus de 7 jours effacé');
// Échec d'envoi : le dossier des mails disparaît, le compte et son envoi sont annulés.
rename($dossierMails, "$dossierMails.sav");
$echec = appel(['action' => 'tsDemandeAcces'], ['email' => 'sans.mail@lotetgaronne.fr']);
rename("$dossierMails.sav", $dossierMails);
verifier(($echec['ok'] ?? true) === false && str_contains($echec['error'] ?? '', 'Envoi du mail impossible')
    && !$db->query("SELECT COUNT(*) FROM comptes_ts WHERE email = 'sans.mail@lotetgaronne.fr'")->fetchColumn()
    && !$db->query("SELECT COUNT(*) FROM envois_ts WHERE email = 'sans.mail@lotetgaronne.fr'")->fetchColumn(), 'échec d\'envoi du mail : compte et envoi annulés');
$db->exec("DELETE FROM comptes_ts WHERE email IN ('concurrente@lotetgaronne.fr', 'actif@lotetgaronne.fr', 'reinit@lotetgaronne.fr')");
// 31 oublis pour LA MÊME adresse (Codex n° 2) : le 31e est refusé, chaque envoi comptant pour un.
$db->exec("DELETE FROM envois_ts");
$insO = $db->prepare("INSERT INTO envois_ts (envoye_le, type, email) VALUES (NOW(), 'oubli', 'ts.un@lotetgaronne.fr')");
for ($i = 0; $i < TS_QUOTA_ENVOIS; $i++) $insO->execute();
$nbm = count($mails());
$r31 = appel(['action' => 'tsMdpOublie'], ['email' => 'ts.un@lotetgaronne.fr']);
verifier(($r31['ok'] ?? true) === false && str_contains($r31['error'] ?? '', 'Trop de demandes') && count($mails()) === $nbm, 'quota : le 31e oubli pour une même adresse est refusé (un envoi = une ligne, pas une adresse)');
$db->exec("DELETE FROM envois_ts");
$TL = appel(['action' => 'tsListe'], $A);
verifier(($TL['ok'] ?? false) && array_column($TL['comptes'], 'email') === ['ts.un@lotetgaronne.fr'], 'liste Admin : le compte créé par la demande y figure');
verifier(appel(['action' => 'tsListe'], ['token' => $nv['token']])['ok'] === false, 'liste Admin : refusée à un conseiller');
$cree = appel(['action' => 'tsCreerCompte'], $A + ['email' => 'ts.deux@lotetgaronne.fr']);
verifier(($cree['ok'] ?? false) && strlen($cree['newPassword'] ?? '') === 12, 'création par l\'Admin : mot de passe provisoire rendu une fois');
verifier(appel(['action' => 'tsCreerCompte'], $A + ['email' => 'ts.deux@lotetgaronne.fr'])['ok'] === false, 'création en double refusée');
verifier(appel(['action' => 'tsCreerCompte'], $A + ['email' => 'ts.trois@example.org'])['ok'] === false, 'création par l\'Admin : domaine imposé aussi');
verifier(!in_array('ts.un@lotetgaronne.fr', array_column(appel(['action' => 'getComptes'])['comptes'], 'conseiller'), true), 'un compte TS n\'apparaît jamais dans getComptes');
$jr = $db->query("SELECT conseiller, ref FROM journal WHERE action = 'tsCreerCompte' ORDER BY horodatage DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
verifier($jr === ['conseiller' => 'Conseiller Test', 'ref' => 'ts.deux@lotetgaronne.fr'], 'création journalisée (auteur, cible)');
verifier(appel(['action' => 'tsSupprimer'], $A + ['email' => 'ts.deux@lotetgaronne.fr'])['ok'] === true, 'compte de test supprimé');

$inconnu = appel(['action' => 'tsConnexion'], ['identifiant' => 'personne@example.org', 'password' => 'x']);
$faux = appel(['action' => 'tsConnexion'], ['identifiant' => 'ts.un@lotetgaronne.fr', 'password' => 'x']);
verifier(($inconnu['error'] ?? '') === TS_ERREUR_CONNEXION && ($faux['error'] ?? '') === TS_ERREUR_CONNEXION, 'connexion : même message, compte connu ou non');
verifier(appel(['action' => 'tsConnexion', 'identifiant' => 'ts.un@lotetgaronne.fr', 'password' => $provisoire])['ok'] === false, 'connexion : mot de passe dans l\'URL ignoré');
// AG-046 (04/11/2026) : première connexion (inscription) sans code par mail ; la session ne sert qu'au changement.
$nbAvantPremiere = count(glob("$dossierMails/*.txt") ?: []);
$prem = appel(['action' => 'tsConnexion'], ['identifiant' => 'ts.un@lotetgaronne.fr', 'password' => $provisoire]);
verifier(($prem['ok'] ?? false) && ($prem['token'] ?? '') !== '' && empty($prem['etape2']) && ($prem['doit_changer'] ?? false) === true
    && count(glob("$dossierMails/*.txt") ?: []) === $nbAvantPremiere, 'inscription : première connexion sans code par mail, changement du provisoire exigé');
$ts = ts_connexion('TS. Un@Lotetgaronne.fr', $provisoire);
verifier(($ts['ok'] ?? false) && ($ts['doit_changer'] ?? false) === true, 'connexion TS (adresse avec espace et majuscules du clavier) : provisoire signalé');
$T = ['token' => $ts['token'] ?? ''];
verifier(($ts_a = appel(['action' => 'tsAteliers'], $T))['ok'] === false && ($ts_a['doit_changer'] ?? false), 'provisoire : ateliers refusés tant qu\'il n\'est pas changé');
verifier(appel(['action' => 'tsChangerMdp'], $T + ['password' => 'court'])['ok'] === false, 'changement : politique de mot de passe');
verifier(appel(['action' => 'tsChangerMdp'], $T + ['password' => 'Travail-Social-47!'])['ok'] === true, 'changement du provisoire sans l\'actuel');
verifier(appel(['action' => 'tsChangerMdp'], $T + ['password' => 'Autre-Social-47!!'])['ok'] === false, 'second changement : l\'actuel exigé');
ts_connexion('ts.un@lotetgaronne.fr', 'Travail-Social-47!', $apres);
verifier(($apres['etape2'] ?? false) === true && empty($apres['token']), 'inscription terminée : les connexions suivantes exigent le code par mail');
foreach (['getAll', 'getConfig', 'getVisibility', 'saveEntry', 'delete', 'getTickets', 'creerTicket', 'selfSetPassword', 'tsListe', 'tsStats', 'tsCreerCompte', 'getComptes'] as $act) {
    $r = appel(['action' => $act], $T + ['email' => 'x@example.org']);
    if ($act === 'getComptes') { verifier(!isset($r['comptes'][0]['role']), 'jeton TS : getComptes réduit à la liste publique'); continue; }
    verifier(($r['ok'] ?? true) === false, "jeton TS : $act refusé");
}
verifier(appel(['action' => 'tsAteliers'], $A)['ok'] === false, 'tsAteliers refusé à un jeton conseiller/admin');
$r = appel(['action' => 'tsAteliers'], $T);
$ids = array_map(fn($a) => $a['thematique'] . '@' . $a['commune'], $r['ateliers'] ?? []);
verifier(($r['ok'] ?? false) && in_array('Mail@Agen', $ids, true) && in_array('Smartphone@Nérac', $ids, true), 'ateliers : Planifié du jour et à venir');
$futurs = $db->query("SELECT COUNT(*) FROM ateliers WHERE statut = 'Planifié' AND date >= CURDATE()")->fetchColumn();
verifier(count($r['ateliers'] ?? []) === (int) $futurs && !array_filter($r['ateliers'], fn($a) => $a['statut'] !== 'Planifié' || $a['date'] < date('Y-m-d')), 'ateliers : ni passés, ni autres statuts (' . count($r['ateliers'] ?? []) . ')');
verifier(array_keys($r['ateliers'][0] ?? []) === ['date', 'horaire', 'statut', 'orienteur', 'commune', 'lieu', 'public', 'thematique'], 'ateliers : 8 champs seulement, dont l\'horaire (ni conseiller, ni remarques, ni inscrits)');
verifier(array_column(array_filter($r['ateliers'] ?? [], fn($a) => $a['thematique'] === 'Mail'), 'horaire') === ['14:00'], 'ateliers : l\'horaire de début est renvoyé');
verifier(!str_contains(json_encode($r), 'remarque secrète'), 'ateliers : la remarque ne sort pas');
// Amendement 3 : « à partir du jour de Paris » ne dépend pas du fuseau de MySQL. Pour chaque
// fuseau où le jour MySQL diffère de celui de Paris (au moins un des deux, toujours), la veille
// reste exclue et le jour inclus.
$db->exec("INSERT INTO ateliers (id, statut, date, orienteur, commune, lieu, thematique, public, conseiller, co_animateur, residence, remarques, inscrits)
    VALUES ('ts_borne_jour', 'Planifié', '" . date('Y-m-d') . "', 'O', 'BorneJour', 'L', 'T', 'P', 'Conseiller Test', '', '', '', NULL),
           ('ts_borne_veille', 'Planifié', '" . date('Y-m-d', strtotime('-1 day')) . "', 'O', 'BorneVeille', 'L', 'T', 'P', 'Conseiller Test', '', '', '', NULL)");
$bornes = 0;
foreach (['+13:00', '-12:00'] as $zone) {
    $db->exec("SET GLOBAL time_zone = '$zone'");
    // Les sessions sont datées par PHP (Paris) mais lues avec NOW() de MySQL : un fuseau SQL décalé de plus de 6 h
    // les ferait expirer et le test échouerait pour une autre raison que la frontière de minuit (CI du 04/11/2026, midi).
    $db->exec("UPDATE sessions SET expire = '2099-01-01 00:00:00'");
    $jourSql = (new PDO("mysql:host=$hote;charset=utf8mb4", $util, $mdp))->query('SELECT CURDATE()')->fetchColumn();
    if ($jourSql === date('Y-m-d')) continue;
    $bornes++;
    $communes = array_column(appel(['action' => 'tsAteliers'], $T)['ateliers'] ?? [], 'commune');
    verifier(in_array('BorneJour', $communes, true) && !in_array('BorneVeille', $communes, true), "frontière de minuit (MySQL en $zone, jour $jourSql ≠ Paris) : jour inclus, veille exclue");
}
$db->exec("SET GLOBAL time_zone = 'SYSTEM'");
verifier($bornes >= 1, 'frontière de minuit : au moins un fuseau testé avec un jour SQL différent');
$db->exec("DELETE FROM ateliers WHERE id IN ('ts_borne_jour', 'ts_borne_veille')");
$db->exec("UPDATE config SET valeur = '" . json_encode(['ts_public:Seniors' => false, 'ts_public:Tous publics' => true, 'saisie' => true], JSON_UNESCAPED_UNICODE) . "' WHERE cle = 'visibility'");
$r2 = appel(['action' => 'tsAteliers'], $T);
$pubs = array_unique(array_column($r2['ateliers'] ?? [], 'public'));
verifier(($r2['ok'] ?? false) && !in_array('Seniors', $pubs, true) && in_array('Tous publics', $pubs, true)
    && count($r2['ateliers']) === count($r['ateliers']) - count(array_filter($r['ateliers'], fn($a) => $a['public'] === 'Seniors')),
    'public éteint (Seniors) : ses ateliers ne sortent pas, les autres restent');
$tous = array_values(array_unique(array_filter(array_map(fn($a) => trim($a['public']), $r['ateliers']))));
$db->exec("UPDATE config SET valeur = '" . json_encode(array_fill_keys(array_map(fn($p) => "ts_public:$p", $tous), 'false'), JSON_UNESCAPED_UNICODE) . "' WHERE cle = 'visibility'");
$r3 = appel(['action' => 'tsAteliers'], $T);
verifier(($r3['ok'] ?? false) && !array_filter($r3['ateliers'] ?? [], fn($a) => trim($a['public']) !== ''), 'tous les publics éteints : plus aucun atelier avec un public');
$db->exec("UPDATE config SET valeur = '{}' WHERE cle = 'visibility'");

verifier(appel(['action' => 'tsActiver'], $A + ['email' => 'ts.un@lotetgaronne.fr', 'actif' => '0'])['ok'] === true
    && appel(['action' => 'tsAteliers'], $T)['ok'] === false, 'désactivation : la session tombe aussitôt');
verifier(appel(['action' => 'tsConnexion'], ['identifiant' => 'ts.un@lotetgaronne.fr', 'password' => 'Travail-Social-47!'])['error'] === TS_ERREUR_CONNEXION, 'compte désactivé : même message d\'échec');
appel(['action' => 'tsActiver'], $A + ['email' => 'ts.un@lotetgaronne.fr', 'actif' => '1']);
$re = appel(['action' => 'tsReinitMdp'], $A + ['email' => 'ts.un@lotetgaronne.fr']);
verifier(($re['ok'] ?? false) && ts_connexion('ts.un@lotetgaronne.fr', $re['newPassword'], $e1re)['doit_changer'] === true && ($e1re['etape2'] ?? false) === true, 'réinitialisation d\'un compte déjà utilisé : nouveau provisoire, code par mail exigé');
for ($i = 0; $i < API_ECHECS_MAX; $i++) appel(['action' => 'tsConnexion'], ['identifiant' => 'bloque@example.org', 'password' => 'x']);
verifier(str_contains(appel(['action' => 'tsConnexion'], ['identifiant' => 'bloque@example.org', 'password' => 'x'])['error'] ?? '', 'Trop de tentatives'), 'blocage après ' . API_ECHECS_MAX . ' échecs, même pour une adresse inconnue');

$adm = appel(['action' => 'tsConnexion'], ['identifiant' => 'Conseiller Test', 'password' => 'secret-test']);
verifier(($adm['ok'] ?? false) && !isset($adm['doit_changer']), 'admin : connexion à la page TS avec ses accès');
verifier(appel(['action' => 'tsAteliers'], ['token' => $adm['token']])['ok'] === true && appel(['action' => 'getAll'], ['token' => $adm['token']])['ok'] === false, 'admin sur la page TS : lecture TS seule, getAll refusé');
verifier(appel(['action' => 'tsChangerMdp'], ['token' => $adm['token'], 'password' => 'Autre-Social-47!!'])['ok'] === false, 'admin sur la page TS : pas de changement de mot de passe ici');
verifier(appel(['action' => 'tsConnexion'], ['identifiant' => 'Nouveau Venu', 'password' => 'Nouveau-Mdp-2026!'])['error'] === TS_ERREUR_CONNEXION, 'conseiller (rôle user) : refusé sur la page TS');
// AG-043 : « mot de passe oublié ». Provisoire par mail, mis de côté 24 h ; l'ancien mot de passe reste valable jusqu'à l'emploi du nouveau.
$nbm = count($mails());
$o1 = appel(['action' => 'tsMdpOublie'], ['email' => 'ts.un@lotetgaronne.fr']);
$o2 = appel(['action' => 'tsMdpOublie'], ['email' => 'inconnu@lotetgaronne.fr']);
verifier($o1 === ['ok' => true] && $o2 === $o1 && count($mails()) === $nbm + 1, 'oubli : même réponse, compte connu ou non ; un mail seulement pour le compte');
$mo = $mails(); $dernier = end($mo);
verifier(str_starts_with($dernier, 'A: ts.un@lotetgaronne.fr') && str_contains($dernier, 'Nouveau mot de passe provisoire') && preg_match('/Mot de passe provisoire : (\S{12})\n/', $dernier, $mo2) === 1, 'oubli : mail au titulaire avec un nouveau provisoire');
$oubli = $mo2[1] ?? '';
verifier(($T2 = ts_connexion('ts.un@lotetgaronne.fr', $re['newPassword']))['ok'] === true, 'oubli : l\'ancien mot de passe reste valable tant que le provisoire n\'est pas employé (pas de blocage par un tiers)');
$nbm = count($mails());
verifier(appel(['action' => 'tsMdpOublie'], ['email' => 'ts.un@lotetgaronne.fr']) === $o1 && count($mails()) === $nbm, 'oubli : une seule demande par compte et par 15 min, même réponse, aucun mail de plus');
verifier(($o3 = ts_connexion('ts.un@lotetgaronne.fr', $oubli, $e1ou))['ok'] === true && ($o3['doit_changer'] ?? false) === true && ($e1ou['etape2'] ?? false) === true, 'oubli : le provisoire reçu ouvre le compte après le code par mail, changement obligatoire');
verifier(appel(['action' => 'tsConnexion'], ['identifiant' => 'ts.un@lotetgaronne.fr', 'password' => $re['newPassword']])['ok'] === false, 'oubli : l\'ancien mot de passe est remplacé une fois le provisoire employé');
verifier(ts_connexion('ts.un@lotetgaronne.fr', $oubli)['ok'] === true, 'oubli : le provisoire reste le mot de passe jusqu\'au changement');
$r = appel(['action' => 'tsMdpOublie'], ['email' => 'agent@example.org']);
verifier(($r['ok'] ?? true) === false && str_contains($r['error'] ?? '', '@lotetgaronne.fr'), 'oubli : domaine imposé aussi');
// AG-043 : 2FA par code reçu par mail (étape 1 : mot de passe → défi ; étape 2 : défi + code → session).
$nbLogin = fn() => (int) $db->query("SELECT COUNT(*) FROM journal WHERE action = 'loginTs' AND source = 'ts'")->fetchColumn();
$codeDuDernierMail = function () use ($mails) { $m = $mails(); preg_match('/Code de connexion : (\d{6})/', end($m), $x); return $x[1] ?? ''; };
$resetCode = fn() => $db->exec("UPDATE comptes_ts SET code_expire = NULL WHERE email = 'ts.un@lotetgaronne.fr'");
$avantLogin = $nbLogin();
$resetCode(); $e1 = appel(['action' => 'tsConnexion'], ['identifiant' => 'ts.un@lotetgaronne.fr', 'password' => $oubli]);
verifier(($e1['etape2'] ?? false) === true && !isset($e1['token']) && ($e1['defi'] ?? '') !== '' && $nbLogin() === $avantLogin, '2FA : le mot de passe seul n\'ouvre pas de session et n\'est pas compté comme connexion');
$code = $codeDuDernierMail();
verifier(preg_match('/^\d{6}$/', $code) === 1 && !str_contains(json_encode($e1), $code), '2FA : code à 6 chiffres envoyé par mail, absent de la réponse');
$e1b = appel(['action' => 'tsConnexion'], ['identifiant' => 'ts.un@lotetgaronne.fr', 'password' => $oubli]);
verifier(($e1b['ok'] ?? true) === false && str_contains($e1b['error'] ?? '', 'Un code vient d\'être envoyé'), '2FA : pas de nouveau code pendant la première minute');
verifier(appel(['action' => 'tsCode'], ['defi' => $e1['defi'], 'code' => $code === '000000' ? '111111' : '000000'])['ok'] === false && $nbLogin() === $avantLogin, '2FA : code faux refusé, aucune connexion comptée');
$ok2 = appel(['action' => 'tsCode'], ['defi' => $e1['defi'], 'code' => $code]);
verifier(($ok2['ok'] ?? false) === true && ($ok2['token'] ?? '') !== '' && $nbLogin() === $avantLogin + 1, '2FA : bon code, session ouverte, une connexion comptée');
verifier(appel(['action' => 'tsCode'], ['defi' => $e1['defi'], 'code' => $code])['ok'] === false, '2FA : le défi ne sert qu\'une fois (rejeu refusé)');
// 5 codes faux : le défi est effacé, même le bon code est refusé ensuite.
$resetCode(); $e2 = appel(['action' => 'tsConnexion'], ['identifiant' => 'ts.un@lotetgaronne.fr', 'password' => $oubli]); $c2 = $codeDuDernierMail();
$faux = $c2 === '123456' ? '654321' : '123456';
for ($i = 0; $i < TS_CODE_ESSAIS; $i++) appel(['action' => 'tsCode'], ['defi' => $e2['defi'], 'code' => $faux]);
verifier(appel(['action' => 'tsCode'], ['defi' => $e2['defi'], 'code' => $c2])['ok'] === false, '2FA : après ' . TS_CODE_ESSAIS . ' codes faux, le défi est effacé');
$db->exec("DELETE FROM tentatives WHERE conseiller = 'ts:ts.un@lotetgaronne.fr'");
// Défi remplacé : l'ancien défi ne marche plus.
$resetCode(); $e3 = appel(['action' => 'tsConnexion'], ['identifiant' => 'ts.un@lotetgaronne.fr', 'password' => $oubli]); $c3 = $codeDuDernierMail();
$resetCode(); $e4 = appel(['action' => 'tsConnexion'], ['identifiant' => 'ts.un@lotetgaronne.fr', 'password' => $oubli]); $c4 = $codeDuDernierMail();
verifier(appel(['action' => 'tsCode'], ['defi' => $e3['defi'], 'code' => $c3])['ok'] === false && appel(['action' => 'tsCode'], ['defi' => $e4['defi'], 'code' => $c4])['ok'] === true, '2FA : un défi remplacé est refusé, le dernier marche');
// Mot de passe changé entre les deux étapes (réinitialisation Admin) : la validation est refusée.
$resetCode(); $e5 = appel(['action' => 'tsConnexion'], ['identifiant' => 'ts.un@lotetgaronne.fr', 'password' => $oubli]); $c5 = $codeDuDernierMail();
$rr2 = appel(['action' => 'tsReinitMdp'], $A + ['email' => 'ts.un@lotetgaronne.fr']);
verifier(($rr2['ok'] ?? false) && appel(['action' => 'tsCode'], ['defi' => $e5['defi'], 'code' => $c5])['ok'] === false, '2FA : mot de passe réinitialisé entre les deux étapes, validation refusée');
// Compte désactivé entre les deux étapes : refusé.
$resetCode(); $e6 = appel(['action' => 'tsConnexion'], ['identifiant' => 'ts.un@lotetgaronne.fr', 'password' => $rr2['newPassword']]); $c6 = $codeDuDernierMail();
appel(['action' => 'tsActiver'], $A + ['email' => 'ts.un@lotetgaronne.fr', 'actif' => '0']);
$r6 = appel(['action' => 'tsCode'], ['defi' => $e6['defi'], 'code' => $c6]);
appel(['action' => 'tsActiver'], $A + ['email' => 'ts.un@lotetgaronne.fr', 'actif' => '1']);
verifier(($r6['ok'] ?? true) === false, '2FA : compte désactivé entre les deux étapes, validation refusée');
// Quota des mails de code : 20 par compte et par 24 h.
$resetCode(); $db->exec("DELETE FROM envois_ts");
$insC = $db->prepare("INSERT INTO envois_ts (envoye_le, type, email) VALUES (NOW(), 'code', 'ts.un@lotetgaronne.fr')");
for ($i = 0; $i < TS_QUOTA_CODES; $i++) $insC->execute();
$nbm = count($mails());
$q = appel(['action' => 'tsConnexion'], ['identifiant' => 'ts.un@lotetgaronne.fr', 'password' => $rr2['newPassword']]);
verifier(($q['ok'] ?? true) === false && str_contains($q['error'] ?? '', 'Trop de codes') && count($mails()) === $nbm, '2FA : au plus ' . TS_QUOTA_CODES . ' mails de code par compte et par 24 h');
$db->exec("DELETE FROM envois_ts");
// Échec d'envoi du code : le défi est effacé et l'envoi n'est pas compté.
$resetCode();
rename($dossierMails, "$dossierMails.sav");
$qe = appel(['action' => 'tsConnexion'], ['identifiant' => 'ts.un@lotetgaronne.fr', 'password' => $rr2['newPassword']]);
rename("$dossierMails.sav", $dossierMails);
verifier(($qe['ok'] ?? true) === false && str_contains($qe['error'] ?? '', 'Envoi du code impossible') && !$db->query("SELECT COUNT(*) FROM comptes_ts WHERE email = 'ts.un@lotetgaronne.fr' AND defi_hash IS NOT NULL")->fetchColumn()
    && !$db->query("SELECT COUNT(*) FROM envois_ts WHERE type = 'code'")->fetchColumn(), '2FA : mail du code non parti, défi effacé et envoi non compté');

// Codex (2e relecture) n° 1 : cinq codes faux SIMULTANÉS comptent pour cinq (défi inutilisable, blocage appliqué).
$resetCode(); $db->exec("DELETE FROM tentatives WHERE conseiller = 'ts:ts.un@lotetgaronne.fr'");
$ec = appel(['action' => 'tsConnexion'], ['identifiant' => 'ts.un@lotetgaronne.fr', 'password' => $rr2['newPassword']]); $cc = $codeDuDernierMail();
$mauvais = $cc === '123456' ? '654321' : '123456';
$par = appels_paralleles(array_fill(0, TS_CODE_ESSAIS, [['action' => 'tsCode'], ['defi' => $ec['defi'], 'code' => $mauvais]]));
$bloque = $db->query("SELECT bloque_jusqua FROM tentatives WHERE conseiller = 'ts:ts.un@lotetgaronne.fr'")->fetchColumn();
verifier(count(array_filter($par, fn($r) => ($r['ok'] ?? true) === false)) === TS_CODE_ESSAIS && $bloque && strtotime($bloque) > time()
    && !$db->query("SELECT COUNT(*) FROM comptes_ts WHERE email = 'ts.un@lotetgaronne.fr' AND defi_hash IS NOT NULL")->fetchColumn(), 'concurrence : ' . TS_CODE_ESSAIS . ' codes faux simultanés = ' . TS_CODE_ESSAIS . ' échecs comptés, blocage appliqué, défi effacé');
verifier(appel(['action' => 'tsCode'], ['defi' => $ec['defi'], 'code' => $cc])['ok'] === false, 'concurrence : le bon code ne passe plus après les cinq codes faux');
$db->exec("DELETE FROM tentatives WHERE conseiller = 'ts:ts.un@lotetgaronne.fr'");
// n° 2 : un changement de mot de passe en cours (ligne verrouillée) pendant la validation du bon code : la validation attend, puis est refusée.
$resetCode(); $ed = appel(['action' => 'tsConnexion'], ['identifiant' => 'ts.un@lotetgaronne.fr', 'password' => $rr2['newPassword']]); $cd = $codeDuDernierMail();
$autre2 = new PDO("mysql:host=$hote;dbname=ateliers_test_api;charset=utf8mb4", $util, $mdp, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$autre2->beginTransaction();
$autre2->exec("UPDATE comptes_ts SET hash = 'mot-de-passe-change-en-cours' WHERE email = 'ts.un@lotetgaronne.fr'");   // changement de mot de passe concurrent (le défi reste en place)
$simul = appels_paralleles([[['action' => 'tsCode'], ['defi' => $ed['defi'], 'code' => $cd]]], function () use ($autre2) { $autre2->commit(); });
verifier(($simul[0]['ok'] ?? true) === false && !isset($simul[0]['token']), 'concurrence : aucun jeton rendu à une validation concurrente d\'un reset');
// Un reset juste après la connexion complète ferme la session qui vient d'être ouverte.
$rp = appel(['action' => 'tsReinitMdp'], $A + ['email' => 'ts.un@lotetgaronne.fr']);
$vv = ts_connexion('ts.un@lotetgaronne.fr', $rp['newPassword']);
appel(['action' => 'tsReinitMdp'], $A + ['email' => 'ts.un@lotetgaronne.fr']);
verifier(($vv['token'] ?? '') !== '' && appel(['action' => 'tsAteliers'], ['token' => $vv['token']])['ok'] === false, 'reset après la connexion complète : la session ouverte est fermée');
// n° 3 : migration d'une base ancienne (colonnes absentes) : un compte déjà utilisé puis réinitialisé n'est jamais purgé.
$db->exec("ALTER TABLE comptes_ts DROP COLUMN mdp_change_le, DROP COLUMN defi_mdp, DROP COLUMN defi_oubli");
$db->exec("INSERT INTO comptes_ts (email, hash, actif, doit_changer, cree_le) VALUES ('ancien.reinit@lotetgaronne.fr', NULL, 1, 1, NOW() - INTERVAL 40 DAY)");
appel(['action' => 'tsDemandeAcces'], ['email' => 'declencheur@lotetgaronne.fr']);   // déclenche la migration puis la purge
verifier($db->query("SELECT COUNT(*) FROM comptes_ts WHERE email = 'ancien.reinit@lotetgaronne.fr'")->fetchColumn() == 1
    && $db->query("SELECT mdp_change_le IS NOT NULL FROM comptes_ts WHERE email = 'ancien.reinit@lotetgaronne.fr'")->fetchColumn() == 1, 'migration : un compte ancien à historique inconnu (déjà utilisé puis réinitialisé) est préservé de la purge');
$db->exec("DELETE FROM comptes_ts WHERE email IN ('ancien.reinit@lotetgaronne.fr', 'declencheur@lotetgaronne.fr')");
// Codex (3e relecture) : base DÉJÀ migrée par la version précédente (colonnes présentes, dates NULL) : le rattrapage
// s'exécute une fois, préserve ces comptes ; ceux créés après lui gardent leur suivi exact (purgés à 30 jours).
$db->exec("DELETE FROM config WHERE cle = 'ts_rattrapage_mdp_change'");
$db->exec("INSERT INTO comptes_ts (email, hash, actif, doit_changer, cree_le, mdp_change_le) VALUES ('deja.migre@lotetgaronne.fr', NULL, 1, 1, NOW() - INTERVAL 40 DAY, NULL)");
appel(['action' => 'tsDemandeAcces'], ['email' => 'declencheur2@lotetgaronne.fr']);
verifier($db->query("SELECT COUNT(*) FROM comptes_ts WHERE email = 'deja.migre@lotetgaronne.fr' AND mdp_change_le IS NOT NULL")->fetchColumn() == 1
    && $db->query("SELECT COUNT(*) FROM config WHERE cle = 'ts_rattrapage_mdp_change'")->fetchColumn() == 1, 'migration : base déjà migrée, comptes à historique inconnu préservés (rattrapage exécuté, marqué)');
$db->exec("INSERT INTO comptes_ts (email, hash, actif, doit_changer, cree_le, mdp_change_le) VALUES ('cree.apres@lotetgaronne.fr', NULL, 1, 1, NOW() - INTERVAL 40 DAY, NULL)");
$db->exec("DELETE FROM envois_ts");
appel(['action' => 'tsDemandeAcces'], ['email' => 'declencheur3@lotetgaronne.fr']);
verifier(!$db->query("SELECT COUNT(*) FROM comptes_ts WHERE email = 'cree.apres@lotetgaronne.fr'")->fetchColumn(), 'migration : le rattrapage ne se refait pas, un compte créé après lui et jamais utilisé est purgé à 30 jours');
$db->exec("DELETE FROM comptes_ts WHERE email IN ('deja.migre@lotetgaronne.fr', 'declencheur2@lotetgaronne.fr', 'declencheur3@lotetgaronne.fr')");
// Codex (4e relecture) : rattrapage UNIQUE en concurrence. Un premier appel tient le marqueur (transaction pas
// encore validée) ; un second appel arrive : il ne doit faire AUCUN rattrapage, donc ne pas dater un compte neuf.
// (L'ancien code lisait « marqueur absent » et datait ce compte.)
$db->exec("DELETE FROM config WHERE cle = 'ts_rattrapage_mdp_change'");
$db->exec("DELETE FROM envois_ts");
$db->exec("INSERT INTO comptes_ts (email, hash, actif, doit_changer, cree_le, mdp_change_le) VALUES ('neuf.concurrent@lotetgaronne.fr', NULL, 1, 1, NOW(), NULL)");
$premier = new PDO("mysql:host=$hote;dbname=ateliers_test_api;charset=utf8mb4", $util, $mdp, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$premier->beginTransaction();
$premier->exec("INSERT INTO config (cle, valeur) VALUES ('ts_rattrapage_mdp_change', 'essai')");
appels_paralleles([[['action' => 'tsDemandeAcces'], ['email' => 'declencheur4@lotetgaronne.fr']]], function () use ($premier) { $premier->commit(); });
verifier($db->query("SELECT COUNT(*) FROM comptes_ts WHERE email = 'neuf.concurrent@lotetgaronne.fr' AND mdp_change_le IS NULL")->fetchColumn() == 1,
    'migration : rattrapage simultané, le second appel ne fait aucun rattrapage (marqueur pris avant, dans la même transaction)');
$db->exec("DELETE FROM comptes_ts WHERE email IN ('neuf.concurrent@lotetgaronne.fr', 'declencheur4@lotetgaronne.fr')");
$db->exec("DELETE FROM envois_ts");
// AG-046, amendements de Codex. n° 1 : compte jamais terminé, « oubli », code validé, abandon avant le choix du
// mot de passe : le provisoire d'oubli promu n'ouvre JAMAIS sans code à la connexion suivante.
$provisoireDe = function (string $email) use ($mails) { $m = $mails(); $d = end($m); return (str_starts_with($d, "A: $email") && preg_match('/Mot de passe provisoire : (\S{12})\n/', $d, $x)) ? $x[1] : ''; };
appel(['action' => 'tsDemandeAcces'], ['email' => 'jamais.fini@lotetgaronne.fr']);
appel(['action' => 'tsMdpOublie'], ['email' => 'jamais.fini@lotetgaronne.fr']);
$pOubli = $provisoireDe('jamais.fini@lotetgaronne.fr');
$promu = ts_connexion('jamais.fini@lotetgaronne.fr', $pOubli, $e1promu);
$apresAbandon = appel(['action' => 'tsConnexion'], ['identifiant' => 'jamais.fini@lotetgaronne.fr', 'password' => $pOubli]);
verifier($pOubli !== '' && ($e1promu['etape2'] ?? false) === true && ($promu['doit_changer'] ?? false) === true
    && ($apresAbandon['etape2'] ?? false) === true && empty($apresAbandon['token']), 'inscription : provisoire d\'oubli promu puis abandonné, la connexion suivante exige le code');
// n° 2 : la borne des 30 jours est contrôlée à la connexion, sans attendre la purge d'une nouvelle demande.
$db->exec("DELETE FROM envois_ts");
appel(['action' => 'tsDemandeAcces'], ['email' => 'vieux.compte@lotetgaronne.fr']);
$pVieux = $provisoireDe('vieux.compte@lotetgaronne.fr');
$db->exec("UPDATE comptes_ts SET cree_le = NOW() - INTERVAL 31 DAY WHERE email = 'vieux.compte@lotetgaronne.fr'");
$vieux = appel(['action' => 'tsConnexion'], ['identifiant' => 'vieux.compte@lotetgaronne.fr', 'password' => $pVieux]);
verifier($pVieux !== '' && ($vieux['etape2'] ?? false) === true && empty($vieux['token']), 'inscription : provisoire de plus de 30 jours, plus de connexion sans code');
$db->exec("DELETE FROM comptes_ts WHERE email IN ('jamais.fini@lotetgaronne.fr', 'vieux.compte@lotetgaronne.fr')");
$db->exec("DELETE FROM envois_ts");
$db->exec("DELETE FROM envois_ts");

// Codex n° 1 : le provisoire d'« oubli » n'est promu qu'après le code, sur l'empreinte vérifiée ; anciennes sessions fermées.
$db->exec("UPDATE comptes_ts SET hash = NULL WHERE email = 'ts.un@lotetgaronne.fr'");
$stable = appel(['action' => 'tsReinitMdp'], $A + ['email' => 'ts.un@lotetgaronne.fr']);
$vieux = ts_connexion('ts.un@lotetgaronne.fr', $stable['newPassword']);   // session « ancienne », provisoire à changer
verifier(($vieux['token'] ?? '') !== '' && appel(['action' => 'tsChangerMdp'], ['token' => $vieux['token'], 'password' => 'Ancien-Social-47!!'])['ok'] === true, 'oubli/promotion : session ancienne ouverte, mot de passe personnel posé');
$vieux = ts_connexion('ts.un@lotetgaronne.fr', 'Ancien-Social-47!!');
$db->exec("UPDATE comptes_ts SET oubli_le = NULL WHERE email = 'ts.un@lotetgaronne.fr'");
verifier(appel(['action' => 'tsMdpOublie'], ['email' => 'ts.un@lotetgaronne.fr'])['ok'] === true, 'oubli/promotion : nouveau provisoire envoyé');
$mo = $mails(); preg_match('/Mot de passe provisoire : (\S{12})\n/', end($mo), $mx); $prov2 = $mx[1] ?? '';
$resetCode(); $eo = appel(['action' => 'tsConnexion'], ['identifiant' => 'ts.un@lotetgaronne.fr', 'password' => $prov2]); $co = $codeDuDernierMail();
$av = $db->query("SELECT hash, hash_oubli FROM comptes_ts WHERE email = 'ts.un@lotetgaronne.fr'")->fetch(PDO::FETCH_ASSOC);
verifier(($eo['etape2'] ?? false) && $av['hash_oubli'] !== null && password_verify(hash('sha256', 'Ancien-Social-47!!'), $av['hash']), 'oubli/promotion : avant le code, rien n\'est promu (l\'ancien mot de passe reste en place)');
// Changement personnel concurrent (mot de passe changé par l'ancienne session) entre les deux étapes : refus, rien de promu à NULL.
appel(['action' => 'tsChangerMdp'], ['token' => $vieux['token'], 'password' => 'Concurrent-Social-47!', 'currentPwd' => 'Ancien-Social-47!!']);
$apres = $db->query("SELECT hash IS NOT NULL AS h, hash_oubli IS NULL AS o FROM comptes_ts WHERE email = 'ts.un@lotetgaronne.fr'")->fetch(PDO::FETCH_ASSOC);
verifier(appel(['action' => 'tsCode'], ['defi' => $eo['defi'], 'code' => $co])['ok'] === false && (int) $apres['h'] === 1 && (int) $apres['o'] === 1, 'oubli/promotion : changement personnel entre les deux étapes, validation refusée, hash jamais mis à NULL');
// Cas nominal : promotion après le code, ancienne session fermée.
$db->exec("UPDATE comptes_ts SET oubli_le = NULL WHERE email = 'ts.un@lotetgaronne.fr'");
$vieux2 = ts_connexion('ts.un@lotetgaronne.fr', 'Concurrent-Social-47!');
appel(['action' => 'tsMdpOublie'], ['email' => 'ts.un@lotetgaronne.fr']);
$mo = $mails(); preg_match('/Mot de passe provisoire : (\S{12})\n/', end($mo), $mx); $prov3 = $mx[1] ?? '';
$nouv = ts_connexion('ts.un@lotetgaronne.fr', $prov3);
verifier(($nouv['ok'] ?? false) && ($nouv['doit_changer'] ?? false) === true && ($vieux2['token'] ?? 'x') !== '' && appel(['action' => 'tsAteliers'], ['token' => $vieux2['token']])['ok'] === false, 'oubli/promotion : promotion après le code, ancienne session fermée (son jeton est refusé)');
verifier(appel(['action' => 'tsChangerMdp'], ['token' => $vieux2['token'], 'password' => 'Pirate-Social-47!!'])['ok'] === false, 'oubli/promotion : l\'ancien jeton ne peut pas choisir le nouveau mot de passe');

// AG-043 : connexions de la page, lues dans le journal (lecture seule, non journalisée).
$nbJournal = fn() => (int) $db->query('SELECT COUNT(*) FROM journal')->fetchColumn();
$avant = $nbJournal();
$st = appel(['action' => 'tsStats'], $A);
$parCompte = array_column($st['comptes'] ?? [], null, 'compte');
$reelles = (int) $db->query("SELECT COUNT(*) FROM journal WHERE action = 'loginTs' AND source = 'ts' AND succes = 1 AND conseiller = 'ts.un@lotetgaronne.fr'")->fetchColumn();
verifier(($st['ok'] ?? false) && $nbJournal() === $avant, 'connexions TS : lecture seule, rien n\'est journalisé par la consultation');
verifier($reelles >= 2 && ($parCompte['ts.un@lotetgaronne.fr']['connexions'] ?? -1) === $reelles && ($parCompte['ts.un@lotetgaronne.fr']['type'] ?? '') === 'TS', "connexions TS : $reelles connexions de ts.un comptées comme dans le journal");
verifier(($parCompte['personne@example.org']['echecs'] ?? 0) >= 1 && ($parCompte['personne@example.org']['connexions'] ?? -1) === 0, 'connexions TS : échecs comptés, y compris pour une adresse sans compte');
verifier(($parCompte['Conseiller Test']['type'] ?? '') === 'Admin' && ($st['jours'][0]['jour'] ?? '') === date('Y-m-d') && ($st['jours'][0]['connexions'] ?? 0) === ($st['total']['connexions'] ?? -1), 'connexions TS : compte Admin distingué, total du jour = total des comptes');
// Codex n° 4 : un compte dont le rôle a changé reste UNE ligne, et somme des comptes = somme des jours.
$db->exec("INSERT INTO journal (horodatage, action, conseiller, ref, role, succes, tentatives, user_agent, source) VALUES
    (NOW() - INTERVAL 1 DAY, 'loginTs', 'Admin Fictif', '', 'admin', 1, 0, '', 'ts'),
    (NOW(), 'loginTs', 'Admin Fictif', '', 'superviseur', 1, 0, '', 'ts')");
$st2 = appel(['action' => 'tsStats'], $A);
$fic = array_values(array_filter($st2['comptes'] ?? [], fn($c) => $c['compte'] === 'Admin Fictif'));
verifier(count($fic) === 1 && $fic[0]['connexions'] === 2 && $fic[0]['type'] === 'Admin'
    && array_sum(array_column($st2['comptes'], 'connexions')) === array_sum(array_column($st2['jours'], 'connexions')) && ($st2['total']['connexions'] ?? -1) === array_sum(array_column($st2['jours'], 'connexions')), 'connexions TS : changement de rôle = une seule ligne, somme des comptes = somme des jours');
verifier(appel(['action' => 'tsStats'], ['token' => $nv['token']])['ok'] === false, 'connexions TS : refusées à un conseiller');
verifier(appel(['action' => 'tsSupprimer'], $A + ['email' => 'ts.un@lotetgaronne.fr'])['ok'] === true
    && !$db->query("SELECT COUNT(*) FROM comptes_ts")->fetchColumn() && !$db->query("SELECT COUNT(*) FROM sessions WHERE conseiller LIKE 'ts:ts.un%'")->fetchColumn(), 'suppression : compte et sessions effacés');
// ── Anomalies validées (AG-044, 04/11/2026) ───────────────────────────────
echo "\nAPI — anomalies validées\n";
$idA = $db->query("SELECT id FROM ateliers WHERE id NOT LIKE 'ts\\_%' ORDER BY id LIMIT 1")->fetchColumn();
verifier(appel(['action' => 'validerAnomalie'], ['token' => $nv['token'], '_id' => $idA, 'categorie' => 'tard', 'empreinte' => '17:30'])['ok'] === false, 'anomalies : validation refusée à un conseiller');
verifier(appel(['action' => 'getAnomaliesValidees'], ['token' => $nv['token']])['ok'] === false, 'anomalies : lecture refusée à un conseiller');
verifier(appel(['action' => 'validerAnomalie'], $A + ['_id' => $idA, 'categorie' => 'inconnue', 'empreinte' => 'x'])['ok'] === false, 'anomalies : catégorie inconnue refusée');
verifier(appel(['action' => 'validerAnomalie'], $A + ['_id' => 'entry_absent', 'categorie' => 'tard', 'empreinte' => '17:30'])['ok'] === false, 'anomalies : atelier introuvable refusé');
verifier(appel(['action' => 'validerAnomalie'], $A + ['_id' => $idA, 'categorie' => 'tard', 'empreinte' => ''])['ok'] === false && appel(['action' => 'validerAnomalie'], $A + ['_id' => $idA, 'categorie' => 'tard', 'empreinte' => str_repeat('x', 201)])['ok'] === false, 'anomalies : empreinte vide ou trop longue refusée');
$vr = appel(['action' => 'validerAnomalie'], $A + ['_id' => $idA, 'categorie' => 'tard', 'empreinte' => '17:30']);
verifier(($vr['ok'] ?? false) === true && ($vr['par'] ?? '') === 'Conseiller Test' && str_starts_with($vr['valide_le'] ?? '', date('Y-m-d')) && ($vr['valide_le'] ?? '') === $db->query("SELECT valide_le FROM anomalies_validees WHERE atelier_id = " . $db->quote($idA))->fetchColumn(), 'anomalies : validation par l\'Admin, date de Paris et auteur rendus par l\'API (ceux de la base)');
$g = appel(['action' => 'getAnomaliesValidees'], $A)['validees'] ?? [];
verifier(count($g) === 1 && $g[0]['atelier_id'] === $idA && $g[0]['categorie'] === 'tard' && $g[0]['empreinte'] === '17:30' && $g[0]['par'] === 'Conseiller Test' && str_starts_with($g[0]['valide_le'], date('Y-m-d')), 'anomalies : lue avec empreinte, auteur et date du jour (heure de Paris)');
appel(['action' => 'validerAnomalie'], $A + ['_id' => $idA, 'categorie' => 'tard', 'empreinte' => '18:00']);
$g = appel(['action' => 'getAnomaliesValidees'], $A)['validees'] ?? [];
verifier(count($g) === 1 && $g[0]['empreinte'] === '18:00', 'anomalies : revalider remplace l\'empreinte (une seule ligne par atelier et catégorie)');
$jv = $db->query("SELECT COUNT(*) FROM journal WHERE action = 'validerAnomalie' AND ref = " . $db->quote($idA))->fetchColumn();
$jl = $db->query("SELECT COUNT(*) FROM journal WHERE action = 'getAnomaliesValidees'")->fetchColumn();
verifier((int) $jv === 2 && (int) $jl === 0, 'anomalies : les validations sont journalisées au nom de l\'Admin, la lecture ne l\'est pas');
// Concurrence réelle : cinq catégories validées en même temps sur le même atelier, deux fois la même clé.
$par = appels_paralleles(array_merge(
    array_map(fn($c) => [['action' => 'validerAnomalie'], $A + ['_id' => $idA, 'categorie' => $c, 'empreinte' => "v-$c"]], ['manquants', 'communes', 'chiffres', 'ordi', 'tard']),
    [[['action' => 'validerAnomalie'], $A + ['_id' => $idA, 'categorie' => 'tard', 'empreinte' => 'v-tard-bis']]]
));
verifier(count(array_filter($par, fn($r) => ($r['ok'] ?? false) === true)) === 6 && (int) $db->query("SELECT COUNT(*) FROM anomalies_validees WHERE atelier_id = " . $db->quote($idA))->fetchColumn() === 5, 'anomalies : six validations simultanées, aucune perdue, une seule ligne par catégorie');
verifier(appel(['action' => 'annulerAnomalie'], $A + ['_id' => $idA, 'categorie' => 'tard'])['ok'] === true && (int) $db->query("SELECT COUNT(*) FROM anomalies_validees WHERE atelier_id = " . $db->quote($idA))->fetchColumn() === 4, 'anomalies : annuler la validation ne retire que cette catégorie');
$db->exec("DELETE FROM anomalies_validees");

$db->exec("DELETE FROM ateliers WHERE id LIKE 'ts\\_%'");

array_map('unlink', glob("$dossierMails/*.txt")); @rmdir($dossierMails);

$db->exec("UPDATE sessions SET expire = NOW() - INTERVAL 1 SECOND");
verifier(appel(['action' => 'getAll'], ['token' => $admin['token']])['auth'] ?? false, 'jeton expiré : refusé');
verifier(appel(['action' => 'inventee'], $A)['error'] === 'action inconnue: inventee', 'action inconnue');

echo $echecs ? "\n$echecs échec(s)\n" : "\nTous les tests passent.\n";
exit($echecs ? 1 : 0);
