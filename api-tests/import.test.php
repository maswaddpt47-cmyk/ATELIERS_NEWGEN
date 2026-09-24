<?php
// Tests de l'import du classeur (api/lib/import.php, api/lib/xlsx.php).
//
//   php api-tests/import.test.php
//
// Le classeur de test est fabriqué ici, sur le modèle de l'export Google
// Sheets réel du 23/09/2026 (mêmes formats de date, Config sans en-tête,
// journal au format récent) — jamais le vrai fichier, qui contient des
// données personnelles et ne doit pas entrer dans le dépôt.
// Si la variable ATELIERS_TEST_MYSQL est définie (ex. « root@127.0.0.1 »),
// le chargement en base est testé aussi, dans une base jetable.

date_default_timezone_set('Europe/Paris');
require __DIR__ . '/outils.php';

echo "Import — classeur conforme\n";
$a = analyser(classeur([atelier('entry_1'), atelier('entry_2', ['_n' => null, 'horaire' => '9h05', 'Ordinateur' => null]), [null, 3]]));
verifier($a['erreurs'] === [], 'aucune erreur : ' . implode(' | ', $a['erreurs']));
verifier($a['decomptes'] === ['ateliers' => 2, 'materiel' => 3, 'config' => 3, 'comptes' => 2, 'journal' => 2], 'décomptes : ' . json_encode($a['decomptes']));
$e = $a['donnees']['ateliers'][0];
verifier($e['date'] === '2026-09-24' && $e['horaire'] === '13:30', 'date et heure Sheets converties (2026-09-24 13:30)');
verifier($e['date_prelevement_materiel'] === '2026-09-23' && $e['date_retour_materiel'] === '2026-09-25', 'dates de prêt converties');
verifier($e['presents'] === null && $e['inscrits'] === 6, 'presents vide reste vide (≠ 0), inscrits entier');
verifier($a['donnees']['ateliers'][1]['horaire'] === '09:05', 'heure saisie en texte « 9h05 » normalisée');
verifier(in_array(['atelier_id' => 'entry_1', 'materiel' => 'Classe mobile'], $a['donnees']['materiel'], true), 'matériel OUI → ligne ateliers_materiel');
$cfg = array_column($a['donnees']['config'], 'valeur', 'cle');
verifier(($cfg['materiels_caches'] ?? null) === '["Tablette"]' && !isset($cfg['materiels_masques']), 'materiels_masques renommée materiels_caches');
verifier(($cfg['stock_ordinateurs'] ?? null) === '14', 'nombre de Config gardé sans « .0 »');
verifier(!isset($cfg['admin_password']) && !isset($cfg['app_version']) && !isset($cfg['lock_conseiller_test']), 'clés mortes non importées (dont admin_password)');
verifier((bool) preg_grep('/admin_password.*en clair/', $a['avertissements']), 'admin_password signalée comme mot de passe en clair');
[$c1, $c2] = $a['donnees']['comptes'];
verifier(password_verify(hash('sha256', 'secret-test'), $c1['hash']) && $c1['doit_changer'] === 0, 'empreinte SHA-256 reprise : le mot de passe d\'origine reste valable');
verifier(password_verify(hash('sha256', 'cd47nouveau'), $c2['hash']) && $c2['doit_changer'] === 1 && !str_contains($c2['hash'], 'cd47'), 'mot de passe en clair : haché, changement forcé');
[$j1, $j2] = $a['donnees']['journal'];
verifier($j1['horodatage'] === '2026-09-24 10:30:00' && $j1['action'] === 'login' && $j1['succes'] === 1 && $j1['source'] === 'index.html', 'journal format récent');
verifier($j2['action'] === 'checkPassword' && $j2['conseiller'] === 'Conseiller Test' && $j2['succes'] === 1, 'journal ancien format unifié');

