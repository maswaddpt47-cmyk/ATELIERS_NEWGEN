<?php
// Tests de la copie de nuit (api/lib/sauvegarde.php).
//
//   ATELIERS_TEST_MYSQL=root@127.0.0.1 ATELIERS_TEST_MYSQL_MDP=… php api-tests/sauvegarde.test.php
//
// Le script est lancé comme la tâche planifiée le lancera : en ligne de
// commande, sur une base jetable, avec un dossier de sauvegarde temporaire.

require __DIR__ . '/outils.php';

$mysql = getenv('ATELIERS_TEST_MYSQL');
if (!$mysql) { echo "(sauvegarde non testée : ATELIERS_TEST_MYSQL non défini)\n"; exit(0); }
[$util, $hote] = explode('@', $mysql) + [1 => '127.0.0.1'];
$mdp = (string) getenv('ATELIERS_TEST_MYSQL_MDP');

$db = new PDO("mysql:host=$hote;charset=utf8mb4", $util, $mdp, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db->exec('DROP DATABASE IF EXISTS ateliers_test_sauv');
$db->exec('CREATE DATABASE ateliers_test_sauv CHARACTER SET utf8mb4');
$db->exec("CREATE TABLE ateliers_test_sauv.ateliers (id VARCHAR(40) PRIMARY KEY, commune VARCHAR(100))");
$db->exec("INSERT INTO ateliers_test_sauv.ateliers VALUES ('entry_1', 'Nérac')");

$tmp = sys_get_temp_dir() . '/sauv-test-' . getmypid();
@mkdir($tmp);
$dossier = "$tmp/sauvegardes";
$lancer = function (string $motDePasse) use ($tmp, $hote, $util, $dossier): array {
    $cfg = "$tmp/config.php";
    file_put_contents($cfg, '<?php return ' . var_export(['db_hote' => $hote, 'db_nom' => 'ateliers_test_sauv', 'db_utilisateur' => $util, 'db_mot_de_passe' => $motDePasse], true) . ';');
    $env = 'ATELIERS_API_CONFIG=' . escapeshellarg($cfg) . ' ATELIERS_SAUVEGARDE_DIR=' . escapeshellarg($dossier);
    exec("$env php " . escapeshellarg(__DIR__ . '/../api/lib/sauvegarde.php') . ' 2>&1', $sortie, $code);
    return [$code, implode("\n", $sortie)];
};

echo "Sauvegarde\n";
@mkdir($dossier, 0700, true);
$vieille = "$dossier/ateliers-2026-01-01_030000.sql.gz";
file_put_contents($vieille, 'x');
touch($vieille, time() - 40 * 86400);
[$code, $sortie] = $lancer($mdp);
$fichiers = glob("$dossier/ateliers-*.sql.gz");
verifier($code === 0 && count($fichiers) === 1 && str_contains($sortie, '1 purgée(s)'), "copie écrite, copie de plus de 30 jours purgée : $sortie");
$sql = (string) shell_exec('gzip -dc ' . escapeshellarg($fichiers[0]));
verifier(str_contains($sql, "'entry_1','Nérac'") && str_contains($sql, 'Dump completed'), 'la copie contient les données, complète');
// La vraie preuve : la copie se recharge dans une base vide.
$db->exec('DROP DATABASE IF EXISTS ateliers_test_restau');
$db->exec('CREATE DATABASE ateliers_test_restau CHARACTER SET utf8mb4');
exec('gzip -dc ' . escapeshellarg($fichiers[0]) . ' | mysql -h ' . escapeshellarg($hote) . ' -u ' . escapeshellarg($util) . ' --password=' . escapeshellarg($mdp) . ' ateliers_test_restau 2>&1', $o, $cr);
verifier($cr === 0 && $db->query('SELECT commune FROM ateliers_test_restau.ateliers')->fetchColumn() === 'Nérac', 'restauration dans une base vide : données retrouvées');
$db->exec('DROP DATABASE ateliers_test_restau');
verifier((fileperms($fichiers[0]) & 0777) === 0600, 'copie lisible par le seul compte (600)');
verifier(!str_contains($sortie, 'Nérac') && !str_contains($sortie, $mdp === '' ? "\0" : $mdp), 'compte rendu sans donnée ni mot de passe');

[$code, $sortie] = $lancer('mauvais-mot-de-passe');
verifier($code === 1 && str_contains($sortie, 'ÉCHEC') && count(glob("$dossier/ateliers-*")) === 1, 'échec signalé, aucun fichier partiel laissé');

$db->exec('DROP DATABASE ateliers_test_sauv');
exec('rm -rf ' . escapeshellarg($tmp));

echo $echecs ? "\n$echecs échec(s)\n" : "\nTous les tests passent.\n";
exit($echecs ? 1 : 0);
