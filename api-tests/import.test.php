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
require __DIR__ . '/../api/lib/import.php';

$echecs = 0;
function verifier(bool $ok, string $quoi): void
{
    global $echecs;
    echo ($ok ? '  ok   ' : '  ÉCHEC ') . $quoi . "\n";
    if (!$ok) $echecs++;
}

// ── Fabrication d'un .xlsx minimal ────────────────────────────────────────
// Cellules : string → texte partagé ; int/float → nombre ; true/false →
// booléen ; ['d', n] → nombre au format date ; ['h', n] → format heure ;
// ['dh', n] → format date-heure.
function fabriquer_xlsx(array $feuilles): string
{
    $partages = [];
    $idx = function (string $s) use (&$partages): int {
        if (!isset($partages[$s])) $partages[$s] = count($partages);
        return $partages[$s];
    };
    $xmlFeuilles = [];
    foreach ($feuilles as $lignes) {
        $x = '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
        foreach ($lignes as $i => $ligne) {
            $x .= '<row r="' . ($i + 1) . '">';
            foreach ($ligne as $j => $v) {
                if ($v === null) continue;
                $ref = import_lettre($j) . ($i + 1);
                if (is_string($v)) $x .= "<c r=\"$ref\" t=\"s\"><v>" . $idx($v) . '</v></c>';
                elseif (is_bool($v)) $x .= "<c r=\"$ref\" t=\"b\"><v>" . ($v ? 1 : 0) . '</v></c>';
                elseif (is_array($v)) $x .= "<c r=\"$ref\" s=\"" . ['d' => 1, 'h' => 2, 'dh' => 3][$v[0]] . "\"><v>$v[1]</v></c>";
                else $x .= "<c r=\"$ref\"><v>$v</v></c>";
            }
            $x .= '</row>';
        }
        $xmlFeuilles[] = $x . '</sheetData></worksheet>';
    }
    $chemin = tempnam(sys_get_temp_dir(), 'xlsx');
    $z = new ZipArchive();
    $z->open($chemin, ZipArchive::OVERWRITE);
    $wb = '<?xml version="1.0"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><workbookPr/><sheets>';
    $rels = '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
    $n = 0;
    foreach (array_keys($feuilles) as $nom) {
        $n++;
        $wb .= '<sheet name="' . htmlspecialchars($nom) . "\" sheetId=\"$n\" r:id=\"rId$n\"/>";
        $rels .= "<Relationship Id=\"rId$n\" Target=\"worksheets/sheet$n.xml\" Type=\"http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet\"/>";
        $z->addFromString("xl/worksheets/sheet$n.xml", $xmlFeuilles[$n - 1]);
    }
    $z->addFromString('xl/workbook.xml', $wb . '</sheets></workbook>');
    $z->addFromString('xl/_rels/workbook.xml.rels', $rels . '</Relationships>');
    $ss = '<?xml version="1.0"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
    foreach (array_keys($partages) as $s) $ss .= '<si><t>' . htmlspecialchars((string) $s) . '</t></si>';
    $z->addFromString('xl/sharedStrings.xml', $ss . '</sst>');
    // Mêmes formats que l'export Sheets réel (164 à 166).
    $z->addFromString('xl/styles.xml', '<?xml version="1.0"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<numFmts><numFmt numFmtId="164" formatCode="m/d/yyyy h:mm:ss"/><numFmt numFmtId="165" formatCode="yyyy-mm-dd"/><numFmt numFmtId="166" formatCode="hh:mm"/></numFmts>'
        . '<cellXfs><xf numFmtId="0"/><xf numFmtId="165"/><xf numFmtId="166"/><xf numFmtId="164"/></cellXfs></styleSheet>');
    $z->close();
    return $chemin;
}

const ENTETE_ATELIERS = ['_id', '_n', 'statut', 'date', 'horaire', 'ampm', 'orienteur', 'commune', 'lieu', 'thematique',
    'inscrits', 'presents', 'public', 'conseiller', 'co_animateur', 'Videoprojecteur', 'Ecran', 'Classe mobile',
    'Boitier 4G', 'Tablette', 'Scanner', 'Multiprise', 'Ordinateur', 'Autre', 'residence', 'remarques',
    'nb_ordinateurs', 'date_prelevement_materiel', 'date_retour_materiel'];

// 46289 = 24/09/2026 ; 0.5625 = 13:30 ; 46289.4375 = 24/09/2026 10:30:00.
function atelier(string $id, array $modifs = []): array
{
    $l = ['_id' => $id, '_n' => 2, 'statut' => 'Planifié', 'date' => ['d', 46289], 'horaire' => ['h', 0.5625],
          'ampm' => 'PM', 'orienteur' => 'Mission locale', 'commune' => 'Agen', 'lieu' => 'Médiathèque',
          'thematique' => 'Smartphone', 'inscrits' => 6, 'presents' => null, 'public' => 'Tous publics',
          'conseiller' => 'Conseiller Test', 'Classe mobile' => 'OUI', 'Ordinateur' => 'OUI', 'nb_ordinateurs' => 4,
          'date_prelevement_materiel' => ['d', 46288], 'date_retour_materiel' => ['d', 46290]];
    $l = array_merge($l, $modifs);
    return array_map(fn($col) => $l[$col] ?? null, ENTETE_ATELIERS);
}

function classeur(array $ateliers, array $modifs = []): array
{
    return array_merge([
        'Ateliers_next_step' => array_merge([ENTETE_ATELIERS], $ateliers),
        'Comptes' => [
            ['Conseiller', 'Hash', 'Role', 'Actif', 'FailCount', 'LockUntil'],
            ['Conseiller Test', hash('sha256', 'secret-test'), 'admin', 'OUI', 0, null],
            ['Nouveau Venu', 'cd47nouveau', 'user', 'OUI', 0, null],
        ],
        // Pas de ligne d'en-tête : comme le vrai classeur.
        'Config' => [
            ['app_version', '9.0'],
            ['maintenance', '0'],
            ['materiels_masques', '["Tablette"]'],
            ['stock_ordinateurs', 14],
            ['admin_password', 'xxxxxxxx'],
            ['lock_conseiller_test', 'x'],
        ],
        'Logs_Connexion' => [
            ['timestamp', 'conseiller', 'role', 'success', 'user_agent', 'tentatives'],
            [['dh', 46289.4375], 'login', 'Conseiller Test', '', 'admin', 'Mozilla/5.0', true, 0, 'index.html'],
            [['dh', 46289.5], 'Conseiller Test', 'OK', 'OUI', 'Mozilla/5.0', 0],
        ],
        'Copie de Config' => [['x', 'y']],
    ], $modifs);
}

function analyser(array $classeur): array
{
    $f = fabriquer_xlsx($classeur);
    try { return import_analyser(xlsx_lire($f)); } finally { unlink($f); }
}

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
