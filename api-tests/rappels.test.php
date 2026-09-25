<?php
// Tests des rappels d'ateliers en retard (api/lib/rappels.php).
//
//   ATELIERS_TEST_MYSQL=root@127.0.0.1 ATELIERS_TEST_MYSQL_MDP=… php api-tests/rappels.test.php
//
// Le script est lancé comme la tâche planifiée le lancera, sur une base
// jetable ; les mails sont écrits dans un dossier au lieu d'être envoyés.

require __DIR__ . '/outils.php';

$mysql = getenv('ATELIERS_TEST_MYSQL');
if (!$mysql) { echo "(rappels non testés : ATELIERS_TEST_MYSQL non défini)\n"; exit(0); }
[$util, $hote] = explode('@', $mysql) + [1 => '127.0.0.1'];
$mdp = (string) getenv('ATELIERS_TEST_MYSQL_MDP');

$db = new PDO("mysql:host=$hote;charset=utf8mb4", $util, $mdp, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db->exec('DROP DATABASE IF EXISTS ateliers_test_rappels');
$db->exec('CREATE DATABASE ateliers_test_rappels CHARACTER SET utf8mb4');
$db->exec('USE ateliers_test_rappels');
foreach (import_requetes_schema() as $sql) $db->exec($sql);

$hier = date('Y-m-d', strtotime('-1 day'));
$demain = date('Y-m-d', strtotime('+1 day'));
$ins = $db->prepare("INSERT INTO ateliers (id, statut, date, horaire, thematique, conseiller, commune, orienteur, lieu, co_animateur, residence, remarques)
                     VALUES (?, ?, ?, '09:00', ?, ?, 'Nérac', '', '', '', '', '')");
$ins->execute(['a1', 'Planifié', $hier, 'Mails <b>gras</b>', 'Alice']);   // en retard
$ins->execute(['a2', 'Réalisé', $hier, 'Déjà fait', 'Alice']);            // réalisé : non
$ins->execute(['a3', 'Planifié', $demain, 'À venir', 'Alice']);           // futur : non
$ins->execute(['b1', 'Planifié', $hier, 'Tablette', 'Bruno']);            // rappel coupé
$ins->execute(['c1', 'Planifié', $hier, 'Smartphone', 'Chloé']);          // sans adresse
$cfg = $db->prepare('INSERT INTO config (cle, valeur) VALUES (?, ?)');
$cfg->execute(['emails', json_encode(['Alice' => 'alice@example.org', 'Bruno' => 'bruno@example.org'])]);
$cfg->execute(['rappels_actifs', json_encode(['Bruno' => false])]);

$tmp = sys_get_temp_dir() . '/rappels-test-' . getmypid();
@mkdir("$tmp/mails", 0700, true);
file_put_contents("$tmp/config.php", '<?php return ' . var_export(['db_hote' => $hote, 'db_nom' => 'ateliers_test_rappels', 'db_utilisateur' => $util, 'db_mot_de_passe' => $mdp], true) . ';');
$lancer = function (string $args = '') use ($tmp): array {
    $env = 'ATELIERS_API_CONFIG=' . escapeshellarg("$tmp/config.php") . ' ATELIERS_MAIL_TEST_DIR=' . escapeshellarg("$tmp/mails");
    exec("$env php " . escapeshellarg(__DIR__ . '/../api/lib/rappels.php') . " $args 2>&1", $sortie, $code);
    return [$code, implode("\n", $sortie)];
};
$mails = fn() => array_map('file_get_contents', glob("$tmp/mails/*.txt") ?: []);

echo "Rappels\n";
[$code, $sortie] = $lancer();
$m = $mails();
verifier($code === 0 && count($m) === 1 && str_contains($m[0], 'A: alice@example.org'), "un seul mail, au conseiller concerné : $sortie");
verifier(str_contains($m[0] ?? '', 'Mails <b>gras</b>') && !str_contains($m[0] ?? '', 'Déjà fait') && !str_contains($m[0] ?? '', 'À venir'), 'seuls les ateliers « Planifié » à date passée');
verifier(str_contains($sortie, '1 rappel(s) coupé(s)') && str_contains($sortie, '1 conseiller(s) sans adresse'), 'interrupteur de rappel individuel respecté, absence d\'adresse comptée');
verifier(!preg_match('/Alice|Bruno|Chloé|example\.org/', $sortie), 'compte rendu sans nom ni adresse');
verifier((int) $db->query("SELECT COUNT(*) FROM journal WHERE action = 'alertesRetard' AND conseiller = 'Alice'")->fetchColumn() === 1, 'envoi journalisé');

array_map('unlink', glob("$tmp/mails/*.txt"));
[$code, $sortie] = $lancer('--test=moi@example.org');
$m = $mails();
verifier($code === 0 && count($m) === 1 && str_contains($m[0], 'A: moi@example.org') && str_contains($m[0], '[TEST]'), 'mode test : un seul mail, à l\'adresse donnée');

$db->exec("REPLACE INTO config (cle, valeur) VALUES ('rappels_actifs', 'false')");
array_map('unlink', glob("$tmp/mails/*.txt"));
[$code, $sortie] = $lancer();
verifier($code === 0 && $mails() === [] && str_contains($sortie, 'désactivés'), 'réglage général « false » : aucun mail');

$db->exec('DROP DATABASE ateliers_test_rappels');
exec('rm -rf ' . escapeshellarg($tmp));

echo $echecs ? "\n$echecs échec(s)\n" : "\nTous les tests passent.\n";
exit($echecs ? 1 : 0);
