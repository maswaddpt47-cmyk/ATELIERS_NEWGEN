<?php
// Page des travailleurs sociaux (AG-042, 04/11/2026) : consultation seule
// des ateliers « Planifié » à venir, sur ts.html (NEWGEN et NextStep).
//
// Comptes à part (table comptes_ts, identifiant = e-mail pro) créés par
// l'Admin à partir des demandes d'accès déposées sur la page. Les comptes
// admin/superviseur à accès Admin actif peuvent aussi s'y connecter
// (demande de l'utilisateur, 04/11/2026), toujours en lecture seule.
//
// Session : table `sessions`, role 'ts', conseiller = 'ts:' + identifiant.
// Un jeton 'ts' n'ouvre que API_ACTIONS_TS (garde dans api_traiter) : sans
// elle, il ouvrirait getAll (remarques, inscrits, conseillers…).

require_once __DIR__ . '/base.php';
require_once __DIR__ . '/mail.php';
require_once __DIR__ . '/tickets.php';   // tickets_destinataires : admins à prévenir

const TS_PREFIXE = 'ts:';
const TS_DEMANDES_MAX = 30;      // demandes en attente au-delà desquelles on refuse
const TS_DEMANDES_JOURS = 90;    // purge des demandes non traitées
const TS_EMAIL_MAX = 97;         // 'ts:' + e-mail tient dans VARCHAR(100) (sessions, tentatives)
const TS_CHAMPS = ['date', 'statut', 'orienteur', 'commune', 'lieu', 'public', 'thematique'];
const TS_URL_ADMIN = [
    'newgen'   => 'https://maswaddpt47-cmyk.github.io/ATELIERS_NEWGEN/admin.html',
    'nextstep' => 'https://maswaddpt47-cmyk.github.io/ateliers-cd47_NextStep/admin.html',
];
const TS_ERREUR_CONNEXION = 'Identifiant ou mot de passe incorrect';

function ts_schema(PDO $db): void
{
    static $ok = false;
    if ($ok) return;
    foreach (import_requetes_schema() as $sql) {
        if (str_contains($sql, 'EXISTS comptes_ts') || str_contains($sql, 'EXISTS demandes_ts')) $db->exec($sql);
    }
    $ok = true;
}

// E-mail normalisé (minuscules), ou null s'il est invalide ou trop long.
function ts_email(mixed $v): ?string
{
    // Espaces retirés : le clavier d'un téléphone en ajoute après un point.
    $e = mb_strtolower(preg_replace('/\s+/u', '', (string) $v));
    if ($e === '' || strlen($e) > TS_EMAIL_MAX || !filter_var($e, FILTER_VALIDATE_EMAIL)) return null;
    return $e;
}

function ts_mdp_provisoire(): string
{
    // Sans caractères ambigus (0/O, 1/l/I) : il sera recopié à la main.
    $alphabet = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $mdp = '';
    for ($i = 0; $i < 12; $i++) $mdp .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    return $mdp;
}

function ts_fermer_sessions(PDO $db, string $identifiant, ?string $garder = null): void
{
    $db->prepare('DELETE FROM sessions WHERE conseiller = ? AND jeton_hash <> ?')->execute([TS_PREFIXE . $identifiant, (string) $garder]);
}

// ── Public ────────────────────────────────────────────────────────────────

// Demande d'accès. Réponse identique que l'adresse soit connue ou non :
// la page ne doit pas servir à savoir qui a un compte.
function action_ts_demande_acces(PDO $db, array $p): array
{
    ts_schema($db);
    // Champ piège : invisible pour un humain, rempli par les robots.
    if (trim((string) ($p['url'] ?? '')) !== '') return ['ok' => true];
    $email = ts_email($p['email'] ?? '');
    if ($email === null) return ['ok' => false, 'error' => 'Adresse e-mail invalide'];
    $db->exec('DELETE FROM demandes_ts WHERE demande_le < NOW() - INTERVAL ' . TS_DEMANDES_JOURS . ' DAY');

    $s = $db->prepare('SELECT (SELECT COUNT(*) FROM comptes_ts WHERE email = ?) + (SELECT COUNT(*) FROM demandes_ts WHERE email = ?)');
    $s->execute([$email, $email]);
    if ((int) $s->fetchColumn() > 0) return ['ok' => true];

    if ((int) $db->query('SELECT COUNT(*) FROM demandes_ts')->fetchColumn() >= TS_DEMANDES_MAX) {
        return ['ok' => false, 'error' => 'Trop de demandes en attente : réessayez dans quelques jours.'];
    }
    $site = api_site_courant() ?: 'nextstep';
    $db->prepare('INSERT INTO demandes_ts (email, demande_le, site) VALUES (?, NOW(), ?)')->execute([$email, $site]);
    api_journal($db, 'tsDemande', '', $email, 'ts', 1, 0, '', 'ts');
    ts_prevenir_admins($db, $email, $site);
    return ['ok' => true];
}