echo "Import — refus\n";
$a = analyser(classeur([array_merge(atelier('entry_1'), ['intrus'])], ['Ateliers_next_step' => [array_merge(ENTETE_ATELIERS, ['Colonne ajoutée']), atelier('entry_1')]]));
verifier((bool) preg_grep('/colonne « Colonne ajoutée » inconnue/', $a['erreurs']), 'colonne inconnue refusée');
$a = analyser(classeur([atelier('entry_1', ['date' => '12/03/2026']), atelier('entry_2', ['inscrits' => 'six']), atelier('entry_1'), atelier('entry_3', ['Scanner' => 'X'])]));
verifier((bool) preg_grep('/ligne 2, colonne date/', $a['erreurs']), 'date illisible refusée avec son numéro de ligne');
verifier((bool) preg_grep('/ligne 3, colonne inscrits/', $a['erreurs']), 'nombre illisible refusé');
verifier((bool) preg_grep('/_id « entry_1 » déjà vu ligne 2/', $a['erreurs']), '_id en double refusé');
verifier((bool) preg_grep('/colonne Scanner : « X »/', $a['erreurs']), 'matériel ni OUI ni vide refusé');
$sans = classeur([atelier('entry_1')]);
unset($sans['Comptes']);
verifier((bool) preg_grep('/Feuille « Comptes » absente/', analyser($sans)['erreurs']), 'feuille manquante refusée');
$a = analyser(classeur([atelier('entry_1')], ['Config' => [['cle_inventee', 'x']]]));
verifier((bool) preg_grep('/clé « cle_inventee » inconnue/', $a['erreurs']), 'clé de Config inconnue refusée');

// ── Chargement en base (facultatif) ───────────────────────────────────────
$mysql = getenv('ATELIERS_TEST_MYSQL');
if ($mysql) {
    echo "Import — chargement en base ($mysql)\n";
    [$util, $hote] = explode('@', $mysql) + [1 => '127.0.0.1'];
    $mdp = (string) getenv('ATELIERS_TEST_MYSQL_MDP');
    $db = new PDO("mysql:host=$hote;charset=utf8mb4", $util, $mdp, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $db->exec('DROP DATABASE IF EXISTS ateliers_test_import');
    $db->exec('CREATE DATABASE ateliers_test_import CHARACTER SET utf8mb4');
    $db->exec('USE ateliers_test_import');
    $a = analyser(classeur([atelier('entry_1'), atelier('entry_2', ['presents' => 0])]));
    import_charger($db, $a, 'empreinte');
    import_charger($db, $a, 'empreinte'); // rejouable : pas de doublon
    verifier((int) $db->query('SELECT COUNT(*) FROM ateliers')->fetchColumn() === 2, 'import rejoué : toujours 2 ateliers');
    $l = $db->query("SELECT date, horaire, presents FROM ateliers WHERE id = 'entry_1'")->fetch(PDO::FETCH_ASSOC);
    verifier($l === ['date' => '2026-09-24', 'horaire' => '13:30', 'presents' => null], 'relu en base : ' . json_encode($l));
    verifier($db->query("SELECT presents FROM ateliers WHERE id = 'entry_2'")->fetchColumn() === 0, 'presents 0 distinct de vide en base');
    verifier((int) $db->query('SELECT COUNT(*) FROM ateliers_materiel')->fetchColumn() === 4, 'matériel en base');
    $db->exec("REPLACE INTO meta VALUES ('import_verrouille', '1')");
    try { import_charger($db, $a, 'x'); $refuse = false; } catch (RuntimeException $e) { $refuse = str_contains($e->getMessage(), 'verrouillé'); }
    verifier($refuse, 'import refusé après la bascule (verrou)');
    $mauvais = analyser(classeur([atelier('entry_9', ['date' => 'demain'])]));
    try { import_charger($db, $mauvais, 'x'); $refuse = false; } catch (RuntimeException $e) { $refuse = true; }
    verifier($refuse && (int) $db->query('SELECT COUNT(*) FROM ateliers')->fetchColumn() === 2, 'analyse en erreur : rien n\'est écrit');
    $db->exec('DROP DATABASE ateliers_test_import');
} else {
    echo "(chargement en base non testé : ATELIERS_TEST_MYSQL non défini)\n";
}

echo $echecs ? "\n$echecs échec(s)\n" : "\nTous les tests passent.\n";
exit($echecs ? 1 : 0);
