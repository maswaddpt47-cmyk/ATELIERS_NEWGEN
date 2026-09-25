<?php
// Actions d'écriture de l'API (remplaçant du GAS NEWGEN).
//
// Même contrat que gas/GAS_NEWGEN.js (noms d'action, paramètres, réponses),
// avec ces écarts voulus :
//   - saveEntry valide chaque champ avant d'écrire (date, heure, nombres) :
//     la feuille acceptait tout, la base refuse ce qu'elle ne sait pas typer ;
//   - le verrou LockService devient une transaction SQL : deux écritures
//     simultanées sur le même _id ne créent jamais de doublon (clé primaire) ;
//   - resetPassword tire un mot de passe provisoire aléatoire (plus
//     cd47+prénom, devinable), stocké haché, changement forcé ensuite ;
//   - saveLists crée les comptes manquants SANS mot de passe (plus
//     cd47+prénom en clair) : l'admin fait ensuite « réinitialiser » ;
//   - logLogin ne fait plus rien : checkPassword journalise lui-même la
//     connexion réussie (une ligne de journal ne se forge plus sans jeton) ;
//   - logAccesIndex exige un jeton, et journalise la personne du jeton.

require_once __DIR__ . '/base.php';
require_once __DIR__ . '/import.php';   // IMPORT_COLONNES_MATERIEL

// Politique du GAS pour un mot de passe choisi (GAS_NEWGEN.js:917-920).
const API_MDP_POLITIQUE = 'Le mot de passe doit contenir au moins 12 caractères, une majuscule, une minuscule, un chiffre et un caractère spécial.';

// ── Ateliers ──────────────────────────────────────────────────────────────

function action_save_entry(PDO $db, array $p, string $acteur = ''): array
{
    $d = api_objet($p['entry'] ?? null) ?? $p;
    $err = null;
    $id = api_ecrire_atelier($db, $d, $err, $acteur);
    return $id === null ? ['ok' => false, 'error' => $err] : ['ok' => true, '_id' => $id];
}

// Comme le GAS : chaque entrée est écrite indépendamment, les erreurs sont
// rapportées ensemble. Rejouer le lot est sûr (_id fournis par le client).
function action_save_many(PDO $db, array $p, string $acteur = ''): array
{
    $entries = $p['entries'] ?? null;
    if (is_string($entries)) {
        $entries = json_decode($entries, true);
        if (!is_array($entries)) return ['ok' => false, 'error' => 'JSON invalide'];
    }
    if (!is_array($entries) || !array_is_list($entries)) return ['ok' => false, 'error' => 'entries doit être un tableau'];
    $erreurs = [];
    foreach ($entries as $i => $e) {
        $err = null;
        if (!is_array($e) || api_ecrire_atelier($db, $e, $err, $acteur) === null) {
            $erreurs[] = ['idx' => $i, 'error' => $err ?? 'entrée invalide'];
        }
    }
    if ($erreurs) return ['ok' => false, 'error' => 'Erreurs batch: ' . json_encode($erreurs, JSON_UNESCAPED_UNICODE)];
    return ['ok' => true, 'count' => count($entries)];
}

const CORBEILLE_JOURS = 30;

// Crée la table de la corbeille si besoin (apparue après la bascule du
// 25/09/2026). CREATE TABLE valide toute transaction : appelé avant.
function corbeille_schema(PDO $db): void
{
    foreach (import_requetes_schema() as $sql) {
        if (str_contains($sql, 'ateliers_corbeille')) $db->exec($sql);
    }
}

// Un atelier au format du client (celui de getAll), ou null.
function api_atelier_par_id(PDO $db, string $id): ?array
{
    $cols = implode(', ', array_map(fn($c) => "`$c`", array_values(API_CHAMPS_ATELIER)));
    $s = $db->prepare("SELECT $cols FROM ateliers WHERE id = ?");
    $s->execute([$id]);
    $l = $s->fetch(PDO::FETCH_ASSOC);
    if (!$l) return null;
    $e = [];
    foreach (API_CHAMPS_ATELIER as $cle => $col) $e[$cle] = $l[$col] ?? '';
    $m = $db->prepare('SELECT materiel FROM ateliers_materiel WHERE atelier_id = ? ORDER BY materiel');
    $m->execute([$id]);
    $e['materiel'] = $m->fetchAll(PDO::FETCH_COLUMN);
    return $e;
}