function ts_prevenir_admins(PDO $db, string $email, string $site): void
{
    $url = TS_URL_ADMIN[$site] ?? TS_URL_ADMIN['nextstep'];
    $h = fn($x) => htmlspecialchars((string) $x, ENT_QUOTES, 'UTF-8');
    $sujet = "Demande d'accès à la page des travailleurs sociaux";
    $texte = "Nouvelle demande d'accès à la page des travailleurs sociaux : $email\n\n"
           . "Créer le compte dans l'Admin, onglet Admin, rubrique « Accès travailleurs sociaux » : $url\n";
    $html = '<div style="font-family:sans-serif;max-width:640px"><p>Nouvelle demande d\'accès à la page des travailleurs sociaux :</p>'
          . '<p style="font-size:16px;font-weight:700">' . $h($email) . '</p>'
          . '<p><a href="' . $h($url) . '" style="color:#1e3a8a;font-weight:700">Créer le compte dans l\'Admin</a> (onglet Admin, rubrique « Accès travailleurs sociaux »)</p></div>';
    foreach (tickets_destinataires($db) as $a) {
        try { mail_envoyer($a, $sujet, $texte, $html); } catch (Throwable $e) { /* la demande reste enregistrée */ }
    }
}

// Connexion. Identifiant avec « @ » : compte TS ; sans : compte
// admin/superviseur à accès Admin actif (même compteur d'échecs que sa
// connexion habituelle). Un seul message d'échec pour tous les cas.
function action_ts_connexion(PDO $db, array $p): array
{
    ts_schema($db);
    $saisi = trim((string) ($p['identifiant'] ?? ''));
    $mdp = trim((string) ($p['password'] ?? ''));
    if ($saisi === '' || $mdp === '') return ['ok' => false, 'error' => 'Identifiant et mot de passe requis'];

    if (str_contains($saisi, '@')) {
        $id = ts_email($saisi);
        if ($id === null) return ['ok' => false, 'error' => TS_ERREUR_CONNEXION];
        $cleTentatives = TS_PREFIXE . $id;
        $s = $db->prepare('SELECT hash, actif, doit_changer FROM comptes_ts WHERE email = ?');
        $s->execute([$id]);
        $c = $s->fetch(PDO::FETCH_ASSOC);
        $valide = $c && (int) $c['actif'] === 1;
        $doitChanger = $c && (int) $c['doit_changer'] === 1;
        $role = 'ts';
    } else {
        $id = $saisi;
        $cleTentatives = $id;
        $s = $db->prepare('SELECT hash, role, actif FROM comptes WHERE conseiller = ?');
        $s->execute([$id]);
        $c = $s->fetch(PDO::FETCH_ASSOC);
        $valide = $c && (int) $c['actif'] === 1 && in_array($c['role'], API_ROLES_ADMIN, true);
        $doitChanger = false;
        $role = $c ? (string) $c['role'] : '';
    }

    $bloque = api_blocage($db, $cleTentatives);
    if ($bloque !== null) return ['ok' => false, 'error' => $bloque];
    $ok = $valide && $c['hash'] !== null && password_verify(hash('sha256', $mdp), $c['hash']);
    if (!$ok) {
        // E-mail : compté même inconnu (sinon le blocage dirait qui a un
        // compte). Nom : compté s'il existe, comme checkPassword (les noms
        // sont déjà publics par getComptes).
        if ($role === 'ts' || $c) api_echec_mdp($db, $cleTentatives, (string) ($p['userAgent'] ?? ''), 'ts');
        return ['ok' => false, 'error' => TS_ERREUR_CONNEXION];
    }
    $db->prepare('DELETE FROM tentatives WHERE conseiller = ?')->execute([$cleTentatives]);
    $jeton = bin2hex(random_bytes(32));
    $db->exec('DELETE FROM sessions WHERE expire < NOW()');
    $db->prepare('INSERT INTO sessions (jeton_hash, conseiller, role, expire) VALUES (?, ?, ?, ?)')
       ->execute([hash('sha256', $jeton), TS_PREFIXE . $id, 'ts', date('Y-m-d H:i:s', time() + API_JETON_DUREE_S)]);
    api_journal($db, 'loginTs', $id, '', $role === 'ts' ? 'ts' : $role, 1, 0, (string) ($p['userAgent'] ?? ''), 'ts');
    $r = ['ok' => true, 'token' => $jeton];
    if ($doitChanger) $r['doit_changer'] = true;
    return $r;
}

// ── Session TS ────────────────────────────────────────────────────────────

