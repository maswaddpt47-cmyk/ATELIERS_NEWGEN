<?php
// Avis des stagiaires par QR code (AG-021, 04/10/2026, CR « option 1 »,
// module B et C). Anonymes : ni nom, ni adresse IP, ni tranche d'âge, ni
// « être recontacté » tant que le DPO n'a pas validé (décision du 04/10).
//
// - jetonAvis (conseiller) : jeton de l'atelier, créé à la demande, et résumé
//   des avis reçus ;
// - avisPublic, deposerAvis (SANS connexion) : la page avis.html, ouverte par
//   le QR code. Acceptés du jour de l'atelier à AVIS_JOURS_APRES jours après
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
        source VARCHAR(10) NOT NULL DEFAULT 'qr',
        PRIMARY KEY (id), KEY idx_atelier (atelier_id), KEY idx_cree (cree_le)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    // Table créée avant l'amendement D d'AG-021 (bac à sable) : colonne source.
    if (!$db->query("SHOW COLUMNS FROM avis LIKE 'source'")->fetch()) {
        $db->exec("ALTER TABLE avis ADD COLUMN source VARCHAR(10) NOT NULL DEFAULT 'qr'");
    }
    $fait = true;
}

function avis_purger(PDO $db): void
{
    avis_schema($db);
    $limite = date('Y-m-d H:i:s', strtotime('-' . AVIS_CONSERVATION_MOIS . ' months'));
    $db->prepare('DELETE FROM avis WHERE cree_le < ?')->execute([$limite]);
    // Pas de clé étrangère en cascade (amendement A d'AG-021) : un atelier mis
    // à la corbeille garde ses avis et son jeton, rendus à la restauration ;
    // ils ne partent qu'avec l'atelier sorti de la corbeille.
    corbeille_schema($db);
    foreach (['avis', 'avis_jetons'] as $t) {
        $db->exec("DELETE FROM $t WHERE atelier_id NOT IN (SELECT id FROM ateliers)
                   AND atelier_id NOT IN (SELECT id FROM ateliers_corbeille)");
    }
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

// Avis d'un atelier : réservés à son animateur et à son co-animateur
// (décision de l'utilisateur, 04/10/2026) ; l'Admin et la superviseure voient
// tout. Filtre SQL sur la table ateliers (alias a) et ses paramètres.
// moi=1 : restreint aussi l'Admin à ses propres ateliers (onglet « Mes bilans »).
function avis_filtre_conum(array $session, array $p = []): array
{
    if (in_array($session['role'] ?? '', API_ROLES_ADMIN, true) && empty($p['moi'])) return ['', []];
    $nom = (string) ($session['conseiller'] ?? '');
    return [' AND (a.conseiller = ? OR a.co_animateur = ?)', [$nom, $nom]];
}

// L'atelier existe et la personne connectée peut en voir les avis ; sinon
// le message de refus.
function avis_atelier_autorise(PDO $db, string $id, array $session): ?string
{
    [$f, $fp] = avis_filtre_conum($session);
    $s = $db->prepare("SELECT a.id FROM ateliers a WHERE a.id = ?$f");
    $s->execute(array_merge([$id], $fp));
    if ($s->fetchColumn() !== false) return null;
    $e = $db->prepare('SELECT id FROM ateliers WHERE id = ?');
    $e->execute([$id]);
    return $e->fetchColumn() === false ? 'Atelier introuvable' : 'Les avis de cet atelier sont réservés à son animateur et à son co-animateur';
}

function action_jeton_avis(PDO $db, array $p, array $session): array
{
    avis_schema($db);
    $id = (string) ($p['_id'] ?? '');
    if (($refus = avis_atelier_autorise($db, $id, $session)) !== null) return ['ok' => false, 'error' => $refus];
    // INSERT IGNORE : rejouer l'appel rend le même jeton.
    $db->prepare('INSERT IGNORE INTO avis_jetons (atelier_id, jeton, cree_le) VALUES (?, ?, ?)')
       ->execute([$id, bin2hex(random_bytes(16)), date('Y-m-d H:i:s')]);
    $j = $db->prepare('SELECT jeton FROM avis_jetons WHERE atelier_id = ?');
    $j->execute([$id]);
    $d = $db->prepare('SELECT date FROM ateliers WHERE id = ?');
    $d->execute([$id]);
    [$debut, $fin] = avis_fenetre((string) $d->fetchColumn());
    return ['ok' => true, 'jeton' => (string) $j->fetchColumn(), 'avis' => avis_resume($db, $id), 'ouvert_du' => $debut, 'ouvert_au' => $fin];
}

// Dates d'ouverture du questionnaire d'un atelier : du jour de l'atelier
// (décision de l'utilisateur, 04/10/2026 ; la veille auparavant) à
// AVIS_JOURS_APRES jours après (AAAA-MM-JJ inclus).
function avis_fenetre(string $date): array
{
    return [date('Y-m-d', strtotime($date)), date('Y-m-d', strtotime("$date +" . AVIS_JOURS_APRES . ' days'))];
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
    // Message précis (04/10/2026) : « pas encore ouvert » et « fermé » se
    // distinguent, avec la date, pour l'agent qui teste le QR à l'avance.
    $jour = date('Y-m-d');
    [$debut, $fin] = avis_fenetre($a['date']);
    if ($jour < $debut) { $err = 'Ce questionnaire ouvrira le ' . date('d/m/Y', strtotime($debut)) . ', le jour de l\'atelier.'; return null; }
    if ($jour > $fin) { $err = 'Ce questionnaire est fermé depuis le ' . date('d/m/Y', strtotime($fin . ' +1 day')) . '.'; return null; }
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
    return avis_enregistrer($db, $a['id'], $p, 'qr');
}

// Avis papier recopié par un conseiller connecté (amendement D d'AG-021) :
// sans fenêtre de dates ; journalisé au nom du conseiller (sans le contenu).
function action_saisir_avis_papier(PDO $db, array $p, array $session): array
{
    avis_schema($db);
    $id = (string) ($p['_id'] ?? '');
    if (($refus = avis_atelier_autorise($db, $id, $session)) !== null) return ['ok' => false, 'error' => $refus];
    $r = avis_enregistrer($db, $id, $p, 'papier');
    if ($r['ok']) api_journal($db, 'saisirAvisPapier', $session['conseiller'], $id, $session['role'], 1, 0, '', '');
    return $r;
}

function avis_enregistrer(PDO $db, string $atelierId, array $p, string $source): array
{
    $a = ['id' => $atelierId];
    $n = $db->prepare('SELECT COUNT(*) FROM avis WHERE atelier_id = ?');
    $n->execute([$a['id']]);
    if ((int) $n->fetchColumn() >= AVIS_MAX) return ['ok' => false, 'error' => 'Nombre maximal d\'avis atteint pour cet atelier'];
    // Date seule, pas l'heure (amendement B) : avec des groupes de 3 à 6
    // personnes, l'heure d'un avis aiderait à reconnaître qui l'a donné.
    $l = ['atelier_id' => $a['id'], 'cree_le' => date('Y-m-d'), 'source' => $source];
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
    if (count(array_filter($l, fn($v) => $v !== null && $v !== '')) <= 3) return ['ok' => false, 'error' => 'Répondez au moins à une question'];
    $cols = array_keys($l);
    $db->prepare('INSERT INTO avis (' . implode(', ', $cols) . ') VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ')')
       ->execute(array_values($l));
    return ['ok' => true];
}

// Bilan trimestriel (CR « option 1 », module D, 04/10/2026) : les réponses
// des ateliers « Réalisé » de la période, une ligne par avis, sans date ni
// remarque ; les remarques à part, sans lien avec leur atelier. Le client
// agrège (bilanTrimestriel, logic.js). Lecture seule, équipe connectée.
function action_bilan_avis(PDO $db, array $p, array $session): array
{
    avis_schema($db);
    [$f, $fp] = avis_filtre_conum($session, $p);
    $du = (string) ($p['du'] ?? '');
    $au = (string) ($p['au'] ?? '');
    $jour = '/^\d{4}-\d{2}-\d{2}$/';
    if (!preg_match($jour, $du) || !preg_match($jour, $au) || $du > $au) return ['ok' => false, 'error' => 'Période invalide'];
    $s = $db->prepare("SELECT v.atelier_id, v.attentes, v.clarte, v.rythme, v.aise, v.autonomie, v.sujet
                       FROM avis v JOIN ateliers a ON a.id = v.atelier_id
                       WHERE a.statut = 'Réalisé' AND a.date BETWEEN ? AND ?$f ORDER BY v.id");
    $s->execute(array_merge([$du, $au], $fp));
    $avis = array_map(fn($r) => ['atelier_id' => $r['atelier_id'],
        'attentes' => $r['attentes'] !== null ? (int) $r['attentes'] : null,
        'clarte' => $r['clarte'] !== null ? (int) $r['clarte'] : null,
        'rythme' => $r['rythme'], 'aise' => $r['aise'], 'autonomie' => $r['autonomie'], 'sujet' => $r['sujet']],
        $s->fetchAll(PDO::FETCH_ASSOC));
    $r = $db->prepare("SELECT v.remarque FROM avis v JOIN ateliers a ON a.id = v.atelier_id
                       WHERE a.statut = 'Réalisé' AND a.date BETWEEN ? AND ?$f AND v.remarque <> '' ORDER BY v.id LIMIT 100");
    $r->execute(array_merge([$du, $au], $fp));
    return ['ok' => true, 'avis' => $avis, 'remarques' => $r->fetchAll(PDO::FETCH_COLUMN)];
}

// Récapitulatif des avis atelier par atelier (demande du 04/10/2026), à tout
// moment, sans attendre le bilan trimestriel : tous statuts, ateliers datés
// de la période. Mêmes informations que la fenêtre du QR de chaque atelier,
// réunies dans un tableau. Lecture seule, équipe connectée.
function action_avis_par_atelier(PDO $db, array $p, array $session): array
{
    avis_schema($db);
    [$f, $fp] = avis_filtre_conum($session, $p);
    $du = (string) ($p['du'] ?? '');
    $au = (string) ($p['au'] ?? '');
    $jour = '/^\d{4}-\d{2}-\d{2}$/';
    if (!preg_match($jour, $du) || !preg_match($jour, $au) || $du > $au) return ['ok' => false, 'error' => 'Période invalide'];
    $s = $db->prepare("SELECT v.atelier_id, COUNT(*) n, SUM(v.source = 'papier') papier,
                              AVG(v.attentes) attentes, AVG(v.clarte) clarte,
                              SUM(v.rythme = 'Adapté') rythme_ok, SUM(v.rythme IS NOT NULL) rythme_n,
                              SUM(v.aise = 'Oui') aise_oui, SUM(v.aise IS NOT NULL) aise_n,
                              SUM(v.autonomie = 'Oui') autonomie_oui, SUM(v.autonomie IS NOT NULL) autonomie_n
                       FROM avis v JOIN ateliers a ON a.id = v.atelier_id
                       WHERE a.date BETWEEN ? AND ?$f GROUP BY v.atelier_id");
    $s->execute(array_merge([$du, $au], $fp));
    $r = $db->prepare("SELECT v.atelier_id, v.remarque FROM avis v JOIN ateliers a ON a.id = v.atelier_id
                       WHERE a.date BETWEEN ? AND ?$f AND v.remarque <> '' ORDER BY v.id");
    $r->execute(array_merge([$du, $au], $fp));
    $rem = [];
    foreach ($r->fetchAll(PDO::FETCH_ASSOC) as $l) $rem[$l['atelier_id']][] = $l['remarque'];
    $moy = fn($v) => $v !== null ? round((float) $v, 1) : null;
    return ['ok' => true, 'ateliers' => array_map(fn($l) => [
        'atelier_id' => $l['atelier_id'], 'n' => (int) $l['n'], 'papier' => (int) $l['papier'],
        'attentes' => $moy($l['attentes']), 'clarte' => $moy($l['clarte']),
        'rythme_ok' => (int) $l['rythme_ok'], 'rythme_n' => (int) $l['rythme_n'],
        'aise_oui' => (int) $l['aise_oui'], 'aise_n' => (int) $l['aise_n'],
        'autonomie_oui' => (int) $l['autonomie_oui'], 'autonomie_n' => (int) $l['autonomie_n'],
        'remarques' => $rem[$l['atelier_id']] ?? [],
    ], $s->fetchAll(PDO::FETCH_ASSOC))];
}
