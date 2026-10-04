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
    ['ATELIERS_API_CONFIG' => $cfg, 'ATELIERS_MAIL_TEST_DIR' => $dossierMails, 'ATELIERS_SAUVEGARDE_DIR' => $dossierSauv] + getenv());
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
verifier(array_keys($r) === ['ok', 'entries', 'lists', 'visibility', 'conseiller_colors', 'stockOrdinateurs', 'materielsCaches', 'conseillers_inactifs', 'tickets'], 'clés de la réponse conseiller = GAS NEWGEN sans emails : ' . implode(',', array_keys($r)));
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
appel(['action' => 'saveEntry'], $T + ['entry' => json_encode($maj + ['bilan' => $bilan])]);
appel(['action' => 'saveEntry'], $T + ['entry' => json_encode($maj)]);
verifier($lire('entry_1')['bilan'] === $bilan, 'bilan enregistré tel quel, gardé par un envoi sans bilan (client en cache)');
$r = appel(['action' => 'saveEntry'], $T + ['entry' => json_encode($maj + ['bilan' => ['objectif' => 'Peut-être']])]);
verifier($r['ok'] === false && str_contains($r['error'], 'bilan') && $lire('entry_1')['bilan'] === $bilan, 'bilan : valeur hors liste refusée');
appel(['action' => 'saveEntry'], $T + ['entry' => json_encode($maj + ['bilan' => ['difficultes' => ['Autre'], 'difficultes_autre' => 'Coupure de courant']])]);
$b1 = $lire('entry_1')['bilan'];
appel(['action' => 'saveEntry'], $T + ['entry' => json_encode($maj + ['bilan' => ['difficultes' => ['Connexion'], 'difficultes_autre' => 'oublié']])]);
verifier(($b1['difficultes_autre'] ?? '') === 'Coupure de courant' && !isset($lire('entry_1')['bilan']['difficultes_autre']), 'précision « Autre » gardée seulement si « Autre » est coché');
appel(['action' => 'saveEntry'], $T + ['entry' => json_encode($maj + ['bilan' => ''])]);
verifier($lire('entry_1')['bilan'] === '', 'bilan vidé');
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
verifier(appel(['action' => 'getVisibility'], $A)['visibility'] === ['saisie' => true, 'carte' => false], 'saveVisibility relu');
appel(['action' => 'saveVisibility'], $A + ['visibility' => json_encode(['saisie' => true, 'carte' => false, 'corbeille' => true])]);
verifier((appel(['action' => 'getCorbeille'], $T)['ok'] ?? false) === true, 'corbeille rendue visible sur Index : ouverte aux conseillers');
verifier(str_contains(appel(['action' => 'saveVisibility'], $T + ['visibility' => '{}'])['error'] ?? '', 'administrateurs'), 'corbeille ouverte : les autres actions admin restent fermées');
appel(['action' => 'saveVisibility'], $A + ['visibility' => json_encode(['saisie' => true, 'carte' => false])]);
verifier(str_contains(appel(['action' => 'restaurerCorbeille'], $T + ['_id' => 'x'])['error'] ?? '', 'administrateurs'), 'corbeille masquée sur Index : refermée aux conseillers');
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
verifier(appel(['action' => 'setPassword'], $A + ['conseiller' => 'Nouvelle Recrue', 'password' => 'Autre-Mot-De-Passe-8'])['ok'] === false, 'setPassword sans mot de passe actuel : refusé');
verifier(appel(['action' => 'setPassword'], $A + ['conseiller' => 'Nouvelle Recrue', 'currentPwd' => 'Pas-Le-Bon-1', 'password' => 'Autre-Mot-De-Passe-8'])['error'] === 'Mot de passe actuel incorrect', 'setPassword : mot de passe actuel faux refusé');
verifier(appel(['action' => 'setPassword', 'currentPwd' => 'Un-Mot-De-Passe-7'], $A + ['conseiller' => 'Nouvelle Recrue', 'password' => 'Autre-Mot-De-Passe-8'])['ok'] === false, 'setPassword : mot de passe actuel dans l\'URL ignoré');
verifier(appel(['action' => 'setPassword'], $A + ['conseiller' => 'Nouvelle Recrue', 'currentPwd' => 'Un-Mot-De-Passe-7', 'password' => 'Autre-Mot-De-Passe-8'])['ok'] === true, 'setPassword avec mot de passe actuel');
$db->exec("DELETE FROM tentatives");
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

echo "API — avis des stagiaires (AG-021)\n";
$auj = date('Y-m-d');
appel(['action' => 'saveEntry'], $T + ['entry' => json_encode(['_id' => 'av_1', 'date' => $auj, 'horaire' => '10:00', 'thematique' => 'Smartphone', 'conseiller' => 'Conseiller Test', 'commune' => 'Nérac'])]);
appel(['action' => 'saveEntry'], $T + ['entry' => json_encode(['_id' => 'av_vieux', 'date' => date('Y-m-d', strtotime('-40 days')), 'thematique' => 'X', 'conseiller' => 'Conseiller Test'])]);
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
$jv = appel(['action' => 'jetonAvis'], $T + ['_id' => 'av_vieux']);
verifier(!(appel(['action' => 'avisPublic'], ['a' => $jv['jeton']])['ok'] ?? true), 'atelier passé depuis plus de 30 jours : questionnaire fermé');
$col = $db->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'avis' AND column_name IN ('ip', 'adresse_ip', 'nom', 'prenom', 'age', 'tranche_age', 'contact', 'recontact', 'email', 'telephone')")->fetchColumn();
verifier((int) $col === 0, '[RGPD-20] avis anonymes : aucune colonne IP, nom, âge ou contact');
$res = appel(['action' => 'jetonAvis'], $T + ['_id' => 'av_1'])['avis'];
verifier($res['n'] === 1 && $res['attentes'] == 5 && $res['remarques'] === ['Merci'], 'résumé des avis pour la fiche : ' . json_encode($res));
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
preg_match('/[?&]reinit=([0-9a-f]{64})/', $m[0] ?? '', $mm);
$jetonReinit = $mm[1] ?? '';
verifier($jetonReinit !== '' && str_contains($m[0], $RETOUR . '&reinit='), 'lien vers la page de départ, ?backend=php conservé');
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
array_map('unlink', glob("$dossierMails/*.txt")); @rmdir($dossierMails);

$db->exec("UPDATE sessions SET expire = NOW() - INTERVAL 1 SECOND");
verifier(appel(['action' => 'getAll'], ['token' => $admin['token']])['auth'] ?? false, 'jeton expiré : refusé');
verifier(appel(['action' => 'inventee'], $A)['error'] === 'action inconnue: inventee', 'action inconnue');

echo $echecs ? "\n$echecs échec(s)\n" : "\nTous les tests passent.\n";
exit($echecs ? 1 : 0);
