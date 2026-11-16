<?php
// Durées vides remplies à 1 h 30 (AG-062, demande de l'utilisateur du 16/11/2026).
// Lancé par .github/workflows/durees-vides.yml et envoyé par SSH sur l'entrée
// standard : rien n'est déposé sur le serveur. Dépôt public : n'affiche que des
// nombres (année, statut, compte), jamais un atelier.
//
// Une durée vide (NULL ou <= 0) est déjà comptée 1 h 30 partout (AG-032) :
// l'écrire ne change aucun total d'heures, seulement le nombre d'ateliers
// « durée par défaut » affiché par les bilans et l'onglet Anomalies.
//
//   compter   : ateliers à durée vide, par année et par statut (par défaut).
//   appliquer : dans une transaction, garde identifiant et ancienne valeur dans
//               durees_vides_20261116, met duree = 90, écrit une ligne de
//               journal, recompte (doit donner 0).
//   annuler   : remet l'ancienne valeur des lignes gardées qui valent encore 90.
//               Limite (Codex, 16/11/2026) : une durée ressaisie à 90 depuis le
//               remplissage serait aussi remise à vide ; contrôler les
//               modifications faites depuis avant toute annulation.
// Portée « <= 0 » : les négatifs, refusés par l'API, n'ont pas de voie ordinaire.
declare(strict_types=1);

$mode = $argv[1] ?? 'compter';
if (!in_array($mode, ['compter', 'appliquer', 'annuler'], true)) {
    fwrite(STDERR, "Mode inconnu : compter, appliquer ou annuler\n");
    exit(2);
}
date_default_timezone_set('Europe/Paris');
$c = require getenv('HOME') . '/config-api.php';
$db = new PDO('mysql:host=' . $c['db_hote'] . ';dbname=' . $c['db_nom'] . ';charset=utf8mb4',
    $c['db_utilisateur'], $c['db_mot_de_passe'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

const DUREE_VIDE = '(duree IS NULL OR duree <= 0)';
const TABLE_GARDE = 'durees_vides_20261116';

function compter(PDO $db): int
{
    $lignes = $db->query('SELECT YEAR(date) AS annee, statut, COUNT(*) AS n FROM ateliers WHERE ' . DUREE_VIDE
        . ' GROUP BY YEAR(date), statut ORDER BY annee, statut')->fetchAll(PDO::FETCH_ASSOC);
    $total = 0;
    foreach ($lignes as $l) {
        echo '  ', $l['annee'], ' ', $l['statut'] === '' ? '(sans statut)' : $l['statut'], ' : ', $l['n'], "\n";
        $total += (int) $l['n'];
    }
    echo 'Durées vides : ', $total, ' sur ', $db->query('SELECT COUNT(*) FROM ateliers')->fetchColumn(), " ateliers\n";
    return $total;
}

function journal(PDO $db, string $ref): void
{
    $db->prepare("INSERT INTO journal (horodatage, action, conseiller, ref, role, succes, tentatives, user_agent, source)
                  VALUES (NOW(), 'dureesVides', '', ?, '', 1, 0, '', 'maintenance')")->execute([mb_substr($ref, 0, 100)]);
}

echo "== Avant\n";
$avant = compter($db);
if ($mode === 'compter') exit(0);

if ($mode === 'appliquer') {
    // CREATE TABLE valide implicitement une transaction : hors de celle-ci.
    $db->exec('CREATE TABLE IF NOT EXISTS ' . TABLE_GARDE . ' (id VARCHAR(64) NOT NULL, ancienne SMALLINT NULL,
               rempli_le DATETIME NOT NULL, PRIMARY KEY (id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    if ($avant === 0) { echo "== Rien à remplir.\n"; exit(0); }
    $db->beginTransaction();
    $n = (int) $db->query('SELECT COUNT(*) FROM ateliers WHERE ' . DUREE_VIDE . ' FOR UPDATE')->fetchColumn();
    $db->exec('INSERT IGNORE INTO ' . TABLE_GARDE . ' (id, ancienne, rempli_le) SELECT id, duree, NOW() FROM ateliers WHERE ' . DUREE_VIDE);
    $gardees = (int) $db->query('SELECT COUNT(*) FROM ateliers a JOIN ' . TABLE_GARDE . ' g ON g.id = a.id WHERE ' . str_replace('duree', 'a.duree', DUREE_VIDE))->fetchColumn();
    $m = $db->exec('UPDATE ateliers SET duree = 90 WHERE ' . DUREE_VIDE);
    if ($m !== $n || $gardees !== $n) {
        $db->rollBack();
        echo "::error::Écart : $n à remplir, $gardees gardées, $m modifiées. Rien n'est changé.\n";
        exit(1);
    }
    journal($db, $m . ' atelier(s) : durée vide remplie à 90 min (AG-062)');
    $db->commit();
    echo "== Remplies : $m (identifiants et ancienne valeur gardés dans " . TABLE_GARDE . ")\n";
} else {
    if (!$db->query("SHOW TABLES LIKE '" . TABLE_GARDE . "'")->fetch()) {
        echo "::error::Rien à annuler : " . TABLE_GARDE . " n'existe pas.\n";
        exit(1);
    }
    $db->beginTransaction();
    $m = $db->exec('UPDATE ateliers a JOIN ' . TABLE_GARDE . ' g ON g.id = a.id SET a.duree = g.ancienne WHERE a.duree = 90');
    journal($db, $m . ' atelier(s) : durée remise à vide (annulation AG-062)');
    $db->commit();
    echo "== Remises à vide : $m\n";
}
echo "== Après\n";
$apres = compter($db);
if ($mode === 'appliquer' && $apres !== 0) {
    echo "::error::Il reste $apres durée(s) vide(s) après remplissage.\n";
    exit(1);
}