function action_delete(PDO $db, array $p, string $acteur = ''): array
{
    $id = trim((string) ($p['_id'] ?? ''));
    if ($id === '') return ['ok' => false, 'error' => 'ID manquant'];
    corbeille_schema($db);
    $db->beginTransaction();
    try {
        $s = $db->prepare('SELECT conseiller FROM ateliers WHERE id = ? FOR UPDATE');
        $s->execute([$id]);
        $conseiller = $s->fetchColumn();
        if ($conseiller === false) { $db->rollBack(); return ['ok' => false, 'error' => 'Entrée introuvable']; }
        // Corbeille (AG-014) : copie complète avant suppression, 30 jours.
        $db->prepare('REPLACE INTO ateliers_corbeille (id, donnees, supprime_le, supprime_par) VALUES (?, ?, NOW(), ?)')
           ->execute([$id, json_encode(api_atelier_par_id($db, $id), JSON_UNESCAPED_UNICODE), mb_substr($acteur, 0, 100)]);
        $db->prepare('DELETE FROM ateliers WHERE id = ?')->execute([$id]);   // matériel : ON DELETE CASCADE
        // Auteur = la personne connectée ; le conseiller de l'atelier supprimé
        // reste lisible dans ref (l'atelier n'existe plus pour le retrouver).
        api_journal($db, 'delete', $acteur !== '' ? $acteur : (string) $conseiller, mb_substr("$id ($conseiller)", 0, 100), '', 1, 0, '', '');
        $db->commit();
    } catch (Throwable $e) { $db->rollBack(); throw $e; }
    return ['ok' => true];
}

// Lecture seule : « ces ateliers sont-ils enregistrés ? » (GAS_NEWGEN.js:461-471).
function action_verifier_ids(PDO $db, array $p): array
{
    $ids = array_slice(array_values(array_filter(array_map('trim', explode(',', (string) ($p['ids'] ?? ''))))), 0, 50);
    if (!$ids) return ['ok' => true, 'presents' => []];
    $s = $db->prepare('SELECT id FROM ateliers WHERE id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')');
    $s->execute($ids);
    $trouves = array_flip($s->fetchAll(PDO::FETCH_COLUMN));
    return ['ok' => true, 'presents' => array_values(array_filter($ids, fn($i) => isset($trouves[$i])))];
}

