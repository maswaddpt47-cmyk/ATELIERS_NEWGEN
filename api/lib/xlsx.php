<?php
// Lecture d'un fichier .xlsx sans bibliothèque externe.
//
// Un .xlsx est une archive zip de fichiers XML : on l'ouvre avec ZipArchive
// et on lit les feuilles avec SimpleXML (deux extensions standard de PHP).
// Seul ce dont l'import a besoin est lu : valeurs des cellules, textes
// partagés, et formats de nombre pour reconnaître les dates et les heures.
//
// Format produit par Google Sheets (Fichier → Télécharger → Excel), relevé
// sur l'export réel du 23/09/2026 : dates au format « yyyy-mm-dd », heures
// « hh:mm », horodatages « m/d/yyyy h:mm:ss », tous stockés en nombre de
// jours depuis le 30/12/1899 (convention Excel).

// Valeur de cellule au format date ou heure : on garde le nombre brut et on
// laisse l'appelant décider s'il attend une date, une heure ou les deux.
final class XlsxDate
{
    public function __construct(public readonly float $serie) {}

    // Jour calendaire 'Y-m-d'.
    public function jour(): string
    {
        return self::base()->modify('+' . (int) floor($this->serie) . ' days')->format('Y-m-d');
    }

    // Heure 'H:i' (partie décimale du nombre), arrondie à la minute.
    public function heure(): string
    {
        $minutes = (int) round(($this->serie - floor($this->serie)) * 1440);
        if ($minutes === 1440) $minutes = 0;
        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }

    // Date et heure 'Y-m-d H:i:s', à la seconde.
    public function dateHeure(): string
    {
        $secondes = (int) round($this->serie * 86400);
        return self::base()->modify('+' . $secondes . ' seconds')->format('Y-m-d H:i:s');
    }

    private static function base(): DateTimeImmutable
    {
        return new DateTimeImmutable('1899-12-30 00:00:00', new DateTimeZone('UTC'));
    }
}

// Ouvre le fichier et renvoie ['NomFeuille' => [ [cellules ligne 1], [ligne 2], … ]].
// Chaque ligne est un tableau indexé à partir de 0 (colonne A = 0) ; une
// cellule vide vaut null. Valeurs : string, int|float, bool ou XlsxDate.
// Lève une RuntimeException si le fichier n'est pas un .xlsx lisible.
function xlsx_lire(string $chemin): array
{
    $zip = new ZipArchive();
    if ($zip->open($chemin, ZipArchive::RDONLY) !== true) {
        throw new RuntimeException("Fichier illisible : ce n'est pas un .xlsx.");
    }
    try {
        $classeur = xlsx_xml($zip, 'xl/workbook.xml');
        $liens    = xlsx_xml($zip, 'xl/_rels/workbook.xml.rels');
        $textes   = xlsx_textes_partages($zip);
        $formatsDate = xlsx_styles_date($zip);

        // En 1904 (vieux fichiers Mac), le jour 0 n'est pas le même.
        $decalage1904 = 0;
        $pr = $classeur->workbookPr ?? null;
        if ($pr !== null && in_array((string) ($pr['date1904'] ?? ''), ['1', 'true'], true)) {
            $decalage1904 = 1462;
        }

        $cibles = [];
        foreach ($liens->Relationship as $r) {
            $cibles[(string) $r['Id']] = (string) $r['Target'];
        }

        $feuilles = [];
        foreach ($classeur->sheets->sheet as $f) {
            $rid = (string) $f->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
            $cible = $cibles[$rid] ?? null;
            if ($cible === null) continue;
            $cible = ltrim($cible, '/');
            if (!str_starts_with($cible, 'xl/')) $cible = 'xl/' . $cible;
            $feuilles[(string) $f['name']] = xlsx_feuille(xlsx_xml($zip, $cible), $textes, $formatsDate, $decalage1904);
        }
        return $feuilles;
    } finally {
        $zip->close();
    }
}

function xlsx_xml(ZipArchive $zip, string $nom): SimpleXMLElement
{
    $contenu = $zip->getFromName($nom);
    if ($contenu === false) throw new RuntimeException("Fichier .xlsx incomplet ($nom absent).");
    // LIBXML_NONET : jamais de téléchargement déclenché par le fichier reçu.
    $xml = simplexml_load_string($contenu, SimpleXMLElement::class, LIBXML_NONET | LIBXML_COMPACT);
    if ($xml === false) throw new RuntimeException("Fichier .xlsx abîmé ($nom illisible).");
    return $xml;
}

