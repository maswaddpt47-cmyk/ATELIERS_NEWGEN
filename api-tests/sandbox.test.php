<?php
// Tests du remplissage du bac à sable (api/lib/sandbox_seed.php, AG-019).
//
//   ATELIERS_TEST_MYSQL=root@127.0.0.1 ATELIERS_TEST_MYSQL_MDP=… php api-tests/sandbox.test.php

require __DIR__ . '/outils.php';

$mysql = getenv('ATELIERS_TEST_MYSQL');
if (!$mysql) { echo "(bac à sable non testé : ATELIERS_TEST_MYSQL non défini)\n"; exit(0); }
[$util, $hote] = explode('@', $mysql) + [1 => '127.0.0.1'];
$mdp = (string) getenv('ATELIERS_TEST_MYSQL_MDP');

$db = new PDO("mysql:host=$hote;charset=utf8mb4", $util, $mdp, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db->exec('DROP DATABASE IF EXISTS ateliers_test_sandbox');
$db->exec('CREATE DATABASE ateliers_test_sandbox CHARACTER SET utf8mb4');
$db->exec('USE ateliers_test_sandbox');

$tmp = sys_get_temp_dir() . '/sandbox-test-' . getmypid();
@mkdir($tmp, 0700, true);
$base = ['db_hote' => $hote, 'db_nom' => 'ateliers_test_sandbox', 'db_utilisateur' => $util, 'db_mot_de_passe' => $mdp];
file_put_contents("$tmp/prod.php", '<?php return ' . var_export($base, true) . ';');
file_put_contents("$tmp/sbx.php", '<?php return ' . var_export($base + ['bac_a_sable' => true, 'mdp_demo' => 'mot-de-passe-demo'], true) . ';');
$lancer = function (string $cfg): array {
    exec('ATELIERS_API_CONFIG=' . escapeshellarg($cfg) . ' php ' . escapeshellarg(__DIR__ . '/../api/lib/sandbox_seed.php') . ' 2>&1', $s, $code);
    return [$code, implode("\n", $s)];
};

echo "Bac à sable\n";
[$code] = $lancer("$tmp/prod.php");
$n = (int) $db->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'ateliers_test_sandbox'")->fetchColumn();
verifier($code === 1 && $n === 0, 'configuration de production (sans « bac_a_sable ») : refusé, rien écrit');
[$code, $sortie] = $lancer("$tmp/sbx.php");
$nb = (int) $db->query('SELECT COUNT(*) FROM ateliers')->fetchColumn();
verifier($code === 0 && $nb > 20 && (int) $db->query("SELECT COUNT(*) FROM ateliers WHERE id NOT LIKE 'demo\\_%'")->fetchColumn() === 0, "base remplie de faux ateliers : $sortie");
$db->exec("UPDATE ateliers SET remarques = 'essai' WHERE id = 'demo_1'");
[$code] = $lancer("$tmp/sbx.php");
verifier($code === 0 && $db->query("SELECT remarques FROM ateliers WHERE id = 'demo_1'")->fetchColumn() === 'essai', 'redéploiement : les essais sont gardés');

$dossier = fn(string $cfg) => trim((string) shell_exec('ATELIERS_API_CONFIG=' . escapeshellarg($cfg) . ' php -r ' . escapeshellarg('require "' . __DIR__ . '/../api/lib/copie.php"; echo sauvegarde_dossier();')));
verifier(str_ends_with($dossier("$tmp/sbx.php"), '/sauvegardes-sandbox') && str_ends_with($dossier("$tmp/prod.php"), '/sauvegardes'), 'copies du bac à sable dans leur propre dossier, jamais celui de la production');

$db->exec('DROP DATABASE ateliers_test_sandbox');
exec('rm -rf ' . escapeshellarg($tmp));
echo $echecs ? "\n$echecs échec(s)\n" : "\nTous les tests passent.\n";
exit($echecs ? 1 : 0);
