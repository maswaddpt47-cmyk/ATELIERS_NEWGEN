<?php
// Actions de l'API (remplaçant du GAS NEWGEN) — étape « lecture ».
//
// Contrat : mêmes noms d'action et mêmes formes de réponse que
// gas/GAS_NEWGEN.js, pour que le client change le moins possible. Écarts
// voulus, tous de sécurité (AG-009, AG-011) :
//   - jeton exigé pour getAll, getConfig, getVisibility ;
//   - jeton et mot de passe lus dans le corps POST, jamais dans l'URL (une
//     URL finit dans les journaux d'accès de l'hébergeur) ;
//   - getComptes sans jeton admin ne rend que les noms des comptes actifs,
//     plus l'état de maintenance (seules infos utiles avant connexion) ;
//   - la maintenance est levée par le rôle du jeton, plus par source=admin.
// Chaque fonction action_* reçoit la base et les paramètres, et renvoie le
// tableau à encoder en JSON.

require_once __DIR__ . '/base.php';

const API_JETON_DUREE_S = 6 * 3600;          // comme TOKEN_TTL_SECONDS du GAS
const API_ECHECS_MAX = 5;                    // 5 échecs → blocage 15 min
const API_BLOCAGE_S = 15 * 60;
const API_ROLES_ADMIN = ['admin', 'superviseur'];

// Ordre des champs d'un atelier dans la réponse (contract.test.js:12-34).
const API_CHAMPS_ATELIER = [
    '_id' => 'id', '_n' => 'n', 'statut' => 'statut', 'date' => 'date', 'horaire' => 'horaire',
    'ampm' => 'ampm', 'orienteur' => 'orienteur', 'commune' => 'commune', 'lieu' => 'lieu',
    'thematique' => 'thematique', 'inscrits' => 'inscrits', 'presents' => 'presents',
    'public' => 'public', 'conseiller' => 'conseiller', 'co_animateur' => 'co_animateur',
    'residence' => 'residence', 'remarques' => 'remarques', 'nb_ordinateurs' => 'nb_ordinateurs',
    'date_prelevement_materiel' => 'date_prelevement_materiel',
    'date_retour_materiel' => 'date_retour_materiel',
];

// Point d'entrée : choisit l'action et applique la règle d'accès.
function api_traiter(PDO $db, string $action, array $get, array $post): array
{
    // Paramètres ordinaires : corps POST prioritaire, URL acceptée.
    $p = $post + $get;
    // Secrets : corps POST uniquement.
    $jeton = (string) ($post['token'] ?? '');
    unset($p['token'], $p['password']);
    $p['password'] = (string) ($post['password'] ?? '');

    switch ($action) {
        case 'checkPassword':
            return action_check_password($db, $p);
        case 'getComptes':
            return action_get_comptes($db, api_session($db, $jeton));
        case 'getAll':
        case 'getConfig':
        case 'getVisibility':
            $session = api_session($db, $jeton);
            if ($session === null) return ['ok' => false, 'error' => 'Non autorisé : jeton manquant ou expiré', 'auth' => true];
            if ($action === 'getAll') return action_get_all($db, $p, $session);
            if ($action === 'getConfig') return ['ok' => true, 'config' => api_config_base($db)];
            return ['ok' => true, 'visibility' => api_json(api_config_base($db)['visibility'] ?? '', (object) [])];
    }
    return ['ok' => false, 'error' => 'action inconnue: ' . $action];
}

// ── Connexion ─────────────────────────────────────────────────────────────