// Valide puis écrit un atelier (création ou remplacement). Renvoie son _id,
// ou null avec la raison dans $err.
function api_ecrire_atelier(PDO $db, array $d, ?string &$err, string $acteur = ''): ?string
{
    $l = api_valider_atelier($d, $err);
    if ($l === null) return null;
    $materiel = api_materiel_canonique($db, $d['materiel'] ?? []);

    $db->beginTransaction();
    try {
        // FOR UPDATE : une seconde écriture du même _id attend la fin de
        // celle-ci au lieu de lire un état à moitié écrit.
        $s = $db->prepare('SELECT n FROM ateliers WHERE id = ? FOR UPDATE');
        $s->execute([$l['id']]);
        $n = $s->fetchColumn();
        if ($n === false) {
            // Nouveau : numéro suivant, comme le numéro de ligne du GAS.
            $n = (int) $db->query('SELECT COALESCE(MAX(n), 0) + 1 FROM ateliers FOR UPDATE')->fetchColumn();
        }
        $l['n'] = $n;
        $cols = array_keys($l);
        $sql = 'INSERT INTO ateliers (' . implode(', ', array_map(fn($c) => "`$c`", $cols)) . ') VALUES ('
             . implode(', ', array_fill(0, count($cols), '?')) . ') ON DUPLICATE KEY UPDATE '
             . implode(', ', array_map(fn($c) => "`$c` = VALUES(`$c`)", array_diff($cols, ['id'])));
        $db->prepare($sql)->execute(array_values($l));
        $db->prepare('DELETE FROM ateliers_materiel WHERE atelier_id = ?')->execute([$l['id']]);
        $ins = $db->prepare('INSERT INTO ateliers_materiel (atelier_id, materiel) VALUES (?, ?)');
        foreach ($materiel as $m) $ins->execute([$l['id'], $m]);
        api_journal($db, 'saveEntry', $acteur !== '' ? $acteur : (string) $l['conseiller'], $l['id'], '', 1, 0, '', '');
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
    return $l['id'];
}

// Convertit un atelier reçu du client en ligne de la table, ou null.
function api_valider_atelier(array $d, ?string &$err): ?array
{
    $id = trim((string) ($d['_id'] ?? ''));
    if ($id === '') $id = 'entry_' . (int) (microtime(true) * 1000) . '_' . random_int(0, 9999);
    if (mb_strlen($id) > 64) { $err = '_id trop long'; return null; }

    $l = ['id' => $id];
    foreach (IMPORT_COLONNES_ATELIER as $cle => [$type, $max]) {
        if ($cle === '_id' || $cle === '_n') continue;
        $v = $d[$cle] ?? '';
        if (is_array($v) || is_object($v)) { $err = "$cle : valeur invalide"; return null; }
        $v = is_bool($v) ? '' : trim((string) $v);
        switch ($type) {
            case 'texte':
                if ($max > 0 && mb_strlen($v) > $max) { $err = "$cle : trop long (maximum $max caractères)"; return null; }
                if (strlen($v) > 65535) { $err = "$cle : trop long"; return null; }
                $l[$cle] = $v;
                break;
            case 'entier':
                if ($v === '') { $l[$cle] = null; break; }
                if (!preg_match('/^\d+$/', $v)) { $err = "$cle : « $v » n'est pas un nombre entier"; return null; }
                $l[$cle] = (int) $v;
                break;
            case 'date':
            case 'date_ou_vide':
                if ($v === '') {
                    if ($type === 'date') { $err = 'date manquante'; return null; }
                    $l[$cle] = null;
                    break;
                }
                if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $v, $m) || !checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
                    $err = "$cle : « $v » n'est pas une date aaaa-mm-jj"; return null;
                }
                $l[$cle] = $v;
                break;
            case 'heure':
                if ($v === '') { $l[$cle] = null; break; }
                if (!preg_match('/^(\d{1,2})[:hH](\d{2})$/', $v, $m) || (int) $m[1] > 23 || (int) $m[2] > 59) {
                    $err = "horaire : « $v » n'est pas une heure hh:mm"; return null;
                }
                $l[$cle] = sprintf('%02d:%02d', (int) $m[1], (int) $m[2]);
                break;
        }
    }
    return $l;
}

// Le client envoie les matériels sous le nom de la liste Admin (« Vidéo-
// projecteur ») ; la base garde le nom d'origine de la colonne
// (« Videoprojecteur »). Même rapprochement que _normMat du GAS.
function api_materiel_canonique(PDO $db, mixed $brut): array
{
    if (is_string($brut)) {
        $s = trim($brut);
        $j = str_starts_with($s, '[') ? json_decode($s, true) : null;
        $brut = is_array($j) ? $j : ($s === '' ? [] : explode(str_contains($s, '|') ? '|' : ',', $s));
    }
    if (!is_array($brut)) return [];
    $connus = [];
    $liste = api_liste((string) ($db->query("SELECT valeur FROM config WHERE cle = 'list_materiels'")->fetchColumn() ?: ''));
    foreach (array_merge(IMPORT_COLONNES_MATERIEL, $liste) as $nom) {
        $connus[api_norm_mat((string) $nom)] ??= (string) $nom;
    }
    $sortie = [];
    foreach ($brut as $m) {
        $m = trim((string) $m);
        if ($m === '') continue;
        $nom = mb_substr($connus[api_norm_mat($m)] ?? $m, 0, 100);
        $sortie[$nom] = true;
    }
    return array_keys($sortie);
}

