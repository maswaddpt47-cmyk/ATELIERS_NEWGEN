<?php
// Anomalies validées (AG-044, 04/11/2026) : l'Admin marque une anomalie de l'onglet
// Anomalies comme vérifiée ; elle sort de la liste tant que la valeur concernée ne
// change pas. Une ligne par couple (atelier, catégorie), écrite de façon atomique
// (pas de blob JSON : deux admins qui valident ensemble ne s'écrasent pas).
// Ne change AUCUN calcul : stock, conflits, indicateurs, bilans et exports ignorent ces lignes.

require_once __DIR__ . '/base.php';

const ANOMALIES_CATEGORIES = ['manquants', 'communes', 'chiffres', 'ordi', 'tard'];

function anomalies_schema(PDO $db): void
{
    static $ok = false;
    if ($ok) return;
    foreach (import_requetes_schema() as $sql) {
        if (str_contains($sql, 'EXISTS anomalies_validees')) $db->exec($sql);
    }
    $ok = true;
}

function action_get_anomalies_validees(PDO $db): array
{
    anomalies_schema($db);
    $l = $db->query('SELECT atelier_id, categorie, empreinte, valide_le, par FROM anomalies_validees ORDER BY valide_le DESC')->fetchAll(PDO::FETCH_ASSOC);
    return ['ok' => true, 'validees' => $l];
}

// L'empreinte (valeurs concernées au moment du clic) est calculée par le navigateur ;
// le serveur contrôle le rôle (admin, en amont), l'atelier, la catégorie et les longueurs.
function action_valider_anomalie(PDO $db, array $p, string $par): array
{
    anomalies_schema($db);
    $id = trim((string) ($p['_id'] ?? ''));
    $cat = (string) ($p['categorie'] ?? '');
    $emp = (string) ($p['empreinte'] ?? '');
    if ($id === '' || mb_strlen($id) > 100) return ['ok' => false, 'error' => 'Atelier manquant'];
    if (!in_array($cat, ANOMALIES_CATEGORIES, true)) return ['ok' => false, 'error' => 'Catégorie inconnue'];
    if ($emp === '' || mb_strlen($emp) > 200) return ['ok' => false, 'error' => 'Valeur à valider manquante ou trop longue'];
    $s = $db->prepare('SELECT 1 FROM ateliers WHERE id = ?');
    $s->execute([$id]);
    if (!$s->fetchColumn()) return ['ok' => false, 'error' => 'Atelier introuvable'];
    $db->prepare('INSERT INTO anomalies_validees (atelier_id, categorie, empreinte, valide_le, par) VALUES (?, ?, ?, NOW(), ?)'
        . ' ON DUPLICATE KEY UPDATE empreinte = VALUES(empreinte), valide_le = NOW(), par = VALUES(par)')
       ->execute([$id, $cat, $emp, mb_substr($par, 0, 100)]);
    return ['ok' => true];
}

function action_annuler_anomalie(PDO $db, array $p): array
{
    anomalies_schema($db);
    $id = trim((string) ($p['_id'] ?? ''));
    $cat = (string) ($p['categorie'] ?? '');
    if ($id === '' || !in_array($cat, ANOMALIES_CATEGORIES, true)) return ['ok' => false, 'error' => 'Anomalie inconnue'];
    $db->prepare('DELETE FROM anomalies_validees WHERE atelier_id = ? AND categorie = ?')->execute([$id, $cat]);
    return ['ok' => true];
}