// Identifiant derrière une session 'ts' (e-mail ou nom d'admin), si le
// compte est toujours ouvert : relu à chaque appel, une désactivation
// prend effet aussitôt. null sinon.
function ts_identite(PDO $db, array $session): ?array
{
    if (($session['role'] ?? '') !== 'ts' || !str_starts_with((string) $session['conseiller'], TS_PREFIXE)) return null;
    $id = substr((string) $session['conseiller'], strlen(TS_PREFIXE));
    if (str_contains($id, '@')) {
        $s = $db->prepare('SELECT doit_changer FROM comptes_ts WHERE email = ? AND actif = 1');
        $s->execute([$id]);
        $d = $s->fetchColumn();
        return $d === false ? null : ['id' => $id, 'ts' => true, 'doit_changer' => (int) $d === 1];
    }
    $s = $db->prepare('SELECT role FROM comptes WHERE conseiller = ? AND actif = 1');
    $s->execute([$id]);
    $role = $s->fetchColumn();
    return in_array($role, API_ROLES_ADMIN, true) ? ['id' => $id, 'ts' => false, 'doit_changer' => false] : null;
}

function action_ts_ateliers(PDO $db, array $session): array
{
    ts_schema($db);
    $qui = ts_identite($db, $session);
    if ($qui === null) return ['ok' => false, 'error' => 'Non autorisé', 'auth' => true];
    if ($qui['doit_changer']) return ['ok' => false, 'error' => 'Mot de passe provisoire à changer', 'doit_changer' => true];
    $cols = implode(', ', array_map(fn($c) => "`$c`", TS_CHAMPS));
    $l = $db->query("SELECT $cols FROM ateliers WHERE statut = 'Planifié' AND date >= CURDATE() ORDER BY date, horaire")->fetchAll(PDO::FETCH_ASSOC);
    // Un interrupteur par type de public (Admin → Visibilité — Page TS,
    // décision de l'utilisateur du 04/11/2026) : clé « ts_public:<type> » de la
    // config « visibility ». Éteint = les ateliers de ce public ne partent pas
    // vers la page. Absent = visible (un type ajouté plus tard n'est pas caché
    // par surprise). Clés à plat : visibiliteEffective (utils.js) réduit
    // chaque valeur à un booléen, un objet imbriqué serait écrasé.
    $vis = api_json(api_config_base($db)['visibility'] ?? '', []);
    $l = array_values(array_filter($l, fn($a) => !ts_public_cache($vis, (string) $a['public'])));
    return ['ok' => true, 'ateliers' => $l];
}

// Type de public éteint pour la page TS ? Même lecture que visibiliteEffective
// (utils.js) : false, 0, '' ou 'false' = éteint ; absent = allumé.
function ts_public_cache(mixed $vis, string $public): bool
{
    $cle = 'ts_public:' . trim($public);
    if (!is_array($vis) || trim($public) === '' || !array_key_exists($cle, $vis)) return false;
    $v = $vis[$cle];
    return $v === false || $v === 0 || $v === '' || $v === null || strtolower((string) $v) === 'false';
}

// Change le mot de passe du compte TS de la session. Le mot de passe actuel
// est exigé, sauf pour quitter un provisoire (comme selfSetPassword).
function action_ts_changer_mdp(PDO $db, array $p, array $session): array
{
    ts_schema($db);
    $qui = ts_identite($db, $session);
    if ($qui === null) return ['ok' => false, 'error' => 'Non autorisé', 'auth' => true];
    if (!$qui['ts']) return ['ok' => false, 'error' => 'Changez votre mot de passe depuis l\'Admin'];
    $id = $qui['id'];
    if (!$qui['doit_changer']) {
        $bloque = api_blocage($db, TS_PREFIXE . $id);
        if ($bloque !== null) return ['ok' => false, 'error' => $bloque];
        $s = $db->prepare('SELECT hash FROM comptes_ts WHERE email = ?');
        $s->execute([$id]);
        $hash = $s->fetchColumn();
        if (!$hash || !password_verify(hash('sha256', trim((string) ($p['currentPwd'] ?? ''))), $hash)) {
            api_echec_mdp($db, TS_PREFIXE . $id, '', 'ts');
            return ['ok' => false, 'error' => 'Mot de passe actuel incorrect'];
        }
    }
    $mdp = trim((string) ($p['password'] ?? ''));
    if (!(strlen($mdp) >= 12 && preg_match('/[A-Z]/', $mdp) && preg_match('/[a-z]/', $mdp) && preg_match('/[0-9]/', $mdp) && preg_match('/[^A-Za-z0-9]/', $mdp))) {
        return ['ok' => false, 'error' => API_MDP_POLITIQUE];
    }
    $db->prepare('UPDATE comptes_ts SET hash = ?, doit_changer = 0 WHERE email = ?')->execute([password_hash(hash('sha256', $mdp), PASSWORD_DEFAULT), $id]);
    ts_fermer_sessions($db, $id, $session['jeton_hash'] ?? null);
    api_journal($db, 'tsChangerMdp', $id, '', 'ts', 1, 0, '', 'ts');
    return ['ok' => true];
}

