<?php
// Avis des stagiaires par QR code (AG-021, 04/10/2026, CR « option 1 »,
// module B et C). Anonymes : ni nom, ni adresse IP, ni tranche d'âge, ni
// « être recontacté » tant que le DPO n'a pas validé (décision du 04/10).
//
// - jetonAvis (conseiller) : jeton de l'atelier, créé à la demande, et résumé
//   des avis reçus ;
// - avisPublic, deposerAvis (SANS connexion) : la page avis.html, ouverte par
//   le QR code. Acceptés de la veille à AVIS_JOURS_APRES jours après
//   l'atelier, AVIS_MAX par atelier, réponses hors liste refusées.

const AVIS_JOURS_APRES = 30;
const AVIS_MAX = 60;
const AVIS_CONSERVATION_MOIS = 24;   // RGPD : purge à la connexion
const AVIS_CHOIX = [
    'rythme' => ['Trop lent', 'Adapté', 'Trop rapide'],
    'aise' => ['Non', 'Un peu', 'Oui'],
    'autonomie' => ['Oui', 'Avec de l\'aide', 'Non'],
    'sujet' => ['Smartphone', 'Messagerie', 'Démarches en ligne', 'Sécurité', 'Intelligence artificielle', 'Autre'],
];
const AVIS_NOTES = ['attentes', 'clarte'];   // 1 à 5 étoiles

