<?php
// Outils communs aux tests de l'API : assertions, fabrication d'un classeur
// .xlsx de test sur le modèle de l'export Google Sheets réel (jamais le
// vrai fichier, qui contient des données personnelles).

date_default_timezone_set('Europe/Paris');
require_once __DIR__ . '/../api/lib/import.php';

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