// ── Admin ─────────────────────────────────────────────────────────────────

function action_ts_liste(PDO $db): array
{
    ts_schema($db);
    $db->exec('DELETE FROM demandes_ts WHERE demande_le < NOW() - INTERVAL ' . TS_DEMANDES_JOURS . ' DAY');
    return [
        'ok' => true,
        'demandes' => $db->query('SELECT email, demande_le, site FROM demandes_ts ORDER BY demande_le')->fetchAll(PDO::FETCH_ASSOC),
        'comptes' => array_map(fn($c) => ['email' => $c['email'], 'actif' => (int) $c['actif'] === 1, 'doit_changer' => (int) $c['doit_changer'] === 1, 'cree_le' => $c['cree_le']],
            $db->query('SELECT email, actif, doit_changer, cree_le FROM comptes_ts ORDER BY email')->fetchAll(PDO::FETCH_ASSOC)),
        'demandesMax' => TS_DEMANDES_MAX,
    ];
}

// Crée le compte (depuis une demande ou une adresse saisie) avec un mot de
// passe provisoire, rendu une seule fois à l'Admin qui le transmet.
function action_ts_creer_compte(PDO $db, array $p): array
{
    ts_schema($db);
    $email = ts_email($p['email'] ?? '');
    if ($email === null) return ['ok' => false, 'error' => 'Adresse e-mail invalide'];
    $s = $db->prepare('SELECT 1 FROM comptes_ts WHERE email = ?');
    $s->execute([$email]);
    if ($s->fetchColumn()) return ['ok' => false, 'error' => 'Ce compte existe déjà'];
    $mdp = ts_mdp_provisoire();
    $db->prepare('INSERT INTO comptes_ts (email, hash, actif, doit_changer, cree_le) VALUES (?, ?, 1, 1, NOW())')
       ->execute([$email, password_hash(hash('sha256', $mdp), PASSWORD_DEFAULT)]);
    $db->prepare('DELETE FROM demandes_ts WHERE email = ?')->execute([$email]);
    return ['ok' => true, 'email' => $email, 'newPassword' => $mdp];
}

function action_ts_reinit_mdp(PDO $db, array $p): array
{
    ts_schema($db);
    $email = ts_email($p['email'] ?? '');
    if ($email === null) return ['ok' => false, 'error' => 'Adresse e-mail invalide'];
    $mdp = ts_mdp_provisoire();
    $u = $db->prepare('UPDATE comptes_ts SET hash = ?, doit_changer = 1 WHERE email = ?');
    $u->execute([password_hash(hash('sha256', $mdp), PASSWORD_DEFAULT), $email]);
    if ($u->rowCount() === 0) return ['ok' => false, 'error' => 'Compte introuvable'];
    ts_fermer_sessions($db, $email);
    $db->prepare('DELETE FROM tentatives WHERE conseiller = ?')->execute([TS_PREFIXE . $email]);
    return ['ok' => true, 'newPassword' => $mdp];
}

function action_ts_activer(PDO $db, array $p): array
{
    ts_schema($db);
    $email = ts_email($p['email'] ?? '');
    if ($email === null) return ['ok' => false, 'error' => 'Adresse e-mail invalide'];
    $actif = in_array((string) ($p['actif'] ?? ''), ['1', 'true'], true) ? 1 : 0;
    $s = $db->prepare('SELECT 1 FROM comptes_ts WHERE email = ?');
    $s->execute([$email]);
    if (!$s->fetchColumn()) return ['ok' => false, 'error' => 'Compte introuvable'];
    $db->prepare('UPDATE comptes_ts SET actif = ? WHERE email = ?')->execute([$actif, $email]);
    if (!$actif) ts_fermer_sessions($db, $email);
    return ['ok' => true];
}

// Supprime un compte, ou refuse une demande (même action : l'adresse
// disparaît des deux tables).
function action_ts_supprimer(PDO $db, array $p): array
{
    ts_schema($db);
    $email = ts_email($p['email'] ?? '');
    if ($email === null) return ['ok' => false, 'error' => 'Adresse e-mail invalide'];
    $a = $db->prepare('DELETE FROM comptes_ts WHERE email = ?');
    $a->execute([$email]);
    $b = $db->prepare('DELETE FROM demandes_ts WHERE email = ?');
    $b->execute([$email]);
    if ($a->rowCount() + $b->rowCount() === 0) return ['ok' => false, 'error' => 'Introuvable'];
    ts_fermer_sessions($db, $email);
    $db->prepare('DELETE FROM tentatives WHERE conseiller = ?')->execute([TS_PREFIXE . $email]);
    return ['ok' => true];
}