// Les textes des cellules sont rangés une fois dans sharedStrings.xml, les
// cellules n'en portent que le numéro.
function xlsx_textes_partages(ZipArchive $zip): array
{
    if ($zip->locateName('xl/sharedStrings.xml') === false) return [];
    $textes = [];
    foreach (xlsx_xml($zip, 'xl/sharedStrings.xml')->si as $si) {
        $textes[] = xlsx_texte_riche($si);
    }
    return $textes;
}

// Un texte peut être simple (<t>) ou découpé en morceaux mis en forme (<r><t>).
function xlsx_texte_riche(SimpleXMLElement $el): string
{
    if (isset($el->t)) return (string) $el->t;
    $s = '';
    foreach ($el->r as $r) $s .= (string) $r->t;
    return $s;
}

// Renvoie, pour chaque style de cellule (index s="…"), vrai si son format de
// nombre affiche une date ou une heure.
function xlsx_styles_date(ZipArchive $zip): array
{
    if ($zip->locateName('xl/styles.xml') === false) return [];
    $styles = xlsx_xml($zip, 'xl/styles.xml');
    // Formats intégrés d'Excel qui sont des dates/heures.
    $estDate = [];
    foreach ([14, 15, 16, 17, 18, 19, 20, 21, 22, 45, 46, 47] as $id) $estDate[$id] = true;
    if (isset($styles->numFmts)) {
        foreach ($styles->numFmts->numFmt as $nf) {
            // On retire les textes entre guillemets et les [couleurs] avant de
            // chercher les lettres de date : « "Total" 0 » n'est pas une date.
            $code = preg_replace('/"[^"]*"|\[[^\]]*\]|\\\\./', '', (string) $nf['formatCode']);
            $estDate[(int) $nf['numFmtId']] = (bool) preg_match('/[dmyhs]/i', $code);
        }
    }
    $parStyle = [];
    if (isset($styles->cellXfs)) {
        foreach ($styles->cellXfs->xf as $xf) {
            $parStyle[] = $estDate[(int) $xf['numFmtId']] ?? false;
        }
    }
    return $parStyle;
}

function xlsx_feuille(SimpleXMLElement $xml, array $textes, array $formatsDate, int $decalage1904): array
{
    $lignes = [];
    if (!isset($xml->sheetData)) return $lignes;
    foreach ($xml->sheetData->row as $row) {
        $numLigne = (int) $row['r'];
        $cellules = [];
        foreach ($row->c as $c) {
            $col = xlsx_colonne((string) $c['r']);
            $cellules[$col] = xlsx_valeur($c, $textes, $formatsDate, $decalage1904);
        }
        if ($cellules) {
            $max = max(array_keys($cellules));
            $pleine = array_fill(0, $max + 1, null);
            foreach ($cellules as $i => $v) $pleine[$i] = $v;
            $cellules = $pleine;
        }
        // Les lignes absentes du fichier sont des lignes vides : on les
        // restitue pour que l'index corresponde au numéro de ligne - 1.
        while (count($lignes) < $numLigne - 1) $lignes[] = [];
        $lignes[] = $cellules;
    }
    return $lignes;
}

// « AB12 » → 27 (colonne A = 0).
function xlsx_colonne(string $ref): int
{
    preg_match('/^[A-Z]+/', $ref, $m);
    $n = 0;
    foreach (str_split($m[0] ?? 'A') as $ch) $n = $n * 26 + (ord($ch) - 64);
    return $n - 1;
}

function xlsx_valeur(SimpleXMLElement $c, array $textes, array $formatsDate, int $decalage1904): mixed
{
    $type = (string) ($c['t'] ?? 'n');
    if ($type === 'inlineStr') return isset($c->is) ? xlsx_texte_riche($c->is) : '';
    if (!isset($c->v)) return null;
    $v = (string) $c->v;
    switch ($type) {
        case 's':   return $textes[(int) $v] ?? '';
        case 'str': return $v;                       // résultat de formule texte
        case 'b':   return $v === '1';
        case 'e':   return '#ERREUR';                // cellule en erreur (#REF!…)
    }
    if ($v === '') return null;
    $nombre = (float) $v;
    $style = (int) ($c['s'] ?? 0);
    if ($formatsDate[$style] ?? false) return new XlsxDate($nombre + $decalage1904);
    return (floor($nombre) == $nombre && abs($nombre) < PHP_INT_MAX) ? (int) $nombre : $nombre;
}