function avis_schema(PDO $db): void
{
    static $fait = false;
    if ($fait) return;
    $db->exec("CREATE TABLE IF NOT EXISTS avis_jetons (
        atelier_id VARCHAR(64) NOT NULL, jeton CHAR(32) NOT NULL, cree_le DATETIME NOT NULL,
        PRIMARY KEY (atelier_id), UNIQUE KEY uk_jeton (jeton)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $db->exec("CREATE TABLE IF NOT EXISTS avis (
        id INT NOT NULL AUTO_INCREMENT, atelier_id VARCHAR(64) NOT NULL, cree_le DATETIME NOT NULL,
        attentes TINYINT NULL, rythme VARCHAR(20) NULL, clarte TINYINT NULL, aise VARCHAR(20) NULL,
        autonomie VARCHAR(20) NULL, sujet VARCHAR(40) NULL, sujet_autre VARCHAR(60) NULL, remarque VARCHAR(500) NULL,
        PRIMARY KEY (id), KEY idx_atelier (atelier_id), KEY idx_cree (cree_le)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $fait = true;
}

function avis_purger(PDO $db): void
{
    avis_schema($db);
    $limite = date('Y-m-d H:i:s', strtotime('-' . AVIS_CONSERVATION_MOIS . ' months'));
    $db->prepare('DELETE FROM avis WHERE cree_le < ?')->execute([$limite]);
}

// Résumé des avis d'un atelier, pour la fiche (conseiller).
function avis_resume(PDO $db, string $atelierId): array
{
    $s = $db->prepare('SELECT COUNT(*) n, AVG(attentes) attentes, AVG(clarte) clarte,
                              SUM(aise = \'Oui\') aise_oui, SUM(autonomie = \'Oui\') autonomie_oui
                       FROM avis WHERE atelier_id = ?');
    $s->execute([$atelierId]);
    $r = $s->fetch(PDO::FETCH_ASSOC);
    $rem = $db->prepare("SELECT remarque FROM avis WHERE atelier_id = ? AND remarque <> '' ORDER BY id DESC LIMIT 20");
    $rem->execute([$atelierId]);
    return ['n' => (int) $r['n'],
        'attentes' => $r['attentes'] !== null ? round((float) $r['attentes'], 1) : null,
        'clarte' => $r['clarte'] !== null ? round((float) $r['clarte'], 1) : null,
        'aise_oui' => (int) $r['aise_oui'], 'autonomie_oui' => (int) $r['autonomie_oui'],
        'remarques' => $rem->fetchAll(PDO::FETCH_COLUMN)];
}

function action_jeton_avis(PDO $db, array $p): array
{
    avis_schema($db);
    $id = (string) ($p['_id'] ?? '');
    $s = $db->prepare('SELECT id FROM ateliers WHERE id = ?');
    $s->execute([$id]);
    if ($s->fetchColumn() === false) return ['ok' => false, 'error' => 'Atelier introuvable'];
    // INSERT IGNORE : rejouer l'appel rend le même jeton.
    $db->prepare('INSERT IGNORE INTO avis_jetons (atelier_id, jeton, cree_le) VALUES (?, ?, ?)')
       ->execute([$id, bin2hex(random_bytes(16)), date('Y-m-d H:i:s')]);
    $j = $db->prepare('SELECT jeton FROM avis_jetons WHERE atelier_id = ?');
    $j->execute([$id]);
    return ['ok' => true, 'jeton' => (string) $j->fetchColumn(), 'avis' => avis_resume($db, $id)];
}

// Atelier d'un jeton, s'il accepte encore des avis ; sinon raison du refus.
function avis_atelier(PDO $db, string $jeton, ?string &$err): ?array
{
    avis_schema($db);
    $err = null;
    if (!preg_match('/^[0-9a-f]{32}$/', $jeton)) { $err = 'Lien invalide'; return null; }
    $s = $db->prepare('SELECT a.id, a.date, a.thematique FROM avis_jetons j JOIN ateliers a ON a.id = j.atelier_id WHERE j.jeton = ?');
    $s->execute([$jeton]);
    $a = $s->fetch(PDO::FETCH_ASSOC);
    if (!$a) { $err = 'Lien invalide'; return null; }
    $jour = date('Y-m-d');
    if ($jour < date('Y-m-d', strtotime($a['date'] . ' -1 day')) || $jour > date('Y-m-d', strtotime($a['date'] . ' +' . AVIS_JOURS_APRES . ' days'))) {
        $err = 'Ce questionnaire n\'est plus ouvert'; return null;
    }
    return $a;
}

// Page publique : seulement la date et le thème, pour rassurer la personne
// qu'elle répond sur le bon atelier. Rien sur l'animateur ni le lieu.
function action_avis_public(PDO $db, array $p): array
{
    $a = avis_atelier($db, (string) ($p['a'] ?? ''), $err);
    if ($a === null) return ['ok' => false, 'error' => $err];
    return ['ok' => true, 'atelier' => ['date' => $a['date'], 'thematique' => $a['thematique']]];
}

function action_deposer_avis(PDO $db, array $p): array
{
    $a = avis_atelier($db, (string) ($p['a'] ?? ''), $err);
    if ($a === null) return ['ok' => false, 'error' => $err];
    $n = $db->prepare('SELECT COUNT(*) FROM avis WHERE atelier_id = ?');
    $n->execute([$a['id']]);
    if ((int) $n->fetchColumn() >= AVIS_MAX) return ['ok' => false, 'error' => 'Nombre maximal d\'avis atteint pour cet atelier'];
    $l = ['atelier_id' => $a['id'], 'cree_le' => date('Y-m-d H:i:s')];
    foreach (AVIS_NOTES as $c) {
        $v = trim((string) ($p[$c] ?? ''));
        if ($v === '') { $l[$c] = null; continue; }
        if (!preg_match('/^[1-5]$/', $v)) return ['ok' => false, 'error' => "Réponse invalide ($c)"];
        $l[$c] = (int) $v;
    }
    foreach (AVIS_CHOIX as $c => $liste) {
        $v = trim((string) ($p[$c] ?? ''));
        if ($v !== '' && !in_array($v, $liste, true)) return ['ok' => false, 'error' => "Réponse invalide ($c)"];
        $l[$c] = $v === '' ? null : $v;
    }
    $l['sujet_autre'] = ($l['sujet'] ?? '') === 'Autre' ? (mb_substr(trim((string) ($p['sujet_autre'] ?? '')), 0, 60) ?: null) : null;
    $l['remarque'] = mb_substr(trim((string) ($p['remarque'] ?? '')), 0, 500);
    if (count(array_filter($l, fn($v) => $v !== null && $v !== '')) <= 2) return ['ok' => false, 'error' => 'Répondez au moins à une question'];
    $cols = array_keys($l);
    $db->prepare('INSERT INTO avis (' . implode(', ', $cols) . ') VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ')')
       ->execute(array_values($l));
    return ['ok' => true];
}