function action_check_password(PDO $db, array $p): array
{
    $nom = trim((string) ($p['conseiller'] ?? ''));
    $mdp = trim((string) ($p['password'] ?? ''));   // le GAS retirait aussi les espaces
    if ($nom === '' || $mdp === '') return ['ok' => false, 'error' => 'Paramètres manquants'];

    $t = $db->prepare('SELECT nb, bloque_jusqua FROM tentatives WHERE conseiller = ?');
    $t->execute([$nom]);
    $tent = $t->fetch(PDO::FETCH_ASSOC) ?: ['nb' => 0, 'bloque_jusqua' => null];
    if ($tent['bloque_jusqua'] !== null && strtotime($tent['bloque_jusqua']) > time()) {
        $min = (int) ceil((strtotime($tent['bloque_jusqua']) - time()) / 60);
        return ['ok' => false, 'error' => "Trop de tentatives. Réessayez dans $min min."];
    }

    $c = $db->prepare('SELECT conseiller, hash, role, actif, doit_changer FROM comptes WHERE conseiller = ?');
    $c->execute([$nom]);
    $compte = $c->fetch(PDO::FETCH_ASSOC);
    if (!$compte) return ['ok' => false, 'error' => 'Conseiller introuvable'];
    if ((int) $compte['actif'] === 0) return ['ok' => false, 'error' => 'Compte désactivé'];

    // Empreinte stockée = password_hash(sha256_hex(mot de passe)) (schema.sql).
    $empreinte = hash('sha256', $mdp);
    $ok = $compte['hash'] !== null && password_verify($empreinte, $compte['hash']);
    if (!$ok) {
        $nb = (int) $tent['nb'] + 1;
        $bloque = null;
        if ($nb >= API_ECHECS_MAX) { $bloque = date('Y-m-d H:i:s', time() + API_BLOCAGE_S); $nb = 0; }
        $db->prepare('REPLACE INTO tentatives (conseiller, nb, bloque_jusqua) VALUES (?, ?, ?)')->execute([$nom, $nb, $bloque]);
        api_journal($db, 'loginFail', $nom, '', '', 0, $nb, (string) ($p['userAgent'] ?? ''), (string) ($p['source'] ?? ''));
        return ['ok' => false, 'error' => 'Mot de passe incorrect'];
    }

    $db->prepare('DELETE FROM tentatives WHERE conseiller = ?')->execute([$nom]);
    if (password_needs_rehash($compte['hash'], PASSWORD_DEFAULT)) {
        $db->prepare('UPDATE comptes SET hash = ? WHERE conseiller = ?')->execute([password_hash($empreinte, PASSWORD_DEFAULT), $nom]);
    }
    // Jeton aléatoire ; seule son empreinte est gardée en base.
    $jeton = bin2hex(random_bytes(32));
    $db->exec('DELETE FROM sessions WHERE expire < NOW()');
    $db->prepare('INSERT INTO sessions (jeton_hash, conseiller, role, expire) VALUES (?, ?, ?, ?)')
       ->execute([hash('sha256', $jeton), $nom, $compte['role'], date('Y-m-d H:i:s', time() + API_JETON_DUREE_S)]);
    // Pas de journal ici : le client appelle logLogin ensuite (comme le GAS).
    $r = ['ok' => true, 'role' => $compte['role'], 'token' => $jeton];
    if ((int) $compte['doit_changer'] === 1) $r['doit_changer'] = true;   // ajout, ignoré par le client actuel
    return $r;
}

// Renvoie ['conseiller' => …, 'role' => …] pour un jeton valide, sinon null.
function api_session(PDO $db, string $jeton): ?array
{
    if (!preg_match('/^[0-9a-f]{64}$/', $jeton)) return null;
    $s = $db->prepare('SELECT conseiller, role FROM sessions WHERE jeton_hash = ? AND expire > NOW()');
    $s->execute([hash('sha256', $jeton)]);
    return $s->fetch(PDO::FETCH_ASSOC) ?: null;
}

function api_journal(PDO $db, string $action, string $conseiller, string $ref, string $role, int $succes, int $tentatives, string $ua, string $source): void
{
    $db->prepare('INSERT INTO journal (horodatage, action, conseiller, ref, role, succes, tentatives, user_agent, source) VALUES (NOW(), ?, ?, ?, ?, ?, ?, ?, ?)')
       ->execute([$action, mb_substr($conseiller, 0, 100), mb_substr($ref, 0, 100), mb_substr($role, 0, 20), $succes, $tentatives, mb_substr($ua, 0, 500), mb_substr($source, 0, 50)]);
}

// ── Lectures ──────────────────────────────────────────────────────────────

function action_get_comptes(PDO $db, ?array $session): array
{
    if ($session !== null && in_array($session['role'], API_ROLES_ADMIN, true)) {
        $l = $db->query('SELECT conseiller, role, actif FROM comptes ORDER BY conseiller')->fetchAll(PDO::FETCH_ASSOC);
        return ['ok' => true, 'comptes' => array_map(fn($c) => [
            'conseiller' => $c['conseiller'], 'role' => $c['role'], 'actif' => (int) $c['actif'] === 1 ? 'OUI' : 'NON',
        ], $l)];
    }
    // Public : les noms nécessaires à la liste de connexion, rien d'autre.
    $noms = $db->query('SELECT conseiller FROM comptes WHERE actif = 1 ORDER BY conseiller')->fetchAll(PDO::FETCH_COLUMN);
    $cfg = api_config_base($db);
    return [
        'ok' => true,
        'comptes' => array_map(fn($n) => ['conseiller' => $n], $noms),
        'maintenance' => api_maintenance($cfg),
        'maintenance_msg' => api_maintenance($cfg) ? (string) ($cfg['maintenance_msg'] ?? '') : '',
    ];
}

