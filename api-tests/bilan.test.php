<?php
// Tests du bilan mensuel (api/lib/bilan.php).
//
//   ATELIERS_TEST_MYSQL=root@127.0.0.1 ATELIERS_TEST_MYSQL_MDP=… php api-tests/bilan.test.php
//
// Le script est lancé comme la tâche planifiée le lancera, sur une base
// jetable ; les mails sont écrits dans un dossier au lieu d'être envoyés.

require __DIR__ . '/outils.php';

$mysql = getenv('ATELIERS_TEST_MYSQL');
if (!$mysql) { echo "(bilan non testé : ATELIERS_TEST_MYSQL non défini)\n"; exit(0); }
[$util, $hote] = explode('@', $mysql) + [1 => '127.0.0.1'];
$mdp = (string) getenv('ATELIERS_TEST_MYSQL_MDP');

$db = new PDO("mysql:host=$hote;charset=utf8mb4", $util, $mdp, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db->exec('DROP DATABASE IF EXISTS ateliers_test_bilan');
$db->exec('CREATE DATABASE ateliers_test_bilan CHARACTER SET utf8mb4');
$db->exec('USE ateliers_test_bilan');
foreach (import_requetes_schema() as $sql) $db->exec($sql);

$ins = $db->prepare("INSERT INTO ateliers (id, statut, date, horaire, thematique, conseiller, commune, orienteur, lieu, co_animateur, residence, remarques, inscrits, presents)
                     VALUES (?, ?, ?, '09:00', 'T', ?, 'Nérac', ?, '', '', '', '', ?, ?)");
$ins->execute(['s1', 'Réalisé', '2026-09-03', 'Alice', 'CAF', 6, 5]);
$ins->execute(['s2', 'Réalisé', '2026-09-10', 'Bruno', 'CAF', 4, 3]);
$ins->execute(['s3', 'Annulé', '2026-09-12', 'Alice', 'MSA <b>', 4, null]);
$ins->execute(['s4', 'Planifié', '2026-09-20', 'Bruno', 'MSA <b>', 4, null]);   // date passée : en retard
$ins->execute(['a1', 'Réalisé', '2026-08-05', 'Alice', 'CAF', 5, 2]);           // mois précédent
$ins->execute(['o1', 'Planifié', '2026-10-06', 'Alice', 'CAF', 4, null]);        // mois suivant
$cpt = $db->prepare("INSERT INTO comptes (conseiller, hash, role, actif, doit_changer) VALUES (?, NULL, ?, ?, 0)");
$cpt->execute(['Sophie', 'superviseur', 1]);
$cpt->execute(['Ancienne', 'superviseur', 0]);
$cpt->execute(['Alice', 'user', 1]);
$db->prepare('INSERT INTO config (cle, valeur) VALUES (?, ?)')->execute(['emails', json_encode([
    'Sophie' => 'sophie@example.org', 'Ancienne' => 'ancienne@example.org', 'Alice' => 'alice@example.org'])]);

$tmp = sys_get_temp_dir() . '/bilan-test-' . getmypid();
@mkdir("$tmp/mails", 0700, true);
file_put_contents("$tmp/config.php", '<?php return ' . var_export(['db_hote' => $hote, 'db_nom' => 'ateliers_test_bilan', 'db_utilisateur' => $util, 'db_mot_de_passe' => $mdp], true) . ';');
$lancer = function (string $args = '') use ($tmp): array {
    $env = 'ATELIERS_API_CONFIG=' . escapeshellarg("$tmp/config.php") . ' ATELIERS_MAIL_TEST_DIR=' . escapeshellarg("$tmp/mails");
    exec("$env php " . escapeshellarg(__DIR__ . '/../api/lib/bilan.php') . " $args 2>&1", $sortie, $code);
    return [$code, implode("\n", $sortie)];
};
$mails = fn() => array_map('file_get_contents', glob("$tmp/mails/*.txt") ?: []);

echo "Bilan mensuel\n";
[$code, $sortie] = $lancer('--mois=2026-09');
$m = $mails();
verifier($code === 0 && count($m) === 1 && str_contains($m[0], 'A: sophie@example.org'), "un seul mail, à la superviseure active : $sortie");
$t = $m[0] ?? '';
verifier(str_contains($t, 'Ateliers au total : 4 (août : 1, +3)') && str_contains($t, 'Réalisés : 2 (août : 1, +1)'), 'chiffres du mois comparés au mois précédent');
verifier(str_contains($t, 'Inscrits (ateliers réalisés) : 10') && str_contains($t, 'Présents (ateliers réalisés) : 8') && str_contains($t, 'Taux de présence : 80 % (août : 40 %)'), 'inscrits et présents des seuls ateliers réalisés, taux');
verifier(str_contains($t, '- Alice : 1 réalisé(s) sur 2, 5 présent(s) / 6 inscrit(s)') && str_contains($t, '- CAF : 2 réalisé(s) sur 2'), 'par conseiller et par partenaire');
verifier(str_contains($t, '1 atelier(s) de septembre 2026 encore « Planifié »') && str_contains($t, '1 atelier(s) planifié(s) en octobre'), 'points d\'attention : retards, mois suivant');
verifier(!preg_match('/Alice|Sophie|example\.org/', $sortie), 'compte rendu sans nom ni adresse');
verifier((int) $db->query("SELECT COUNT(*) FROM journal WHERE action = 'bilanMensuel' AND ref = '2026-09'")->fetchColumn() === 1, 'envoi journalisé');

array_map('unlink', glob("$tmp/mails/*.txt"));
[$code, $sortie] = $lancer('--mois=2026-09');
verifier($code === 0 && $mails() === [] && str_contains($sortie, '1 déjà servi(s)'), 'relancé le même mois : aucun second envoi');
[$code, $sortie] = $lancer('--mois=2026-09 --moi@example.org');
verifier($code === 1 && $mails() === [], 'option inconnue (« test= » oublié) : arrêt, aucun envoi');

[$code, $sortie] = $lancer('--mois=2026-09 --test=moi@example.org');
$m = $mails();
verifier($code === 0 && count($m) === 1 && str_contains($m[0], 'A: moi@example.org') && str_contains($m[0], '[TEST]'), 'mode test : un seul mail, à l\'adresse donnée');
[$code] = $lancer('--mois=2026-13');
verifier($code === 1, 'mois invalide : refusé');

$db->exec('DROP DATABASE ateliers_test_bilan');
exec('rm -rf ' . escapeshellarg($tmp));

echo $echecs ? "\n$echecs échec(s)\n" : "\nTous les tests passent.\n";
exit($echecs ? 1 : 0);