// Port de _normMat (GAS_NEWGEN.js:998).
function api_norm_mat(string $s): string
{
    $s = mb_strtolower(trim($s));
    $s = strtr($s, ['é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'à' => 'a', 'â' => 'a', 'ä' => 'a',
        'î' => 'i', 'ï' => 'i', 'ô' => 'o', 'ö' => 'o', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c']);
    $s = preg_replace('/[^a-z0-9]/', '', $s);
    return preg_replace('/s$/', '', $s);
}

// JSON reçu en texte ou déjà décodé → tableau, sinon null.
function api_objet(mixed $v): ?array
{
    if (is_array($v)) return $v;
    if (is_string($v) && $v !== '') {
        $d = json_decode($v, true);
        return is_array($d) ? $d : null;
    }
    return null;
}

// ── Journal ───────────────────────────────────────────────────────────────

// AG-011, amendement 1 : le journal attribue l'accès à la personne
// CONNECTÉE (celle du jeton) ; le conseiller choisi dans le sélecteur
// d'Index, qui peut être un autre, va dans ref.
function action_log_acces_index(PDO $db, array $p, array $session): array
{
    api_journal($db, 'accesIndex', $session['conseiller'], (string) ($p['conseiller'] ?? ''), $session['role'], 1, 0, (string) ($p['userAgent'] ?? ''), 'index.html');
    return ['ok' => true];
}

function action_get_logs(PDO $db, array $p): array
{
    $n = max(1, min(1000, (int) ($p['n'] ?? 100) ?: 100));
    $l = $db->query("SELECT horodatage, action, conseiller, ref, role, succes, tentatives, user_agent, source FROM journal ORDER BY id DESC LIMIT $n")->fetchAll(PDO::FETCH_ASSOC);
    $paris = new DateTimeZone('Europe/Paris');
    return ['ok' => true, 'logs' => array_map(fn($r) => [
        // Même format que le GAS : ISO 8601 en UTC.
        'timestamp' => (new DateTimeImmutable($r['horodatage'], $paris))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.v\Z'),
        'conseiller' => $r['conseiller'], 'role' => $r['role'] !== '' ? $r['role'] : 'user',
        'success' => (int) $r['succes'] === 1, 'tentatives' => (int) $r['tentatives'],
        'user_agent' => $r['user_agent'], 'source' => $r['source'], 'action' => $r['action'], 'ref' => $r['ref'],
    ], $l)];
}

// ── Configuration (admin) ─────────────────────────────────────────────────

function api_set_config(PDO $db, string $cle, string $valeur): void
{
    $db->prepare('INSERT INTO config (cle, valeur) VALUES (?, ?) ON DUPLICATE KEY UPDATE valeur = VALUES(valeur)')->execute([$cle, $valeur]);
}

function action_set_config(PDO $db, array $p): array
{
    $cle = trim((string) ($p['key'] ?? ''));
    if ($cle === '') return ['ok' => false, 'error' => 'Clé manquante'];
    if (mb_strlen($cle) > 100) return ['ok' => false, 'error' => 'Clé trop longue'];
    $v = $p['value'] ?? '';
    api_set_config($db, $cle, is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE) : (string) $v);
    return ['ok' => true];
}

// saveVisibility / saveColors / saveEmails : un objet JSON sous une clé.
function action_set_json(PDO $db, string $cle, mixed $v): array
{
    $o = api_objet($v) ?? [];
    api_set_config($db, $cle, $o === [] ? '{}' : json_encode($o, JSON_UNESCAPED_UNICODE));
    return ['ok' => true];
}

function action_save_lists(PDO $db, array $p): array
{
    $lists = api_objet($p['lists'] ?? null) ?? [];
    $db->beginTransaction();
    try {
        foreach (['statuts', 'conseillers', 'publics', 'materiels'] as $k) {
            $v = is_array($lists[$k] ?? null) ? array_map('strval', $lists[$k]) : [];
            api_set_config($db, "list_$k", implode("\n", $v));
        }
        // Comme _ensureCompte : tout conseiller de la liste a un compte. Créé
        // sans mot de passe utilisable : l'admin le réinitialise pour en
        // obtenir un provisoire.
        $ins = $db->prepare("INSERT IGNORE INTO comptes (conseiller, hash, role, actif, doit_changer) VALUES (?, NULL, 'user', 1, 1)");
        foreach ((array) ($lists['conseillers'] ?? []) as $nom) {
            $nom = trim((string) $nom);
            if ($nom !== '' && mb_strlen($nom) <= 100) $ins->execute([$nom]);
        }
        $db->commit();
    } catch (Throwable $e) { $db->rollBack(); throw $e; }
    return ['ok' => true];
}

// ── Comptes ───────────────────────────────────────────────────────────────

function action_save_compte(PDO $db, array $p): array
{
    $nom = trim((string) ($p['conseiller'] ?? ''));
    if ($nom === '') return ['ok' => false, 'error' => 'Nom manquant'];
    $s = $db->prepare('SELECT role, actif FROM comptes WHERE conseiller = ?');
    $s->execute([$nom]);
    $c = $s->fetch(PDO::FETCH_ASSOC);
    if (!$c) return ['ok' => false, 'error' => 'Compte introuvable'];
    $role = isset($p['role']) ? (string) $p['role'] : $c['role'];
    if (!in_array($role, ['admin', 'superviseur', 'user'], true)) return ['ok' => false, 'error' => 'Rôle inconnu'];
    $actif = isset($p['actif']) ? ((string) $p['actif'] === 'NON' ? 0 : 1) : (int) $c['actif'];
    $db->prepare('UPDATE comptes SET role = ?, actif = ? WHERE conseiller = ?')->execute([$role, $actif, $nom]);
    // Rôle changé : ses connexions en cours tombent, sinon il garderait
    // jusqu'à 6 h les droits d'avant. L'interrupteur « actif » (accès Admin)
    // n'a pas besoin de ça : il est relu à chaque action d'administration,
    // et couper les sessions déconnecterait aussi Index.
    if ($role !== $c['role']) {
        $db->prepare('DELETE FROM sessions WHERE conseiller = ?')->execute([$nom]);
    }
    return ['ok' => true];
}

function action_reset_password(PDO $db, array $p): array
{
    $nom = trim((string) ($p['conseiller'] ?? ''));
    if ($nom === '') return ['ok' => false, 'error' => 'Nom manquant'];
    // Sans caractères ambigus (0/O, 1/l/I) : il sera recopié à la main.
    $alphabet = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $mdp = '';
    for ($i = 0; $i < 12; $i++) $mdp .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    $u = $db->prepare('UPDATE comptes SET hash = ?, doit_changer = 1 WHERE conseiller = ?');
    $u->execute([password_hash(hash('sha256', $mdp), PASSWORD_DEFAULT), $nom]);
    if ($u->rowCount() === 0) return ['ok' => false, 'error' => 'Conseiller introuvable'];
    $db->prepare('DELETE FROM sessions WHERE conseiller = ?')->execute([$nom]);
    $db->prepare('DELETE FROM tentatives WHERE conseiller = ?')->execute([$nom]);
    return ['ok' => true, 'newPassword' => $mdp];
}

// Admin : change le mot de passe d'un compte (écran « Changer mon mot de
// passe »). Le mot de passe ACTUEL de ce compte est exigé (audit du
// 24/09/2026 : sans lui, un Admin resté ouvert suffisait à prendre le
// compte) ; un échec compte comme une connexion ratée. Pour un collègue qui
// a oublié le sien : resetPassword, ou « mot de passe oublié ».
function action_set_password(PDO $db, array $p, array $session): array
{
    $nom = trim((string) ($p['conseiller'] ?? ''));
    $actuel = trim((string) ($p['currentPwd'] ?? ''));
    if ($nom === '' || $actuel === '') return ['ok' => false, 'error' => 'Mot de passe actuel requis'];
    $bloque = api_blocage($db, $nom);
    if ($bloque !== null) return ['ok' => false, 'error' => $bloque];
    $s = $db->prepare('SELECT hash FROM comptes WHERE conseiller = ?');
    $s->execute([$nom]);
    $hash = $s->fetchColumn();
    if (!$hash || !password_verify(hash('sha256', $actuel), $hash)) {
        if ($hash !== false) api_echec_mdp($db, $nom, '', 'admin');
        return ['ok' => false, 'error' => 'Mot de passe actuel incorrect'];
    }
    return api_changer_mdp($db, $nom, (string) ($p['password'] ?? ''));
}

// Conseiller : change SON mot de passe — toujours celui du jeton.
function action_self_set_password(PDO $db, array $p, array $session): array
{
    return api_changer_mdp($db, $session['conseiller'], (string) ($p['password'] ?? ''));
}

function api_changer_mdp(PDO $db, string $nom, string $mdp): array
{
    $mdp = trim($mdp);
    if ($nom === '' || $mdp === '') return ['ok' => false, 'error' => 'Paramètres manquants'];
    if (!(strlen($mdp) >= 12 && preg_match('/[A-Z]/', $mdp) && preg_match('/[a-z]/', $mdp) && preg_match('/[0-9]/', $mdp) && preg_match('/[^A-Za-z0-9]/', $mdp))) {
        return ['ok' => false, 'error' => API_MDP_POLITIQUE];
    }
    $u = $db->prepare('UPDATE comptes SET hash = ?, doit_changer = 0 WHERE conseiller = ?');
    $u->execute([password_hash(hash('sha256', $mdp), PASSWORD_DEFAULT), $nom]);
    if ($u->rowCount() === 0) {
        // rowCount vaut 0 aussi si rien n'a changé : vérifier l'existence.
        $s = $db->prepare('SELECT 1 FROM comptes WHERE conseiller = ?');
        $s->execute([$nom]);
        if (!$s->fetchColumn()) return ['ok' => false, 'error' => 'Conseiller introuvable'];
    }
    return ['ok' => true];
}

// ── Corbeille (AG-014) ────────────────────────────────────────────────────

function action_get_corbeille(PDO $db): array
{
    corbeille_schema($db);
    $db->exec('DELETE FROM ateliers_corbeille WHERE supprime_le < NOW() - INTERVAL ' . CORBEILLE_JOURS . ' DAY');
    $l = $db->query('SELECT id, donnees, supprime_le, supprime_par FROM ateliers_corbeille ORDER BY supprime_le DESC')->fetchAll(PDO::FETCH_ASSOC);
    return ['ok' => true, 'jours' => CORBEILLE_JOURS, 'ateliers' => array_map(function ($r) {
        $e = json_decode($r['donnees'], true) ?: [];
        return ['_id' => $r['id'], 'supprime_le' => $r['supprime_le'], 'supprime_par' => $r['supprime_par'],
                'date' => $e['date'] ?? '', 'horaire' => $e['horaire'] ?? '', 'thematique' => $e['thematique'] ?? '',
                'commune' => $e['commune'] ?? '', 'conseiller' => $e['conseiller'] ?? ''];
    }, $l)];
}

// Remet l'atelier tel qu'il était. Refusé si un atelier de même _id existe
// (recréé entre-temps) : jamais d'écrasement silencieux.
function action_restaurer_corbeille(PDO $db, array $p, string $acteur): array
{
    $id = trim((string) ($p['_id'] ?? ''));
    if ($id === '') return ['ok' => false, 'error' => 'ID manquant'];
    corbeille_schema($db);
    $s = $db->prepare('SELECT donnees FROM ateliers_corbeille WHERE id = ?');
    $s->execute([$id]);
    $donnees = $s->fetchColumn();
    if ($donnees === false) return ['ok' => false, 'error' => 'Atelier absent de la corbeille (déjà restauré ou purgé)'];
    if (api_atelier_par_id($db, $id) !== null) return ['ok' => false, 'error' => 'Un atelier avec cet identifiant existe déjà'];
    $d = json_decode($donnees, true);
    if (!is_array($d)) return ['ok' => false, 'error' => 'Données de la corbeille illisibles'];
    $err = null;
    if (api_ecrire_atelier($db, $d, $err, $acteur) === null) return ['ok' => false, 'error' => $err];
    $db->prepare('DELETE FROM ateliers_corbeille WHERE id = ?')->execute([$id]);
    return ['ok' => true, 'entry' => api_atelier_par_id($db, $id)];
}

// ── Sauvegardes (AG-014) : état en lecture seule, copie à la demande ─────

function action_etat_sauvegardes(): array
{
    require_once __DIR__ . '/copie.php';
    $d = sauvegarde_dossier();
    $f = glob("$d/ateliers-*.sql.gz") ?: [];
    rsort($f);
    // Marqueur déposé par le workflow de la copie chiffrée (ateliers-backups).
    $m = "$d/.derniere-copie-chiffree";
    return ['ok' => true, 'jours' => SAUVEGARDE_JOURS,
        'copies' => array_map(fn($x) => ['nom' => basename($x), 'ko' => (int) ceil(filesize($x) / 1024), 'date' => date('Y-m-d H:i:s', filemtime($x))], $f),
        'chiffree' => is_file($m) ? trim((string) file_get_contents($m)) : ''];
}

// Au plus une copie toutes les 5 minutes : le bouton ne doit pas pouvoir
// remplir l'espace disque du compte.
function action_copie_maintenant(): array
{
    require_once __DIR__ . '/copie.php';
    $f = glob(sauvegarde_dossier() . '/ateliers-*.sql.gz') ?: [];
    $derniere = $f ? max(array_map('filemtime', $f)) : 0;
    if ($derniere > time() - 300) return ['ok' => false, 'error' => 'Une copie a déjà été faite il y a moins de 5 minutes.'];
    $r = sauvegarde_faire();
    return $r['ok'] ? ['ok' => true, 'fichier' => $r['fichier']] : ['ok' => false, 'error' => 'Copie impossible : ' . $r['message']];
}