function action_get_all(PDO $db, array $p, array $session): array
{
    if (isset($p['years'])) {
        $annees = api_annees((string) $p['years']);
        if ($annees === null) return ['ok' => false, 'error' => 'Paramètre years invalide'];
    } else {
        $an = (string) ($p['year'] ?? date('Y'));
        if (!preg_match('/^\d{4}$/', $an)) return ['ok' => false, 'error' => 'Paramètre year invalide'];
        $annees = [$an];
    }

    $cfg = api_config_base($db);
    if (api_maintenance($cfg) && !in_array($session['role'], API_ROLES_ADMIN, true)) {
        return ['ok' => false, 'maintenance' => true, 'msg' => (string) ($cfg['maintenance_msg'] ?? '')];
    }

    // Même lecture des listes que _actionGetAllFresh (GAS_NEWGEN.js:655-660).
    $lists = ['statuts' => [], 'conseillers' => [], 'publics' => [], 'materiels' => []];
    if (($cfg['list_statuts'] ?? '') !== '' || ($cfg['list_conseillers'] ?? '') !== '') {
        foreach ($lists as $k => $_) $lists[$k] = api_liste($cfg['list_' . $k] ?? '');
    } elseif (($cfg['lists'] ?? '') !== '') {
        $ol = api_json($cfg['lists'], []);
        foreach ($lists as $k => $_) $lists[$k] = is_array($ol[$k] ?? null) ? $ol[$k] : [];
    }

    $r = [
        'ok' => true,
        'entries' => api_ateliers($db, $annees),
        'lists' => $lists,
        'visibility' => api_json($cfg['visibility'] ?? '', (object) []),
        'conseiller_colors' => api_json($cfg['conseiller_colors'] ?? '', (object) []),
        'emails' => api_json($cfg['emails'] ?? '', (object) []),
        'stockOrdinateurs' => ((int) ($cfg['stock_ordinateurs'] ?? 0)) ?: 10,
        'materielsCaches' => api_json($cfg['materiels_caches'] ?? '', []),
    ];
    if (isset($p['years'])) $r['years'] = $annees;
    return $r;
}

// « 2027,2026,2026 » → ['2026','2027'] ; de 1 à 3 années (GAS_NEWGEN.js:587-595).
function api_annees(string $s): ?array
{
    $l = array_values(array_unique(array_filter(array_map('trim', explode(',', $s)), fn($x) => preg_match('/^\d{4}$/', $x))));
    sort($l);
    return (count($l) >= 1 && count($l) <= 3) ? $l : null;
}

function api_ateliers(PDO $db, array $annees): array
{
    $marques = implode(',', array_fill(0, count($annees), '?'));
    $cols = implode(', ', array_map(fn($c) => "`$c`", array_values(API_CHAMPS_ATELIER)));
    $st = $db->prepare("SELECT $cols FROM ateliers WHERE YEAR(date) IN ($marques) ORDER BY date, horaire, n");
    $st->execute(array_map('intval', $annees));
    $lignes = $st->fetchAll(PDO::FETCH_ASSOC);

    $materiel = [];
    if ($lignes) {
        $m = $db->prepare("SELECT m.atelier_id, m.materiel FROM ateliers_materiel m JOIN ateliers a ON a.id = m.atelier_id WHERE YEAR(a.date) IN ($marques) ORDER BY m.materiel");
        $m->execute(array_map('intval', $annees));
        foreach ($m->fetchAll(PDO::FETCH_ASSOC) as $x) $materiel[$x['atelier_id']][] = $x['materiel'];
    }

    $entries = [];
    foreach ($lignes as $l) {
        $e = [];
        foreach (API_CHAMPS_ATELIER as $cle => $col) {
            // Le GAS rendait '' pour une cellule vide et un nombre pour un
            // nombre : NULL → '' ; les entiers restent des entiers.
            $e[$cle] = $l[$col] ?? '';
        }
        $e['materiel'] = $materiel[$l['id']] ?? [];
        $entries[] = $e;
    }
    return $entries;
}

// ── Config ────────────────────────────────────────────────────────────────

function api_config_base(PDO $db): array
{
    return $db->query('SELECT cle, valeur FROM config')->fetchAll(PDO::FETCH_KEY_PAIR);
}

// Même test que le GAS : 'true' ou 'TRUE' (GAS_NEWGEN.js:654).
function api_maintenance(array $cfg): bool
{
    return in_array((string) ($cfg['maintenance'] ?? ''), ['true', 'TRUE'], true);
}

// JSON d'une valeur de config, ou $defaut si vide ou illisible.
function api_json(string $v, mixed $defaut): mixed
{
    if (trim($v) === '') return $defaut;
    $d = json_decode($v, true);
    if ($d === null && json_last_error() !== JSON_ERROR_NONE) return $defaut;
    // {} décodé en tableau PHP vide s'encoderait [] : on garde l'objet.
    if ($d === [] && str_starts_with(trim($v), '{')) return (object) [];
    return $d;
}

// Liste : JSON « [...] » ou une valeur par ligne (_parseList, GAS_NEWGEN.js:999).
function api_liste(string $v): array
{
    $s = trim($v);
    if ($s === '') return [];
    if ($s[0] === '[') {
        $d = json_decode($s, true);
        if (is_array($d)) return $d;
    }
    return array_values(array_filter(array_map('trim', explode("\n", $s)), fn($x) => $x !== ''));
}
